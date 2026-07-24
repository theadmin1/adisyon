using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Workers;

/// <summary>
/// Laravel'deki ÖKC kart işlemi kuyruğunu dinleyen, işlemi fiziki terminale
/// ileten ve sonucu merkeze bildiren arka plan servisi.
///
/// PARA İŞLEMİ OLDUĞU İÇİN yazdırma işçisinden iki kritik farkı vardır:
///  1. İşlem ASLA otomatik yeniden denenmez (çift tahsilat riski).
///  2. Sonuç bildirimi başarısız olursa ısrarla tekrar denenir — sunucunun
///     onaylanmış bir ödemeden habersiz kalması kabul edilemez.
/// </summary>
public class PosBackgroundWorker : BackgroundService
{
    private readonly IServiceScopeFactory _scopeFactory;
    private readonly INotificationService _notifications;
    private readonly ILogger<PosBackgroundWorker> _logger;

    private static readonly TimeSpan PollInterval = TimeSpan.FromSeconds(2);
    private static readonly TimeSpan ErrorBackoff = TimeSpan.FromSeconds(10);

    /// <summary>Sonuç bildirimi için deneme sayısı ve bekleme süresi.</summary>
    private const int ResultReportAttempts = 5;
    private static readonly TimeSpan ResultReportDelay = TimeSpan.FromSeconds(3);

    public PosBackgroundWorker(
        IServiceScopeFactory scopeFactory,
        INotificationService notifications,
        ILogger<PosBackgroundWorker> logger)
    {
        _scopeFactory = scopeFactory;
        _notifications = notifications;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("💳 AltF4 ÖKC Kart İşlemi Arka Plan Servisi başlatıldı.");

        while (!stoppingToken.IsCancellationRequested)
        {
            var delay = PollInterval;

            try
            {
                using var scope = _scopeFactory.CreateScope();
                var apiClient = scope.ServiceProvider.GetRequiredService<ILaravelApiClient>();
                var configService = scope.ServiceProvider.GetRequiredService<IPosConfigService>();

                var config = await configService.GetAsync(stoppingToken);

                if (!config.IsEnabled)
                {
                    // ÖKC kapalıysa kuyruğu hiç yoklama.
                    await Task.Delay(TimeSpan.FromSeconds(15), stoppingToken);
                    continue;
                }

                var transactions = await apiClient.GetPendingPosTransactionsAsync(stoppingToken);

                foreach (var transaction in transactions)
                {
                    if (stoppingToken.IsCancellationRequested)
                    {
                        break;
                    }

                    await ProcessTransactionAsync(scope.ServiceProvider, apiClient, transaction, stoppingToken);
                }
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "ÖKC servisinde beklenmeyen hata. {Seconds} sn beklenecek.", ErrorBackoff.TotalSeconds);
                delay = ErrorBackoff;
            }

            try
            {
                await Task.Delay(delay, stoppingToken);
            }
            catch (OperationCanceledException)
            {
                break;
            }
        }

        _logger.LogInformation("ÖKC Worker durduruldu.");
    }

    private async Task ProcessTransactionAsync(
        IServiceProvider services,
        ILaravelApiClient apiClient,
        PosTransactionDto transaction,
        CancellationToken cancellationToken)
    {
        // Gerçek terminal mi simülatör mü: karar tek bir yerde, resolver'da.
        var terminal = await services.GetRequiredService<IPosTerminalResolver>()
            .ResolveAsync(cancellationToken);

        _notifications.Show(
            "💳 Kart ödemesi başlatıldı",
            $"{transaction.Amount:0.00} TL"
            + (transaction.Installment > 1 ? $" / {transaction.Installment} taksit" : string.Empty)
            + $"\nMüşterinin kart okutması bekleniyor.",
            NotificationLevel.Info);

        PosResultDto result;

        try
        {
            result = await terminal.ProcessSaleAsync(
                transaction,
                status => apiClient.UpdatePosTransactionStatusAsync(transaction.Id, status, cancellationToken),
                cancellationToken);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "ÖKC işlemi sırasında istisna [#{TxId}]", transaction.Id);

            result = PosResultDto.Failure(
                $"Beklenmeyen hata: {ex.Message}. İşlemin durumunu terminal ekranından doğrulayın.",
                "WORKER_EXCEPTION");
        }

        await ReportResultAsync(apiClient, transaction, result, cancellationToken);
        NotifyResult(transaction, result);
    }

    /// <summary>
    /// Sonucu merkeze bildirir. Onaylanmış bir ödemenin sunucuya ulaşmaması
    /// kabul edilemez olduğu için ısrarla tekrar denenir.
    /// </summary>
    private async Task ReportResultAsync(
        ILaravelApiClient apiClient,
        PosTransactionDto transaction,
        PosResultDto result,
        CancellationToken cancellationToken)
    {
        for (int attempt = 1; attempt <= ResultReportAttempts; attempt++)
        {
            if (await apiClient.SubmitPosResultAsync(transaction.Id, result, cancellationToken))
            {
                return;
            }

            _logger.LogWarning(
                "ÖKC sonucu sunucuya bildirilemedi [#{TxId}] (deneme {Attempt}/{Total}).",
                transaction.Id, attempt, ResultReportAttempts);

            if (attempt < ResultReportAttempts)
            {
                try
                {
                    await Task.Delay(ResultReportDelay, cancellationToken);
                }
                catch (OperationCanceledException)
                {
                    break;
                }
            }
        }

        // Tüm denemeler başarısız: onaylanmış ödeme sunucuya işlenemedi.
        // Bu, elle müdahale gerektiren ciddi bir durumdur.
        if (result.IsApproved)
        {
            _logger.LogCritical(
                "🛑 ONAYLANMIŞ ödeme sunucuya bildirilemedi! İşlem #{TxId}, tutar {Amount:0.00} TL, "
                + "onay kodu {Code}. Ödeme ELLE kaydedilmelidir.",
                transaction.Id, transaction.Amount, result.ApprovalCode ?? "-");

            _notifications.Show(
                "🛑 Ödeme kaydedilemedi!",
                $"{transaction.Amount:0.00} TL tahsil edildi (onay: {result.ApprovalCode}) "
                + "ancak sunucuya işlenemedi. Adisyonu elle kapatın ve fişi saklayın.",
                NotificationLevel.Error);
        }
    }

    private void NotifyResult(PosTransactionDto transaction, PosResultDto result)
    {
        if (result.IsApproved)
        {
            _notifications.Show(
                "✅ Ödeme onaylandı",
                $"{transaction.Amount:0.00} TL"
                + (string.IsNullOrWhiteSpace(result.MaskedPan) ? string.Empty : $"\nKart: {result.MaskedPan}")
                + (string.IsNullOrWhiteSpace(result.ApprovalCode) ? string.Empty : $"\nOnay kodu: {result.ApprovalCode}"),
                NotificationLevel.Success);

            return;
        }

        var level = string.Equals(result.Status, "declined", StringComparison.OrdinalIgnoreCase)
            ? NotificationLevel.Warning
            : NotificationLevel.Error;

        _notifications.Show(
            string.Equals(result.Status, "declined", StringComparison.OrdinalIgnoreCase)
                ? "⚠️ Ödeme reddedildi"
                : "❌ Ödeme tamamlanamadı",
            $"{transaction.Amount:0.00} TL\n{result.ErrorMessage ?? "Bilinmeyen hata"}",
            level);
    }
}

using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Workers;

/// <summary>
/// Laravel sunucusundaki fiş yazdırma kuyruğunu (Print Spooler) sürekli dinleyen,
/// talepleri alıp fiziki termal yazıcılara ileten ve durum güncellemelerini bildiren
/// arka plan servisi.
///
/// Mükerrer baskı koruması: sunucu, işleri /print/pending yanıtında ATOMİK olarak
/// bu cihaza kilitler (claim). Aynı şubede birden fazla kasa olsa dahi bir fiş
/// yalnızca bir kez basılır.
/// </summary>
public class PrintBackgroundWorker : BackgroundService
{
    private readonly IServiceScopeFactory _scopeFactory;
    private readonly IPrinterService _printerService;
    private readonly INotificationService _notifications;
    private readonly ILogger<PrintBackgroundWorker> _logger;

    private static readonly TimeSpan PollInterval = TimeSpan.FromSeconds(2);
    private static readonly TimeSpan ErrorBackoff = TimeSpan.FromSeconds(10);

    public PrintBackgroundWorker(
        IServiceScopeFactory scopeFactory,
        IPrinterService printerService,
        INotificationService notifications,
        ILogger<PrintBackgroundWorker> logger)
    {
        _scopeFactory = scopeFactory;
        _printerService = printerService;
        _notifications = notifications;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("AltF4 Termal Fiş Yazdırma Arka Plan Servisi (Print Worker) başlatıldı.");

        while (!stoppingToken.IsCancellationRequested)
        {
            var delay = PollInterval;

            try
            {
                // HttpClient'ı singleton bir alanda tutmak IHttpClientFactory'nin handler
                // rotasyonunu devre dışı bırakır (7/24 çalışan serviste DNS değişimi
                // algılanmaz). Bu yüzden istemci her turda scope'tan çözülür.
                using var scope = _scopeFactory.CreateScope();
                var apiClient = scope.ServiceProvider.GetRequiredService<ILaravelApiClient>();
                var printerConfig = scope.ServiceProvider.GetRequiredService<IPrinterConfigService>();

                var claimedJobs = await apiClient.GetPendingPrintJobsAsync(stoppingToken);

                if (claimedJobs.Count > 0)
                {
                    _logger.LogInformation("{Count} adet fiş yazdırma talebi alındı.", claimedJobs.Count);

                    foreach (var job in claimedJobs)
                    {
                        if (stoppingToken.IsCancellationRequested)
                        {
                            break;
                        }

                        await ProcessPrintJobAsync(apiClient, printerConfig, job, stoppingToken);
                    }
                }
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Yazdırma servisinde beklenmeyen bir hata oluştu. {Seconds} sn beklenecek.", ErrorBackoff.TotalSeconds);
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

        _logger.LogInformation("Print Worker durduruldu.");
    }

    private async Task ProcessPrintJobAsync(
        ILaravelApiClient apiClient,
        IPrinterConfigService printerConfig,
        PrintJobDto job,
        CancellationToken cancellationToken)
    {
        var notify = await printerConfig.GetNotificationsEnabledAsync(cancellationToken);

        // Laravel'den yeni yazdırma isteği düştüğünde masaüstü bildirimi
        if (notify)
        {
            _notifications.Show(
                "🖨️ Yazdırma isteği alındı",
                $"{DescribeJob(job)}\n{job.Title}",
                NotificationLevel.Info);
        }

        var rawText = job.Payload?.RawText ?? string.Empty;

        if (string.IsNullOrWhiteSpace(rawText))
        {
            _logger.LogWarning("Fiş metni boş veya geçersiz [#{JobId}]", job.Id);
            await ReportAsync(apiClient, job, "failed", "Fiş metni boş veya içerik oluşturulamadı", cancellationToken);

            if (notify)
            {
                _notifications.Show("❌ Fiş yazdırılamadı", $"{job.Title}\nFiş içeriği boş geldi.", NotificationLevel.Error);
            }

            return;
        }

        // Fiziki yazıcı seçimi CİHAZA aittir: hedef, sunucunun gönderdiği değerden
        // değil servis programının Yazıcılar ekranındaki eşleştirmesinden okunur.
        var config = await printerConfig.GetForTypeAsync(job.PrinterType, cancellationToken);

        if (!config.IsEnabled)
        {
            _logger.LogInformation(
                "'{Type}' yazıcısı bu cihazda kapalı; fiş #{JobId} atlanıyor.",
                job.PrinterType, job.Id);

            var disabledMessage = $"'{DTOs.PrinterConfigDto.LabelFor(config.Type)}' yazıcısı bu cihazda devre dışı bırakılmış.";
            await ReportAsync(apiClient, job, "failed", disabledMessage, cancellationToken);

            if (notify)
            {
                _notifications.Show("⚠️ Fiş yazdırılmadı", $"{job.Title}\n{disabledMessage}", NotificationLevel.Warning);
            }

            return;
        }

        var targetPrinter = string.IsNullOrWhiteSpace(config.PrinterName)
            ? job.TargetPrinter   // cihazda eşleştirme yoksa sunucunun önerisine düş
            : config.PrinterName;

        var codepage = string.IsNullOrWhiteSpace(config.Codepage) ? job.Codepage : config.Codepage;

        _logger.LogInformation(
            "Fiş yazdırma işleme alındı [#{JobId}]: {Title} (Tip: {Type}, Hedef: '{Printer}', {CharWidth} karakter, {Codepage}, Bildirim: {Notify})",
            job.Id, job.Title, job.PrinterType, targetPrinter, config.EffectiveCharWidth, codepage,
            notify ? "açık" : "kapalı");

        await ReportAsync(apiClient, job, "printing", null, cancellationToken);

        try
        {
            bool success = _printerService.SendStringToPrinter(targetPrinter, rawText, codepage, out string errorMessage);

            if (success)
            {
                _logger.LogInformation("Fiş yazdırma tamamlandı [#{JobId}]", job.Id);
                await ReportAsync(apiClient, job, "completed", null, cancellationToken);

                if (notify)
                {
                    _notifications.Show(
                        "✅ Fiş yazdırıldı",
                        $"{DescribeJob(job)}\n{job.Title}\nYazıcı: {targetPrinter}",
                        NotificationLevel.Success);
                }
            }
            else
            {
                _logger.LogError("Fiş yazdırma başarısız [#{JobId}]: {Error}", job.Id, errorMessage);
                await ReportAsync(apiClient, job, "failed", errorMessage, cancellationToken);

                if (notify)
                {
                    _notifications.Show(
                        "❌ Fiş yazdırılamadı",
                        $"{job.Title}\nYazıcı: {targetPrinter}\n{errorMessage}",
                        NotificationLevel.Error);
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fiş yazdırma sırasında istisna oluştu [#{JobId}]", job.Id);
            await ReportAsync(apiClient, job, "failed", ex.Message, cancellationToken);

            if (notify)
            {
                _notifications.Show(
                    "❌ Fiş yazdırılamadı",
                    $"{job.Title}\n{ex.Message}",
                    NotificationLevel.Error);
            }
        }
    }

    /// <summary>Bildirimde gösterilecek okunabilir fiş türü açıklaması.</summary>
    private static string DescribeJob(PrintJobDto job) => job.JobType switch
    {
        "kitchen_slip" => "Mutfak sipariş fişi",
        "check_slip" => "Hesap adisyon fişi",
        "test_slip" => "Yazıcı test fişi",
        _ => DTOs.PrinterConfigDto.LabelFor(job.PrinterType) + " fişi",
    };

    /// <summary>
    /// Durum bildirimi. Sunucuya ulaşılamazsa iş, sunucudaki claim zaman aşımıyla
    /// kuyruğa geri döner ve SINIRLI sayıda yeniden denenir; bu yüzden eskiden
    /// oluşan "sonsuz tekrar baskı" durumu artık mümkün değildir.
    /// </summary>
    private async Task ReportAsync(ILaravelApiClient apiClient, PrintJobDto job, string status, string? error, CancellationToken cancellationToken)
    {
        var reported = await apiClient.UpdatePrintJobStatusAsync(job.Id, status, error, cancellationToken);

        if (!reported)
        {
            _logger.LogWarning(
                "Fiş #{JobId} için '{Status}' durumu sunucuya bildirilemedi. "
                + "İş, sunucudaki zaman aşımı sonrası yeniden kuyruğa alınabilir.",
                job.Id, status);
        }
    }
}

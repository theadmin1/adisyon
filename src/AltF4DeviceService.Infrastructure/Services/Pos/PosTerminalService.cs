using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// Fiziki ÖKC terminaliyle konuşan servis.
/// Taşıma katmanını (TCP/seri) ve protokol kodlayıcısını birleştirir.
/// </summary>
public class PosTerminalService : IPosTerminalService
{
    private readonly IPosConfigService _configService;
    private readonly IPosMessageCodec _codec;
    private readonly ILogger<PosTerminalService> _logger;

    /// <summary>
    /// Terminal aynı anda tek işlem yapabilir; eşzamanlı istekleri sıraya sokar.
    /// </summary>
    private static readonly SemaphoreSlim TerminalLock = new(1, 1);

    public PosTerminalService(
        IPosConfigService configService,
        IPosMessageCodec codec,
        ILogger<PosTerminalService> logger)
    {
        _configService = configService;
        _codec = codec;
        _logger = logger;
    }

    public async Task<PosResultDto> ProcessSaleAsync(
        PosTransactionDto transaction,
        Func<string, Task>? onStatusChanged = null,
        CancellationToken cancellationToken = default)
    {
        var config = await _configService.GetAsync(cancellationToken);

        if (!config.IsEnabled)
        {
            return PosResultDto.Failure("ÖKC entegrasyonu bu cihazda kapalı.", "POS_DISABLED");
        }

        // Terminal tek kullanıcılı: ikinci bir işlem sıraya girer.
        await TerminalLock.WaitAsync(cancellationToken);

        IPosTransport? transport = null;

        try
        {
            transport = CreateTransport(config);

            _logger.LogInformation(
                "ÖKC işlemi başlatılıyor [#{TxId}]: {Amount:0.00} {Currency}, taksit={Installment}, hedef={Target}",
                transaction.Id, transaction.Amount, transaction.Currency, transaction.Installment, transport.Description);

            await transport.ConnectAsync(cancellationToken);

            var frame = _codec.EncodeSale(transaction);
            await transport.SendAsync(frame, cancellationToken);

            if (onStatusChanged != null)
            {
                await onStatusChanged("sent");
            }

            // Müşteri kartı okutup PIN girecek: uzun zaman aşımı gerekir.
            var timeout = TimeSpan.FromSeconds(Math.Max(30, config.TimeoutSeconds));

            if (onStatusChanged != null)
            {
                await onStatusChanged("awaiting_card");
            }

            var response = await transport.ReceiveAsync(timeout, cancellationToken);
            var result = _codec.DecodeResponse(response);

            if (result == null)
            {
                // Çerçeve tamamlanmadı: onay sayılmaz.
                return PosResultDto.Failure(
                    "Terminalden eksik yanıt alındı. İşlemin durumunu terminal ekranından doğrulayın.",
                    "INCOMPLETE_RESPONSE");
            }

            _logger.LogInformation(
                "ÖKC işlem sonucu [#{TxId}]: {Status} (onay kodu: {Code})",
                transaction.Id, result.Status, result.ApprovalCode ?? "-");

            return result;
        }
        catch (TimeoutException ex)
        {
            // KRİTİK: zaman aşımı "işlem olmadı" ANLAMINA GELMEZ. Terminal
            // işlemi almış ve banka onaylamış olabilir. Otomatik tekrar YOK.
            _logger.LogError(ex, "ÖKC işlemi zaman aşımına uğradı [#{TxId}]", transaction.Id);

            return PosResultDto.Failure(
                "Terminal zamanında yanıt vermedi. ÖDEME GEÇMİŞ OLABİLİR — "
                + "işlemi tekrarlamadan önce terminal ekranından kontrol edin.",
                "TIMEOUT");
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "ÖKC işlemi sırasında hata oluştu [#{TxId}]", transaction.Id);

            return PosResultDto.Failure($"ÖKC iletişim hatası: {ex.Message}", "TRANSPORT_ERROR");
        }
        finally
        {
            if (transport != null)
            {
                await transport.DisconnectAsync();
                transport.Dispose();
            }

            TerminalLock.Release();
        }
    }

    public async Task<(bool Success, string Message)> TestConnectionAsync(CancellationToken cancellationToken = default)
    {
        var config = await _configService.GetAsync(cancellationToken);

        IPosTransport? transport = null;

        try
        {
            transport = CreateTransport(config);
            await transport.ConnectAsync(cancellationToken);

            return (true, $"Bağlantı başarılı: {transport.Description} ({_codec.ProtocolName})");
        }
        catch (Exception ex)
        {
            return (false, $"Bağlantı kurulamadı: {ex.Message}");
        }
        finally
        {
            if (transport != null)
            {
                await transport.DisconnectAsync();
                transport.Dispose();
            }
        }
    }

    private IPosTransport CreateTransport(PosConfigDto config)
    {
        return config.ConnectionType?.ToLowerInvariant() switch
        {
            "serial" => new SerialPosTransport(config.SerialPort, config.BaudRate, _logger),
            "tcp" => new TcpPosTransport(config.Host, config.Port, _logger),
            _ => throw new InvalidOperationException(
                $"Desteklenmeyen ÖKC bağlantı tipi: '{config.ConnectionType}'. "
                + "Yönetim panelinden TCP veya Seri Port seçin."),
        };
    }
}

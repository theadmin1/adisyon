using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Workers;

/// <summary>
/// Laravel sunucusundaki fiş yazdırma kuyruğunu (Print Spooler) sürekli dinleyen,
/// talepleri alıp fiziki termal yazıcılara ileten ve durum güncellemelerini bildiren C# Arka Plan Servisi.
/// </summary>
public class PrintBackgroundWorker : BackgroundService
{
    private readonly ILaravelApiClient _laravelApiClient;
    private readonly IPrinterService _printerService;
    private readonly ILogger<PrintBackgroundWorker> _logger;

    public PrintBackgroundWorker(
        ILaravelApiClient laravelApiClient,
        IPrinterService printerService,
        ILogger<PrintBackgroundWorker> logger)
    {
        _laravelApiClient = laravelApiClient;
        _printerService = printerService;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("🖨️ AltF4 Termal Fiş Yazdırma Arka Plan Servisi (Print Worker) Başlatıldı.");

        // System.Text.Encoding.CodePages sağlayıcısını kaydet (Türkçe karakter seti desteği için)
        try
        {
            System.Text.Encoding.RegisterProvider(System.Text.CodePagesEncodingProvider.Instance);
        }
        catch { }

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                var pendingJobs = await _laravelApiClient.GetPendingPrintJobsAsync(stoppingToken);

                if (pendingJobs != null && pendingJobs.Count > 0)
                {
                    _logger.LogInformation("📥 {Count} adet bekleyen fiş yazdırma talebi alındı.", pendingJobs.Count);

                    foreach (var job in pendingJobs)
                    {
                        await ProcessPrintJobAsync(job, stoppingToken);
                    }
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Yazdırma servisinde beklenmeyen bir hata oluştu.");
            }

            // 2 saniye bekle
            await Task.Delay(2000, stoppingToken);
        }
    }

    private async Task ProcessPrintJobAsync(PrintJobDto job, CancellationToken cancellationToken)
    {
        _logger.LogInformation("⚙️ Fiş Yazdırma İşleme Alındı [# {JobId}]: {Title} (Hedef: {Printer})", job.Id, job.Title, job.TargetPrinter);

        // 1. Durum: Talebi Aldı (received)
        await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "received", null, cancellationToken);

        // 2. Durum: Yazdırılıyor (printing)
        await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "printing", null, cancellationToken);

        string rawText = job.Payload?.RawText ?? string.Empty;
        if (string.IsNullOrWhiteSpace(rawText))
        {
            _logger.LogWarning("⚠️ Fiş metni boş veya geçersiz [# {JobId}]", job.Id);
            await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "failed", "Fiş metni boş veya içerik oluşturulamadı", cancellationToken);
            return;
        }

        string printerName = job.TargetPrinter;
        if (string.IsNullOrWhiteSpace(printerName) || printerName == "Default POS Printer")
        {
            printerName = "Generic / Text Only";
        }

        try
        {
            _logger.LogInformation("🖨️ Yazıcıya Gönderiliyor: '{Printer}'", printerName);

            bool success = _printerService.SendStringToPrinter(printerName, rawText, out string errorMessage);

            if (success)
            {
                _logger.LogInformation("✅ Fiş Yazdırma Tamamlandı [# {JobId}]", job.Id);
                await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "completed", null, cancellationToken);
            }
            else
            {
                _logger.LogError("❌ Fiş Yazdırma Başarısız [# {JobId}]: {Error}", job.Id, errorMessage);
                await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "failed", errorMessage, cancellationToken);
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "❌ Fiş Yazdırma Sırasında İstisna Oluştu [# {JobId}]", job.Id);
            await _laravelApiClient.UpdatePrintJobStatusAsync(job.Id, "failed", ex.Message, cancellationToken);
        }
    }
}

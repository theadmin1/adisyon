using AltF4DeviceService.Domain.DTOs;

namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// Laravel Web Adisyon API'si ile haberlesmek icin kullanilan HTTP client arayuzu.
/// </summary>
public interface ILaravelApiClient
{
    /// <summary>
    /// Cihazin gecerli lisans durumunu uzak Laravel API sunucusundan sorgular.
    /// </summary>
    Task<LicenseValidationResultDto> ValidateLicenseAsync(
        string licenseKey,
        string deviceUuid,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Sube bilgilerini Laravel API'den gunceller.
    /// </summary>
    Task<bool> SyncBranchAccountAsync(int branchId, CancellationToken cancellationToken = default);

    /// <summary>
    /// Cihaz canlilik (heartbeat) sinyalini Laravel sunucusuna iletir.
    /// </summary>
    Task<bool> SendHeartbeatAsync(string deviceUuid, CancellationToken cancellationToken = default);

    /// <summary>
    /// Laravel API'sinden bekleyen fis yazdirma gorevlerini ceker.
    /// </summary>
    Task<List<PrintJobDto>> GetPendingPrintJobsAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Tek bir yazdirma isini bu cihaza kilitler.
    /// </summary>
    Task<bool> ClaimPrintJobAsync(long jobId, CancellationToken cancellationToken = default);

    /// <summary>
    /// Cihazdaki yazici yapilandirmasini sunucuya bildirir.
    /// </summary>
    Task<bool> SyncPrinterAsync(
        string printerType,
        string printerName,
        int paperWidth,
        int charWidth,
        string codepage,
        bool isEnabled,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Fis yazdirma isinin durumunu Laravel API'ye bildirir.
    /// </summary>
    Task<bool> UpdatePrintJobStatusAsync(
        long jobId,
        string status,
        string? errorMessage = null,
        CancellationToken cancellationToken = default);
}

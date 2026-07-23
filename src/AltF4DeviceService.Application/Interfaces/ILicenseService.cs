using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Lisans yönetim işlemleri için arayüz.
/// </summary>
public interface ILicenseService
{
    /// <summary>
    /// Cihaza atanmış lisans bilgilerini getirir (yoksa varsayılan seed ile oluşturur).
    /// </summary>
    Task<LicenseDto> GetOrCreateLicenseAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Lisans durumunu günceller.
    /// </summary>
    Task<bool> VerifyAndUpdateLicenseAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Lisans anahtarını günceller.
    /// </summary>
    Task<LicenseDto> UpdateLicenseKeyAsync(string licenseKey, CancellationToken cancellationToken = default);
}

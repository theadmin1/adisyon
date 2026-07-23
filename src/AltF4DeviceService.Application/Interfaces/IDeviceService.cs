using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Cihaz kimliği ve yapılandırma yönetimi için uygulama servis arayüzü.
/// </summary>
public interface IDeviceService
{
    /// <summary>
    /// Cihaz kimlik bilgilerini getirir veya yoksa ilk çalışmada otomatik oluşturur.
    /// </summary>
    Task<DeviceDto> GetOrCreateDeviceIdentityAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Cihazın son sinyal/aktiflik zamanını günceller ve sunucu canlılık yanıtını döner.
    /// </summary>
    Task<bool> UpdateLastSeenAsync(CancellationToken cancellationToken = default);
}

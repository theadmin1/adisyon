using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Sağlık ve çalışma durumu kontrol servisi arayüzü.
/// </summary>
public interface IHealthService
{
    /// <summary>
    /// Servisin güncel sağlık durumu metriklerini hesaplar.
    /// </summary>
    Task<HealthStatusDto> GetHealthStatusAsync(CancellationToken cancellationToken = default);
}

using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// ÖKC terminalinin cihaz üzerindeki yapılandırmasını yerel SQLite'ta yönetir.
/// </summary>
public interface IPosConfigService
{
    Task<PosConfigDto> GetAsync(CancellationToken cancellationToken = default);

    Task SaveAsync(PosConfigDto config, CancellationToken cancellationToken = default);
}

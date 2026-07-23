using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// DeviceService implementasyonu. İlk çalıştırmada benzersiz Device UUID oluşturur ve saklar.
/// </summary>
public class DeviceService : IDeviceService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly IOptions<ServiceOptions> _options;
    private readonly ILogger<DeviceService> _logger;

    public DeviceService(
        IUnitOfWork unitOfWork,
        IOptions<ServiceOptions> options,
        ILogger<DeviceService> logger)
    {
        _unitOfWork = unitOfWork;
        _options = options;
        _logger = logger;
    }

    public async Task<DeviceDto> GetOrCreateDeviceIdentityAsync(CancellationToken cancellationToken = default)
    {        var devices = await _unitOfWork.Devices.GetAllAsync(cancellationToken);
        var device = devices.FirstOrDefault();

        if (device == null)
        {
            var deviceUuid = Guid.NewGuid().ToString("D").ToUpperInvariant();
            var deviceCode = !string.IsNullOrWhiteSpace(_options.Value.DeviceName) 
                ? _options.Value.DeviceName 
                : "KASA-01";

            _logger.LogInformation("İlk çalışma tespit edildi. Yeni benzersiz Device UUID üretiliyor: {DeviceUuid}, Kod: {DeviceCode}", deviceUuid, deviceCode);

            device = new Device
            {
                DeviceUuid = deviceUuid,
                DeviceCode = deviceCode,
                DeviceName = Environment.MachineName,
                IsActive = true,
                CreatedAt = DateTime.UtcNow,
                LastSeenAt = DateTime.UtcNow
            };

            await _unitOfWork.Devices.AddAsync(device, cancellationToken);
            await _unitOfWork.SaveChangesAsync(cancellationToken);
        }

        return MapToDto(device);
    }

    public async Task UpdateLastSeenAsync(CancellationToken cancellationToken = default)
    {
        var devices = await _unitOfWork.Devices.GetAllAsync(cancellationToken);
        var device = devices.FirstOrDefault();
        if (device != null)
        {
            device.LastSeenAt = DateTime.UtcNow;
            _unitOfWork.Devices.Update(device);
            await _unitOfWork.SaveChangesAsync(cancellationToken);
        }
    }

    private static DeviceDto MapToDto(Device entity)
    {
        return new DeviceDto
        {
            Id = entity.Id,
            DeviceUuid = entity.DeviceUuid,
            DeviceCode = entity.DeviceCode,
            DeviceName = entity.DeviceName,
            IsActive = entity.IsActive,
            CreatedAt = entity.CreatedAt,
            LastSeenAt = entity.LastSeenAt
        };
    }
}

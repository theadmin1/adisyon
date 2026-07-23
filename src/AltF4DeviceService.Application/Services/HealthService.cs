using System.Diagnostics;
using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// HealthService implementasyonu. Servisin çalışma süresini ve veritabanı bağlantı durumunu kontrol eder.
/// </summary>
public class HealthService : IHealthService
{
    private static readonly DateTime ServiceStartTime = DateTime.UtcNow;
    private readonly IUnitOfWork _unitOfWork;
    private readonly ILogger<HealthService> _logger;

    public HealthService(IUnitOfWork unitOfWork, ILogger<HealthService> logger)
    {
        _unitOfWork = unitOfWork;
        _logger = logger;
    }

    public async Task<HealthStatusDto> GetHealthStatusAsync(CancellationToken cancellationToken = default)
    {
        bool isDbConnected = false;
        try
        {
            // Basit bir sorgu ile DB bağlantısını test et
            await _unitOfWork.Devices.GetAllAsync(cancellationToken);
            isDbConnected = true;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Health check sırasında veritabanı bağlantı hatası oluştu.");
            isDbConnected = false;
        }

        var uptime = DateTime.UtcNow - ServiceStartTime;

        return new HealthStatusDto
        {
            Status = isDbConnected ? "Healthy" : "Degraded",
            ServiceName = "AltF4 Device Service",
            Version = "1.0.0",
            DatabaseConnected = isDbConnected,
            Uptime = uptime,
            ServerTime = DateTime.UtcNow
        };
    }
}

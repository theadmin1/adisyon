namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// /health endpoint'i üzerinden servis ve veritabanı durumunu dönen DTO.
/// </summary>
public class HealthStatusDto
{
    public string Status { get; set; } = "Healthy";
    public string ServiceName { get; set; } = "AltF4 Device Service";
    public string Version { get; set; } = "1.0.0";
    public bool DatabaseConnected { get; set; }
    public TimeSpan Uptime { get; set; }
    public DateTime ServerTime { get; set; } = DateTime.UtcNow;
}

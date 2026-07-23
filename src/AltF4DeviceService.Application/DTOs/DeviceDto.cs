namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// Cihaz bilgilerini API katmanına sunan DTO.
/// </summary>
public class DeviceDto
{
    public int Id { get; set; }
    public string DeviceUuid { get; set; } = string.Empty;
    public string DeviceCode { get; set; } = string.Empty;
    public string DeviceName { get; set; } = string.Empty;
    public bool IsActive { get; set; }
    public DateTime CreatedAt { get; set; }
    public DateTime? LastSeenAt { get; set; }
}

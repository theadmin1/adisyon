namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// Lisans bilgilerini API yanıtı olarak dönen DTO.
/// </summary>
public class LicenseDto
{
    public int Id { get; set; }
    public string LicenseKey { get; set; } = string.Empty;
    public string DeviceToken { get; set; } = string.Empty;
    public string Status { get; set; } = string.Empty;
    public DateTime? ExpiresAt { get; set; }
    public DateTime? LastCheck { get; set; }
    public DateTime? LastSync { get; set; }
}

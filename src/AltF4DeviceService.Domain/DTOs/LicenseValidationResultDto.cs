using AltF4DeviceService.Domain.Enums;

namespace AltF4DeviceService.Domain.DTOs;

/// <summary>
/// Laravel lisans dogrulama sonucunun uygulama ici tasinabilir hali.
/// </summary>
public class LicenseValidationResultDto
{
    public bool IsValid { get; set; }
    public LicenseStatus Status { get; set; } = LicenseStatus.Unlicensed;
    public string DeviceToken { get; set; } = string.Empty;
    public DateTime? ExpiresAt { get; set; }
    public bool UsedLocalFallback { get; set; }
    public string FailureReason { get; set; } = string.Empty;
}

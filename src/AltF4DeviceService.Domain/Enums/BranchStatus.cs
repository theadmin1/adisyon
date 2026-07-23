namespace AltF4DeviceService.Domain.Enums;

/// <summary>
/// Şube hesabının durumunu belirten enum.
/// </summary>
public enum BranchStatus
{
    /// <summary>
    /// Şube pasif durumda.
    /// </summary>
    Inactive = 0,

    /// <summary>
    /// Şube aktif durumda.
    /// </summary>
    Active = 1,

    /// <summary>
    /// Şube hesabı askıya alınmış.
    /// </summary>
    Suspended = 2
}

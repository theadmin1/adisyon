namespace AltF4DeviceService.Domain.Enums;

/// <summary>
/// Cihazın lisans durumunu belirten enum.
/// </summary>
public enum LicenseStatus
{
    /// <summary>
    /// Lisans henüz aktifleştirilmemiş veya tanım yapılmamış.
    /// </summary>
    Unlicensed = 0,

    /// <summary>
    /// Lisans aktif ve geçerli.
    /// </summary>
    Active = 1,

    /// <summary>
    /// Lisans süresi dolmuş.
    /// </summary>
    Expired = 2,

    /// <summary>
    /// Lisans askıya alınmış veya engellenmiş.
    /// </summary>
    Suspended = 3
}

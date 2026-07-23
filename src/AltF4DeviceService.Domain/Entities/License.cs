using AltF4DeviceService.Domain.Enums;

namespace AltF4DeviceService.Domain.Entities;

/// <summary>
/// Cihaza tanımlı lisans bilgilerini SQLite üzerinde tutan entity.
/// </summary>
public class License
{
    /// <summary>
    /// Birincil anahtar.
    /// </summary>
    public int Id { get; set; }

    /// <summary>
    /// Lisans anahtarı (license_key).
    /// </summary>
    public string LicenseKey { get; set; } = string.Empty;

    /// <summary>
    /// Laravel API tarafınca doğrulanan cihaz tokenı (device_token).
    /// </summary>
    public string DeviceToken { get; set; } = string.Empty;

    /// <summary>
    /// Lisans durumu (Active, Expired, Suspended, Unlicensed).
    /// </summary>
    public LicenseStatus Status { get; set; } = LicenseStatus.Unlicensed;

    /// <summary>
    /// Lisansın bitiş tarihi (expires_at). Null ise süresiz/tanımsızdır.
    /// </summary>
    public DateTime? ExpiresAt { get; set; }

    /// <summary>
    /// Son lisans kontrol tarihi (last_check).
    /// </summary>
    public DateTime? LastCheck { get; set; }

    /// <summary>
    /// Son senkronizasyon tarihi (last_sync).
    /// </summary>
    public DateTime? LastSync { get; set; }

    /// <summary>
    /// Kayıt oluşturulma tarihi.
    /// </summary>
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    /// <summary>
    /// Son güncelleme tarihi.
    /// </summary>
    public DateTime? UpdatedAt { get; set; }
}

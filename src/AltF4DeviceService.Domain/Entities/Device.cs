namespace AltF4DeviceService.Domain.Entities;

/// <summary>
/// Restoran bilgisayarının / POS cihazının benzersiz kimlik ve durum bilgilerini tutan entity.
/// </summary>
public class Device
{
    /// <summary>
    /// Birincil anahtar.
    /// </summary>
    public int Id { get; set; }

    /// <summary>
    /// Cihaza özel otomatik üretilen benzersiz UUID (GUID).
    /// </summary>
    public string DeviceUuid { get; set; } = string.Empty;

    /// <summary>
    /// Cihaz kodu (örneğin KASA-01).
    /// </summary>
    public string DeviceCode { get; set; } = string.Empty;

    /// <summary>
    /// Cihaz adı veya tanımlayıcı bilgisayar adı.
    /// </summary>
    public string DeviceName { get; set; } = string.Empty;

    /// <summary>
    /// Cihazın aktiflik durumu.
    /// </summary>
    public bool IsActive { get; set; } = true;

    /// <summary>
    /// Cihazın sisteme ilk kayıt tarihi.
    /// </summary>
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    /// <summary>
    /// Son aktif olduğu / sinyal gönderdiği tarih.
    /// </summary>
    public DateTime? LastSeenAt { get; set; }
}

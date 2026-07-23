namespace AltF4DeviceService.Domain.Entities;

/// <summary>
/// Servise ait dinamik anahtar-değer ayarlarını SQLite üzerinde saklayan entity.
/// </summary>
public class Setting
{
    /// <summary>
    /// Birincil anahtar.
    /// </summary>
    public int Id { get; set; }

    /// <summary>
    /// Ayar anahtarı (örn. ApiUrl, HeartbeatIntervalSeconds).
    /// </summary>
    public string Key { get; set; } = string.Empty;

    /// <summary>
    /// Ayar değeri.
    /// </summary>
    public string Value { get; set; } = string.Empty;

    /// <summary>
    /// Ayar açıklaması.
    /// </summary>
    public string Description { get; set; } = string.Empty;

    /// <summary>
    /// Son güncelleme tarihi.
    /// </summary>
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;
}

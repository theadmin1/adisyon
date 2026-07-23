using AltF4DeviceService.Domain.Enums;

namespace AltF4DeviceService.Domain.Entities;

/// <summary>
/// Cihazın bağlı olduğu restoran ve şube bilgilerini SQLite üzerinde tutan entity.
/// </summary>
public class BranchAccount
{
    /// <summary>
    /// Birincil anahtar.
    /// </summary>
    public int Id { get; set; }

    /// <summary>
    /// Laravel sistemindeki şube ID (branch_id).
    /// </summary>
    public int BranchId { get; set; }

    /// <summary>
    /// Laravel sistemindeki restoran ID (restaurant_id).
    /// </summary>
    public int RestaurantId { get; set; }

    /// <summary>
    /// Şube adı (branch_name).
    /// </summary>
    public string BranchName { get; set; } = string.Empty;

    /// <summary>
    /// Şube yöneticisi / hesap e-posta adresi (email).
    /// </summary>
    public string Email { get; set; } = string.Empty;

    /// <summary>
    /// Şube hesabına ait erişim tokenı (device_token).
    /// </summary>
    public string DeviceToken { get; set; } = string.Empty;

    /// <summary>
    /// Şube hesap durumu (Active, Inactive, Suspended).
    /// </summary>
    public BranchStatus Status { get; set; } = BranchStatus.Active;

    /// <summary>
    /// Kayıt oluşturulma tarihi (created_at).
    /// </summary>
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    /// <summary>
    /// Son güncelleme tarihi (updated_at).
    /// </summary>
    public DateTime? UpdatedAt { get; set; }
}

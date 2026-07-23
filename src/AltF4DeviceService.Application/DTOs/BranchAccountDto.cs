namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// Şube hesabı verilerini API yanıtı olarak temsil eden DTO.
/// </summary>
public class BranchAccountDto
{
    public int Id { get; set; }
    public int BranchId { get; set; }
    public int RestaurantId { get; set; }
    public string BranchName { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string DeviceToken { get; set; } = string.Empty;
    public string Status { get; set; } = string.Empty;
    public DateTime CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}

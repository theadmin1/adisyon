using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Şube hesabı yönetimi arayüzü.
/// </summary>
public interface IBranchService
{
    /// <summary>
    /// SQLite içerisindeki tek şube hesabını getirir veya yoksa ilk varsayılan şube bilgisini tanımlar.
    /// </summary>
    Task<BranchAccountDto> GetOrCreateBranchAccountAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Şube hesabını Laravel API verileri ile senkronize eder.
    /// </summary>
    Task<bool> SyncBranchAccountAsync(CancellationToken cancellationToken = default);
}

using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Enums;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// Şube hesabı yönetim servisi.
/// </summary>
public class BranchService : IBranchService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly ILaravelApiClient _laravelApiClient;
    private readonly ILogger<BranchService> _logger;

    public BranchService(
        IUnitOfWork unitOfWork,
        ILaravelApiClient laravelApiClient,
        ILogger<BranchService> logger)
    {
        _unitOfWork = unitOfWork;
        _laravelApiClient = laravelApiClient;
        _logger = logger;
    }

    public async Task<BranchAccountDto> GetOrCreateBranchAccountAsync(CancellationToken cancellationToken = default)
    {
        var accounts = await _unitOfWork.BranchAccounts.GetAllAsync(cancellationToken);
        var branch = accounts.FirstOrDefault();

        if (branch == null)
        {
            _logger.LogInformation("SQLite veritabanında şube hesabı bulunamadı. Varsayılan şube hesabı oluşturuluyor.");
            branch = new BranchAccount
            {
                BranchId = 1,
                RestaurantId = 101,
                BranchName = "Merkez Restoran Şubesi",
                Email = "merkez@restoran.com",
                DeviceToken = Guid.NewGuid().ToString("N"),
                Status = BranchStatus.Active,
                CreatedAt = DateTime.UtcNow
            };

            await _unitOfWork.BranchAccounts.AddAsync(branch, cancellationToken);
            await _unitOfWork.SaveChangesAsync(cancellationToken);
        }

        return MapToDto(branch);
    }

    public async Task<bool> SyncBranchAccountAsync(CancellationToken cancellationToken = default)
    {
        var accounts = await _unitOfWork.BranchAccounts.GetAllAsync(cancellationToken);
        var branch = accounts.FirstOrDefault();

        if (branch == null)
        {
            await GetOrCreateBranchAccountAsync(cancellationToken);
            return true;
        }

        _logger.LogInformation("Laravel API üzerinden Şube ID {BranchId} için senkronizasyon yapılıyor.", branch.BranchId);
        var result = await _laravelApiClient.SyncBranchAccountAsync(branch.BranchId, cancellationToken);

        branch.UpdatedAt = DateTime.UtcNow;
        _unitOfWork.BranchAccounts.Update(branch);
        await _unitOfWork.SaveChangesAsync(cancellationToken);

        return result;
    }

    private static BranchAccountDto MapToDto(BranchAccount entity)
    {
        return new BranchAccountDto
        {
            Id = entity.Id,
            BranchId = entity.BranchId,
            RestaurantId = entity.RestaurantId,
            BranchName = entity.BranchName,
            Email = entity.Email,
            DeviceToken = entity.DeviceToken,
            Status = entity.Status.ToString(),
            CreatedAt = entity.CreatedAt,
            UpdatedAt = entity.UpdatedAt
        };
    }
}

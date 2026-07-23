using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Enums;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// Lisans yönetim servis implementasyonu.
/// </summary>
public class LicenseService : ILicenseService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly ILaravelApiClient _laravelApiClient;
    private readonly ILogger<LicenseService> _logger;

    public LicenseService(
        IUnitOfWork unitOfWork,
        ILaravelApiClient laravelApiClient,
        ILogger<LicenseService> logger)
    {
        _unitOfWork = unitOfWork;
        _laravelApiClient = laravelApiClient;
        _logger = logger;
    }

    public async Task<LicenseDto> GetOrCreateLicenseAsync(CancellationToken cancellationToken = default)
    {
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
        {
            _logger.LogInformation("SQLite veritabanında lisans kaydı bulunamadı, varsayılan lisans taslağı oluşturuluyor.");
            license = new License
            {
                LicenseKey = "ALTF4-8899-7711-XYZ9",
                DeviceToken = Guid.NewGuid().ToString("N"),
                Status = LicenseStatus.Active,
                ExpiresAt = DateTime.UtcNow.AddDays(365),
                LastCheck = DateTime.UtcNow,
                LastSync = DateTime.UtcNow,
                CreatedAt = DateTime.UtcNow
            };

            await _unitOfWork.Licenses.AddAsync(license, cancellationToken);
            await _unitOfWork.SaveChangesAsync(cancellationToken);
        }

        return MapToDto(license);
    }

    public async Task<bool> VerifyAndUpdateLicenseAsync(CancellationToken cancellationToken = default)
    {
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
        {
            await GetOrCreateLicenseAsync(cancellationToken);
            return true;
        }

        _logger.LogInformation("Laravel API üzerinden lisans doğrulaması tetiklendi. Endpoint: verify, Key: {Key}", license.LicenseKey);
        var isValid = await _laravelApiClient.ValidateLicenseAsync(license.LicenseKey, license.DeviceToken, cancellationToken);

        license.LastCheck = DateTime.UtcNow;
        license.Status = isValid ? LicenseStatus.Active : LicenseStatus.Expired;
        license.UpdatedAt = DateTime.UtcNow;

        _unitOfWork.Licenses.Update(license);
        await _unitOfWork.SaveChangesAsync(cancellationToken);

        return isValid;
    }

    public async Task<bool> IsLocalLicenseValidAsync(CancellationToken cancellationToken = default)
    {
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
            return false;

        if (license.Status != LicenseStatus.Active)
            return false;

        if (license.ExpiresAt.HasValue && license.ExpiresAt.Value < DateTime.UtcNow)
        {
            _logger.LogWarning("Yerel SQLite veritabanındaki lisans süresi dolmuş! Son Kullanma: {ExpiresAt}", license.ExpiresAt);
            return false;
        }

        return true;
    }

    public async Task<LicenseDto> UpdateLicenseKeyAsync(string licenseKey, CancellationToken cancellationToken = default)
    {
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
        {
            license = new License
            {
                LicenseKey = licenseKey,
                DeviceToken = Guid.NewGuid().ToString("N"),
                Status = LicenseStatus.Active,
                ExpiresAt = DateTime.UtcNow.AddDays(365),
                LastCheck = DateTime.UtcNow,
                LastSync = DateTime.UtcNow
            };
            await _unitOfWork.Licenses.AddAsync(license, cancellationToken);
        }
        else
        {
            license.LicenseKey = licenseKey;
            license.LastCheck = DateTime.UtcNow;
            license.UpdatedAt = DateTime.UtcNow;
            _unitOfWork.Licenses.Update(license);
        }

        await _unitOfWork.SaveChangesAsync(cancellationToken);
        _logger.LogInformation("Lisans anahtarı başarıyla güncellendi: {LicenseKey}", licenseKey);
        return MapToDto(license);
    }

    private static LicenseDto MapToDto(License entity)
    {
        return new LicenseDto
        {
            Id = entity.Id,
            LicenseKey = entity.LicenseKey,
            DeviceToken = entity.DeviceToken,
            Status = entity.Status.ToString(),
            ExpiresAt = entity.ExpiresAt,
            LastCheck = entity.LastCheck,
            LastSync = entity.LastSync
        };
    }
}

using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Enums;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// Lisans yonetim servis implementasyonu.
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
            _logger.LogInformation("SQLite veritabaninda lisans kaydi bulunamadi; dogrulanmamis lisans taslagi olusturuluyor.");
            license = new License
            {
                LicenseKey = string.Empty,
                DeviceToken = string.Empty,
                Status = LicenseStatus.Unlicensed,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow,
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
            return false;
        }

        if (string.IsNullOrWhiteSpace(license.LicenseKey))
        {
            license.Status = LicenseStatus.Unlicensed;
            license.LastCheck = DateTime.UtcNow;
            license.UpdatedAt = DateTime.UtcNow;
            _unitOfWork.Licenses.Update(license);
            await _unitOfWork.SaveChangesAsync(cancellationToken);

            _logger.LogWarning("Lisans dogrulamasi atlandi; kayitli lisans anahtari bos.");
            return false;
        }

        var devices = await _unitOfWork.Devices.GetAllAsync(cancellationToken);
        var deviceUuid = devices.FirstOrDefault()?.DeviceUuid;
        if (string.IsNullOrWhiteSpace(deviceUuid) || !Guid.TryParse(deviceUuid, out _))
        {
            _logger.LogError("Lisans dogrulamasi icin gecerli cihaz UUID'si bulunamadi.");
            return false;
        }

        _logger.LogInformation("Laravel API uzerinden lisans dogrulamasi tetiklendi. Endpoint: verify.");
        var validation = await _laravelApiClient.ValidateLicenseAsync(license.LicenseKey, deviceUuid, cancellationToken);

        license.LastCheck = DateTime.UtcNow;
        license.Status = validation.Status;
        license.UpdatedAt = DateTime.UtcNow;

        if (!string.IsNullOrWhiteSpace(validation.DeviceToken))
        {
            license.DeviceToken = validation.DeviceToken;
        }

        if (validation.ExpiresAt.HasValue)
        {
            license.ExpiresAt = validation.ExpiresAt.Value;
            license.LastSync = DateTime.UtcNow;
        }
        else if (!validation.IsValid && !validation.UsedLocalFallback)
        {
            license.ExpiresAt = null;
        }

        _unitOfWork.Licenses.Update(license);
        await _unitOfWork.SaveChangesAsync(cancellationToken);

        return validation.IsValid;
    }

    public async Task<bool> IsLocalLicenseValidAsync(CancellationToken cancellationToken = default)
    {
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
        {
            return false;
        }

        if (license.Status != LicenseStatus.Active)
        {
            return false;
        }

        if (license.ExpiresAt.HasValue && license.ExpiresAt.Value < DateTime.UtcNow)
        {
            _logger.LogWarning("Yerel SQLite veritabanindaki lisans suresi dolmus. Son kullanma: {ExpiresAt}", license.ExpiresAt);
            return false;
        }

        return true;
    }

    public async Task<LicenseDto> UpdateLicenseKeyAsync(string licenseKey, CancellationToken cancellationToken = default)
    {
        var normalizedKey = (licenseKey ?? string.Empty).Trim();
        var licenses = await _unitOfWork.Licenses.GetAllAsync(cancellationToken);
        var license = licenses.FirstOrDefault();

        if (license == null)
        {
            license = new License
            {
                LicenseKey = normalizedKey,
                DeviceToken = string.Empty,
                Status = LicenseStatus.Unlicensed,
                ExpiresAt = null,
                LastCheck = null,
                LastSync = null,
                CreatedAt = DateTime.UtcNow,
                UpdatedAt = DateTime.UtcNow,
            };

            await _unitOfWork.Licenses.AddAsync(license, cancellationToken);
        }
        else
        {
            license.LicenseKey = normalizedKey;
            license.DeviceToken = string.Empty;
            license.Status = LicenseStatus.Unlicensed;
            license.ExpiresAt = null;
            license.LastCheck = null;
            license.LastSync = null;
            license.UpdatedAt = DateTime.UtcNow;
            _unitOfWork.Licenses.Update(license);
        }

        await _unitOfWork.SaveChangesAsync(cancellationToken);
        _logger.LogInformation("Lisans anahtari kaydedildi. Yeni anahtar dogrulanana kadar durum beklemede tutuluyor.");

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
            LastSync = entity.LastSync,
        };
    }
}

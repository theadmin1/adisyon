using System.Text.Json;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// Sistem ayarları yönetim servisi implementasyonu.
/// </summary>
public class SettingService : ISettingService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly IOptions<ServiceOptions> _serviceOptions;
    private readonly ILogger<SettingService> _logger;

    public SettingService(
        IUnitOfWork unitOfWork,
        IOptions<ServiceOptions> serviceOptions,
        ILogger<SettingService> logger)
    {
        _unitOfWork = unitOfWork;
        _serviceOptions = serviceOptions;
        _logger = logger;
    }

    public async Task<List<Setting>> GetAllSettingsAsync(CancellationToken cancellationToken = default)
    {
        var settings = await _unitOfWork.Settings.GetAllAsync(cancellationToken);
        return settings.ToList();
    }

    public async Task<string> GetSettingValueAsync(string key, string defaultValue = "", CancellationToken cancellationToken = default)
    {
        var settings = await _unitOfWork.Settings.GetAllAsync(cancellationToken);
        var setting = settings.FirstOrDefault(s => s.Key.Equals(key, StringComparison.OrdinalIgnoreCase));
        return setting?.Value ?? defaultValue;
    }

    public async Task SaveSettingAsync(string key, string value, string description = "", CancellationToken cancellationToken = default)
    {
        var settings = await _unitOfWork.Settings.GetAllAsync(cancellationToken);
        var setting = settings.FirstOrDefault(s => s.Key.Equals(key, StringComparison.OrdinalIgnoreCase));

        if (setting == null)
        {
            setting = new Setting
            {
                Key = key,
                Value = value,
                Description = description,
                UpdatedAt = DateTime.UtcNow
            };
            await _unitOfWork.Settings.AddAsync(setting, cancellationToken);
        }
        else
        {
            setting.Value = value;
            if (!string.IsNullOrWhiteSpace(description))
                setting.Description = description;
            setting.UpdatedAt = DateTime.UtcNow;
            _unitOfWork.Settings.Update(setting);
        }

        await _unitOfWork.SaveChangesAsync(cancellationToken);
        _logger.LogInformation("Ayar güncellendi: {Key} = {Value}", key, value);
    }

    public async Task SaveBrowserRestrictionsAsync(BrowserRestrictionOptions restrictions, CancellationToken cancellationToken = default)
    {
        var json = JsonSerializer.Serialize(restrictions);
        await SaveSettingAsync("BrowserRestrictions", json, "Dahili Chromium tarayıcı güvenlik ve kısıtlama ayarları", cancellationToken);

        // In-memory option güncellemesi
        _serviceOptions.Value.BrowserRestrictions = restrictions;
    }

    public async Task<BrowserRestrictionOptions> GetBrowserRestrictionsAsync(CancellationToken cancellationToken = default)
    {
        var json = await GetSettingValueAsync("BrowserRestrictions", string.Empty, cancellationToken);
        if (string.IsNullOrWhiteSpace(json))
        {
            return _serviceOptions.Value.BrowserRestrictions ?? new BrowserRestrictionOptions();
        }

        try
        {
            var options = JsonSerializer.Deserialize<BrowserRestrictionOptions>(json);
            if (options != null)
            {
                _serviceOptions.Value.BrowserRestrictions = options;
                return options;
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "BrowserRestrictions JSON çözümlenirken hata oluştu.");
        }

        return _serviceOptions.Value.BrowserRestrictions ?? new BrowserRestrictionOptions();
    }
}

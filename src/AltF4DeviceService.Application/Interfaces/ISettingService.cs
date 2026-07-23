using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Entities;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Sistem ayarları yönetim servisi arayüzü.
/// </summary>
public interface ISettingService
{
    Task<List<Setting>> GetAllSettingsAsync(CancellationToken cancellationToken = default);
    Task<string> GetSettingValueAsync(string key, string defaultValue = "", CancellationToken cancellationToken = default);
    Task SaveSettingAsync(string key, string value, string description = "", CancellationToken cancellationToken = default);
    Task SaveBrowserRestrictionsAsync(BrowserRestrictionOptions restrictions, CancellationToken cancellationToken = default);
    Task<BrowserRestrictionOptions> GetBrowserRestrictionsAsync(CancellationToken cancellationToken = default);
}

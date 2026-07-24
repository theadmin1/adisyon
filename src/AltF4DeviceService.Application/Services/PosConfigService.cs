using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// ÖKC yapılandırmasını yerel ayar tablosunda saklar (Pos.* anahtarları).
/// </summary>
public class PosConfigService : IPosConfigService
{
    private readonly ISettingService _settingService;

    public PosConfigService(ISettingService settingService)
    {
        _settingService = settingService;
    }

    public async Task<PosConfigDto> GetAsync(CancellationToken cancellationToken = default)
    {
        return new PosConfigDto
        {
            IsEnabled = await GetBoolAsync("Pos.Enabled", false, cancellationToken),
            ConnectionType = await GetStringAsync("Pos.ConnectionType", "tcp", cancellationToken),
            Host = await GetStringAsync("Pos.Host", string.Empty, cancellationToken),
            Port = await GetIntAsync("Pos.Port", 9100, cancellationToken),
            SerialPort = await GetStringAsync("Pos.SerialPort", "COM1", cancellationToken),
            BaudRate = await GetIntAsync("Pos.BaudRate", 9600, cancellationToken),
            TimeoutSeconds = await GetIntAsync("Pos.TimeoutSeconds", 120, cancellationToken),
            Protocol = await GetStringAsync("Pos.Protocol", "gmp3", cancellationToken),
        };
    }

    public async Task SaveAsync(PosConfigDto config, CancellationToken cancellationToken = default)
    {
        await _settingService.SaveSettingAsync("Pos.Enabled", config.IsEnabled.ToString(), "ÖKC entegrasyonu etkin mi", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.ConnectionType", config.ConnectionType, "ÖKC bağlantı tipi (tcp/serial/simulator)", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.Host", config.Host ?? string.Empty, "ÖKC IP adresi", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.Port", config.Port.ToString(), "ÖKC TCP portu", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.SerialPort", config.SerialPort ?? "COM1", "ÖKC seri port adı", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.BaudRate", config.BaudRate.ToString(), "ÖKC seri port hızı", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.TimeoutSeconds", config.TimeoutSeconds.ToString(), "ÖKC yanıt bekleme süresi (sn)", cancellationToken);
        await _settingService.SaveSettingAsync("Pos.Protocol", config.Protocol ?? "gmp3", "ÖKC protokolü", cancellationToken);
    }

    private async Task<string> GetStringAsync(string key, string fallback, CancellationToken ct)
    {
        var value = await _settingService.GetSettingValueAsync(key, fallback, ct);

        return string.IsNullOrWhiteSpace(value) ? fallback : value;
    }

    private async Task<int> GetIntAsync(string key, int fallback, CancellationToken ct)
    {
        var raw = await _settingService.GetSettingValueAsync(key, fallback.ToString(), ct);

        return int.TryParse(raw, out var value) ? value : fallback;
    }

    private async Task<bool> GetBoolAsync(string key, bool fallback, CancellationToken ct)
    {
        var raw = await _settingService.GetSettingValueAsync(key, fallback.ToString(), ct);

        return bool.TryParse(raw, out var value) ? value : fallback;
    }
}

using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Application.Services;

/// <summary>
/// Termal yazıcıların cihaz üzerindeki yapılandırmasını yerel SQLite'ta yönetir
/// ve kağıt/satır genişliğini sunucuya bildirir.
///
/// Neden burada: hangi Windows yazıcısının kurulu olduğunu merkezi sunucu bilemez.
/// Fiş METNİ sunucuda üretildiği için genişlik bilgisi yukarı senkronlanır;
/// fiziki yazıcı SEÇİMİ ise tamamen cihazda kalır.
/// </summary>
public class PrinterConfigService : IPrinterConfigService
{
    private readonly ISettingService _settingService;
    private readonly IPrinterService _printerService;
    private readonly ILaravelApiClient _laravelApiClient;
    private readonly ILogger<PrinterConfigService> _logger;

    /// <summary>Desteklenen kullanım yerleri.</summary>
    public static readonly string[] PrinterTypes = { "kitchen", "cashier", "bar" };

    public PrinterConfigService(
        ISettingService settingService,
        IPrinterService printerService,
        ILaravelApiClient laravelApiClient,
        ILogger<PrinterConfigService> logger)
    {
        _settingService = settingService;
        _printerService = printerService;
        _laravelApiClient = laravelApiClient;
        _logger = logger;
    }

    public async Task<List<PrinterConfigDto>> GetAllAsync(CancellationToken cancellationToken = default)
    {
        var list = new List<PrinterConfigDto>();

        foreach (var type in PrinterTypes)
        {
            list.Add(await GetForTypeAsync(type, cancellationToken));
        }

        return list;
    }

    public async Task<PrinterConfigDto> GetForTypeAsync(string printerType, CancellationToken cancellationToken = default)
    {
        var type = Normalize(printerType);

        var name = await _settingService.GetSettingValueAsync(Key(type, "Name"), string.Empty, cancellationToken);
        var paperRaw = await _settingService.GetSettingValueAsync(Key(type, "PaperWidth"), "80", cancellationToken);
        var charRaw = await _settingService.GetSettingValueAsync(Key(type, "CharWidth"), "0", cancellationToken);
        var codepage = await _settingService.GetSettingValueAsync(Key(type, "Codepage"), "cp857", cancellationToken);
        var enabledRaw = await _settingService.GetSettingValueAsync(Key(type, "Enabled"), "true", cancellationToken);

        return new PrinterConfigDto
        {
            Type = type,
            PrinterName = name ?? string.Empty,
            PaperWidth = int.TryParse(paperRaw, out var paper) && paper == 58 ? 58 : 80,
            CharWidth = int.TryParse(charRaw, out var chars) ? chars : 0,
            Codepage = string.IsNullOrWhiteSpace(codepage) ? "cp857" : codepage,
            IsEnabled = !bool.TryParse(enabledRaw, out var enabled) || enabled,
        };
    }

    public async Task SaveAllAsync(IEnumerable<PrinterConfigDto> configs, CancellationToken cancellationToken = default)
    {
        foreach (var config in configs)
        {
            var type = Normalize(config.Type);

            await _settingService.SaveSettingAsync(Key(type, "Name"), config.PrinterName ?? string.Empty, $"{PrinterConfigDto.LabelFor(type)} yazıcısı (Windows adı)", cancellationToken);
            await _settingService.SaveSettingAsync(Key(type, "PaperWidth"), config.PaperWidth.ToString(), $"{PrinterConfigDto.LabelFor(type)} kağıt genişliği (mm)", cancellationToken);
            await _settingService.SaveSettingAsync(Key(type, "CharWidth"), config.CharWidth.ToString(), $"{PrinterConfigDto.LabelFor(type)} satır genişliği (karakter)", cancellationToken);
            await _settingService.SaveSettingAsync(Key(type, "Codepage"), config.Codepage ?? "cp857", $"{PrinterConfigDto.LabelFor(type)} ESC/POS kod sayfası", cancellationToken);
            await _settingService.SaveSettingAsync(Key(type, "Enabled"), config.IsEnabled.ToString(), $"{PrinterConfigDto.LabelFor(type)} yazdırma etkin mi", cancellationToken);

            // Fiş metni sunucuda üretildiği için genişliği sunucunun da bilmesi gerekir.
            // Sunucuya ulaşılamazsa yerel kayıt yine de geçerlidir; bir sonraki
            // kaydetmede tekrar denenir.
            try
            {
                var synced = await _laravelApiClient.SyncPrinterAsync(
                    type,
                    config.PrinterName ?? string.Empty,
                    config.PaperWidth,
                    config.EffectiveCharWidth,
                    config.Codepage ?? "cp857",
                    config.IsEnabled,
                    cancellationToken);

                if (!synced)
                {
                    _logger.LogWarning("'{Type}' yazıcı ayarı sunucuya bildirilemedi. Yerel kayıt geçerli.", type);
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning(ex, "'{Type}' yazıcı ayarı sunucuya bildirilirken hata oluştu.", type);
            }
        }
    }

    public IReadOnlyList<string> GetInstalledPrinters() => _printerService.GetInstalledPrinters();

    public string GetDefaultPrinterName() => _printerService.GetDefaultPrinterName();

    private const string NotificationsKey = "Printer.Notifications.Enabled";

    public async Task<bool> GetNotificationsEnabledAsync(CancellationToken cancellationToken = default)
    {
        var raw = await _settingService.GetSettingValueAsync(NotificationsKey, "true", cancellationToken);

        // Ayar hiç kaydedilmemişse bildirimler açık kabul edilir.
        return !bool.TryParse(raw, out var enabled) || enabled;
    }

    public Task SetNotificationsEnabledAsync(bool enabled, CancellationToken cancellationToken = default)
    {
        return _settingService.SaveSettingAsync(
            NotificationsKey,
            enabled.ToString(),
            "Yazdırma sırasında Windows masaüstü bildirimi gösterilsin mi",
            cancellationToken);
    }

    private static string Normalize(string? printerType)
    {
        var type = (printerType ?? string.Empty).Trim().ToLowerInvariant();

        return PrinterTypes.Contains(type) ? type : "cashier";
    }

    private static string Key(string type, string suffix)
    {
        // Örn: Printer.kitchen.Name
        return $"Printer.{type}.{suffix}";
    }
}

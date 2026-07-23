using AltF4DeviceService.Application.DTOs;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Cihaza bağlı termal yazıcıların YEREL yapılandırmasını yönetir.
///
/// Fiziki yazıcı seçimi merkezi sunucudan yapılamaz: hangi yazıcının kurulu
/// olduğunu yalnızca cihazın kendisi bilebilir. Bu yüzden eşleştirme burada,
/// servis programının admin panelinde tutulur.
/// </summary>
public interface IPrinterConfigService
{
    /// <summary>Kullanım yerlerine (kitchen/cashier/bar) göre tüm yapılandırmaları getirir.</summary>
    Task<List<PrinterConfigDto>> GetAllAsync(CancellationToken cancellationToken = default);

    /// <summary>Belirli bir kullanım yerinin yapılandırmasını getirir (tanımsızsa varsayılan döner).</summary>
    Task<PrinterConfigDto> GetForTypeAsync(string printerType, CancellationToken cancellationToken = default);

    /// <summary>
    /// Yapılandırmaları yerel veritabanına kaydeder ve sunucuya bildirir
    /// (sunucunun fiş metnini doğru genişlikte üretebilmesi için).
    /// </summary>
    Task SaveAllAsync(IEnumerable<PrinterConfigDto> configs, CancellationToken cancellationToken = default);

    /// <summary>Windows üzerinde kurulu yazıcıların adlarını listeler.</summary>
    IReadOnlyList<string> GetInstalledPrinters();

    /// <summary>Sistemin varsayılan Windows yazıcısı.</summary>
    string GetDefaultPrinterName();
}

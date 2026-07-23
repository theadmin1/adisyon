namespace AltF4DeviceService.Application.Options;

/// <summary>
/// appsettings.json içerisindeki "ServiceOptions" konfigürasyonunun nesne haritası.
/// </summary>
public class ServiceOptions
{
    public const string SectionName = "ServiceOptions";

    /// <summary>
    /// Localhost HTTP Minimal API Port numarası (varsayılan 18500).
    /// </summary>
    public int Port { get; set; } = 18500;

    /// <summary>
    /// Varsayılan cihaz adı / kodu (örn. KASA-01).
    /// </summary>
    public string DeviceName { get; set; } = "KASA-01";

    /// <summary>
    /// Uzak Laravel Web Adisyon API adresi.
    /// </summary>
    public string ApiUrl { get; set; } = "https://adisyon.synaptropic.com/api";

    /// <summary>
    /// Dahili tarayıcıda açılacak varsayılan Adisyon Web Arayüzü adresi.
    /// </summary>
    public string AdisyonWebUrl { get; set; } = "https://adisyon.synaptropic.com/login";

    /// <summary>
    /// Servis açıldığında dahili tarayıcı otomatik açılsın mı?
    /// </summary>
    public bool AutoOpenBrowser { get; set; } = true;

    /// <summary>
    /// Arka plan senkronizasyon ve canlılık kontrol aralığı (saniye cinsinden).
    /// </summary>
    public int SyncIntervalSeconds { get; set; } = 30;

    /// <summary>
    /// Veritabanı bağlantı dizesi.
    /// </summary>
    public string ConnectionString { get; set; } = "Data Source=altf4_device.db";

    /// <summary>
    /// Admin Yönetim Paneli kullanıcı adı.
    /// </summary>
    public string AdminUsername { get; set; } = "admin";

    /// <summary>
    /// Admin Yönetim Paneli şifresi.
    /// </summary>
    public string AdminPassword { get; set; } = "admin123";

    /// <summary>
    /// Dahili tarayıcı güvenlik ve kısıtlama ayarları.
    /// </summary>
    public BrowserRestrictionOptions BrowserRestrictions { get; set; } = new();
}

/// <summary>
/// Dahili Chromium tarayıcısının güvenlik ve kısıtlama kuralları.
/// </summary>
public class BrowserRestrictionOptions
{
    /// <summary>
    /// F12 ve Geliştirici Araçları (DevTools) engellensin mi?
    /// </summary>
    public bool DisableDevTools { get; set; } = true;

    /// <summary>
    /// Sağ tık bağlam menüsü (Context Menu / İncele) engellensin mi?
    /// </summary>
    public bool DisableContextMenu { get; set; } = true;

    /// <summary>
    /// Sadece izin verilen alan adlarına (AllowedDomains) gezinmeye izin verilsin mi?
    /// </summary>
    public bool RestrictNavigationToAllowedDomains { get; set; } = true;

    /// <summary>
    /// İzin verilen alan adları listesi (örn: ["adisyon.synaptropic.com", "127.0.0.1", "localhost"]).
    /// </summary>
    public List<string> AllowedDomains { get; set; } = new() { "adisyon.synaptropic.com", "synaptropic.com", "127.0.0.1", "localhost" };

    /// <summary>
    /// Navigasyon çubuğu ve adres girişini gizle (Tam Kiosk Modu).
    /// </summary>
    public bool HideNavigationControls { get; set; } = true;

    /// <summary>
    /// Başlık çubuğunu ve Windows görev çubuğunu gizleyen tam ekran Kiosk modu.
    /// </summary>
    public bool EnableKioskFullScreen { get; set; } = true;
}

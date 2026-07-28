using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Application.Workers;

/// <summary>
/// Windows Service arka planında sürekli çalışan BackgroundService.
/// Periyodik canlılık sinyali (Heartbeat), lisans doğrulama ve senkronizasyon kontrollerini yürütür.
/// </summary>
public class DeviceBackgroundWorker : BackgroundService
{
    private readonly IServiceProvider _serviceProvider;
    private readonly IOptions<ServiceOptions> _options;
    private readonly IHostApplicationLifetime _appLifetime;
    private readonly ILogger<DeviceBackgroundWorker> _logger;

    public DeviceBackgroundWorker(
        IServiceProvider serviceProvider,
        IOptions<ServiceOptions> options,
        IHostApplicationLifetime appLifetime,
        ILogger<DeviceBackgroundWorker> logger)
    {
        _serviceProvider = serviceProvider;
        _options = options;
        _appLifetime = appLifetime;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        Environment.SetEnvironmentVariable("ADISYON_OFFLINE_MODE", "true", EnvironmentVariableTarget.Process);
        Environment.SetEnvironmentVariable("DB_CONNECTION", "sqlite", EnvironmentVariableTarget.Process);

        _logger.LogInformation("AltF4 Device Service Background Worker başlatıldı.");

        // Ilk acilista cihazi ilklendir
        using (var scope = _serviceProvider.CreateScope())
        {
            try
            {
                var deviceService = scope.ServiceProvider.GetRequiredService<IDeviceService>();
                var device = await deviceService.GetOrCreateDeviceIdentityAsync(stoppingToken);
                _logger.LogInformation("Cihaz Kimliği Doğrulandı. UUID: {Uuid}, Kod: {Code}", device.DeviceUuid, device.DeviceCode);

                var licenseService = scope.ServiceProvider.GetRequiredService<ILicenseService>();
                await licenseService.GetOrCreateLicenseAsync(stoppingToken);

                var branchService = scope.ServiceProvider.GetRequiredService<IBranchService>();
                await branchService.GetOrCreateBranchAccountAsync(stoppingToken);

                // 1. Sunucu ile Lisans Doğrulaması & El Sıkışması (API Key alınır ve SQLite'a kaydedilir)
                _logger.LogInformation("Servis başlangıç Lisans Doğrulaması yapılıyor...");
                var isLicenseValid = await licenseService.VerifyAndUpdateLicenseAsync(stoppingToken);
                var launcher = scope.ServiceProvider.GetService<IBrowserLauncherService>();

                if (!isLicenseValid)
                {
                    _logger.LogError("🛑 LİSANS DOĞRULANAMADI (Pasif veya Süresi Dolmuş)! Uygulama kapatılıyor.");
                    launcher?.UpdateLicenseState(false, "Lisansınız Pasife Alınmıştır veya Süresi Dolmuştur");
                    _appLifetime.StopApplication();
                    return;
                }

                // 2. Servis Başlangıç Canlılık Testi (Heartbeat Ping)
                _logger.LogInformation("Servis başlangıç Canlılık Testi (Heartbeat Ping) gönderiliyor...");
                await ExportDeviceApiKeyAsync(scope.ServiceProvider, stoppingToken);

                var isStartupPingOk = await deviceService.UpdateLastSeenAsync(stoppingToken);

                if (!isStartupPingOk)
                {
                    // Sunucu cihaz API Key'ini tanımıyorsa son bir doğrulama daha dene
                    _logger.LogWarning("Başlangıç canlılık testinde yanıt alınamadı. Sunucu ile lisans doğrulaması yenileniyor...");
                    isLicenseValid = await licenseService.VerifyAndUpdateLicenseAsync(stoppingToken);
                    if (!isLicenseValid)
                    {
                        _logger.LogError("🛑 Lisans pasif veya doğrulanamadı! Uygulama kapatılıyor.");
                        launcher?.UpdateLicenseState(false, "Lisansınız Pasife Alınmıştır veya Süresi Dolmuştur");
                        _appLifetime.StopApplication();
                        return;
                    }
                }

                await ExportDeviceApiKeyAsync(scope.ServiceProvider, stoppingToken);

                launcher?.UpdateLicenseState(true);
                _logger.LogInformation("Servis başlangıç Canlılık Testi ve Lisans Doğrulaması başarıyla tamamlandı.");

                // 🚀 UYGULAMA HER AÇILDIĞINDA UZAK VERİLERİ ANINDA YEREL VERİTABANINA (SQLITE) KOPYALA
                EnsureLocalPhpServerRunning();
                TriggerLocalDatabaseSync();
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Cihaz ilklendirme adımlarında hata oluştu.");
            }
        }

        // 🔄 PERİYODİK AĞ KONTROLÜ VE OTOMATİK ÇEVRİMDİŞİ / ONLİNE DÖNGÜSÜ (Her 5 Saniyede Bir)
        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                using var scope = _serviceProvider.CreateScope();
                var networkService = scope.ServiceProvider.GetService<INetworkMonitoringService>();
                var launcher = scope.ServiceProvider.GetService<IBrowserLauncherService>();

                if (networkService != null)
                {
                    bool isOnline = await networkService.CheckConnectivityAsync(stoppingToken);
                    EnsureLocalPhpServerRunning();

                    if (isOnline)
                    {
                        TriggerLocalDatabaseSync();
                    }
                    launcher?.UpdateNetworkState(isOnline);

                    if (isOnline)
                    {
                        var deviceService = scope.ServiceProvider.GetService<IDeviceService>();
                        if (deviceService != null)
                        {
                            await deviceService.UpdateLastSeenAsync(stoppingToken);
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning(ex, "Periyodik ağ ve canlılık testi sırasında hata oluştu.");
            }

            try
            {
                await Task.Delay(TimeSpan.FromSeconds(10), stoppingToken);
            }
            catch (OperationCanceledException)
            {
                break;
            }
        }

        _logger.LogInformation("AltF4 Device Service Background Worker sonlandırıldı.");
    }

    private static System.Diagnostics.Process? _localPhpProcess;

    private static async Task ExportDeviceApiKeyAsync(
        IServiceProvider serviceProvider,
        CancellationToken cancellationToken)
    {
        var settingService = serviceProvider.GetService<ISettingService>();
        if (settingService == null)
        {
            return;
        }

        var apiKey = await settingService.GetSettingValueAsync(
            "DeviceApiKey",
            string.Empty,
            cancellationToken);

        if (!string.IsNullOrWhiteSpace(apiKey))
        {
            Environment.SetEnvironmentVariable(
                "ADISYON_DEVICE_API_KEY",
                apiKey,
                EnvironmentVariableTarget.Process);
        }
    }

    private void EnsureLocalPhpServerRunning()
    {
        try
        {
            using var tcpClient = new System.Net.Sockets.TcpClient();
            var asyncResult = tcpClient.BeginConnect("127.0.0.1", 8000, null, null);
            bool isListening = asyncResult.AsyncWaitHandle.WaitOne(TimeSpan.FromMilliseconds(200));
            if (isListening && tcpClient.Connected)
            {
                return; // Port 8000 is already active!
            }

            var currentDir = AppDomain.CurrentDomain.BaseDirectory;
            var dir = new DirectoryInfo(currentDir);
            string? projectRoot = null;

            while (dir != null)
            {
                if (File.Exists(Path.Combine(dir.FullName, "artisan")))
                {
                    projectRoot = dir.FullName;
                    break;
                }
                dir = dir.Parent;
            }

            if (projectRoot != null)
            {
                _logger.LogInformation("🚀 Yerel Laravel Kasa Sunucusu (Port 8000) arka planda başlatılıyor... Dizin: {Path}", projectRoot);
                var psi = new System.Diagnostics.ProcessStartInfo
                {
                    FileName = "php",
                    Arguments = "artisan serve --host=127.0.0.1 --port=8000",
                    WorkingDirectory = projectRoot,
                    UseShellExecute = false,
                    CreateNoWindow = true
                };
                psi.EnvironmentVariables["PHP_CLI_SERVER_WORKERS"] = "4";
                _localPhpProcess = System.Diagnostics.Process.Start(psi);

                // Port 8000 hazır olana kadar kısa bir süre bekle (max 2 saniye)
                for (int i = 0; i < 10; i++)
                {
                    Thread.Sleep(200);
                    try
                    {
                        using var client = new System.Net.Sockets.TcpClient();
                        var ar = client.BeginConnect("127.0.0.1", 8000, null, null);
                        if (ar.AsyncWaitHandle.WaitOne(150) && client.Connected)
                        {
                            _logger.LogInformation("✅ Yerel Laravel Kasa Sunucusu (Port 8000) başarıyla aktif oldu.");
                            break;
                        }
                    }
                    catch { }
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogDebug("Yerel Laravel sunucusu kontrol edilirken: {Message}", ex.Message);
        }
    }

    private static DateTime _lastSyncTime = DateTime.MinValue;

    private void TriggerLocalDatabaseSync()
    {
        try
        {
            // Senkronizasyonu en az 30 saniyede bir tetikle
            if ((DateTime.Now - _lastSyncTime).TotalSeconds < 30)
                return;

            _lastSyncTime = DateTime.Now;

            var currentDir = AppDomain.CurrentDomain.BaseDirectory;
            var dir = new DirectoryInfo(currentDir);
            string? projectRoot = null;

            while (dir != null)
            {
                if (File.Exists(Path.Combine(dir.FullName, "artisan")))
                {
                    projectRoot = dir.FullName;
                    break;
                }
                dir = dir.Parent;
            }

            if (projectRoot != null)
            {
                _logger.LogInformation("🌐 adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda yükleniyor (php artisan app:sync-local)...");
                var psi = new System.Diagnostics.ProcessStartInfo
                {
                    FileName = "php",
                    Arguments = "artisan app:sync-local",
                    WorkingDirectory = projectRoot,
                    UseShellExecute = false,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    StandardOutputEncoding = System.Text.Encoding.UTF8,
                    StandardErrorEncoding = System.Text.Encoding.UTF8,
                    CreateNoWindow = true
                };

                var proc = System.Diagnostics.Process.Start(psi);
                if (proc != null)
                {
                    Task.Run(async () =>
                    {
                        try
                        {
                            using var reader = proc.StandardOutput;
                            string? line;
                            while ((line = await reader.ReadLineAsync()) != null)
                            {
                                if (!string.IsNullOrWhiteSpace(line))
                                {
                                    _logger.LogInformation("[Sync] {Output}", line.Trim());
                                }
                            }
                        }
                        catch { }
                    });
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogDebug("Yerel veritabanı senkronizasyonu tetiklenirken hata: {Message}", ex.Message);
        }
    }
}

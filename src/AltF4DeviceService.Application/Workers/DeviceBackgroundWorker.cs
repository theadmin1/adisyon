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

                launcher?.UpdateLicenseState(true);
                _logger.LogInformation("Servis başlangıç Canlılık Testi ve Lisans Doğrulaması başarıyla tamamlandı.");
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Cihaz ilklendirme adımlarında hata oluştu.");
            }
        }

        // 30 saniyelik periyodik canlılık testi döngüsü kaldırıldı.
        // Worker durdurulana kadar sessizce beklemededir.
        try
        {
            await Task.Delay(Timeout.Infinite, stoppingToken);
        }
        catch (OperationCanceledException)
        {
            _logger.LogInformation("AltF4 Device Service Background Worker durduruluyor.");
        }

        _logger.LogInformation("AltF4 Device Service Background Worker sonlandırıldı.");
    }
}

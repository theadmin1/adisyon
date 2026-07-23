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
    private readonly ILogger<DeviceBackgroundWorker> _logger;

    public DeviceBackgroundWorker(
        IServiceProvider serviceProvider,
        IOptions<ServiceOptions> options,
        ILogger<DeviceBackgroundWorker> logger)
    {
        _serviceProvider = serviceProvider;
        _options = options;
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
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Cihaz ilklendirme adımlarında hata oluştu.");
            }
        }

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                var intervalSeconds = _options.Value.SyncIntervalSeconds > 0 ? _options.Value.SyncIntervalSeconds : 30;

                using (var scope = _serviceProvider.CreateScope())
                {
                    var deviceService = scope.ServiceProvider.GetRequiredService<IDeviceService>();
                    await deviceService.UpdateLastSeenAsync(stoppingToken);
                    
                    _logger.LogDebug("Arka plan canlılık sinyali güncellendi. Sonraki döngü {Interval} saniye sonra.", intervalSeconds);
                }

                await Task.Delay(TimeSpan.FromSeconds(intervalSeconds), stoppingToken);
            }
            catch (OperationCanceledException)
            {
                _logger.LogInformation("Background worker durdurma sinyali aldı.");
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Background worker döngüsünde beklenmeyen bir hata oluştu.");
                await Task.Delay(TimeSpan.FromSeconds(10), stoppingToken);
            }
        }

        _logger.LogInformation("AltF4 Device Service Background Worker sonlandırıldı.");
    }
}

using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Laravel Web Adisyon API'si ile iletişim kuran altyapı istemcisi (HttpClient wrapper).
/// Sprint 1 kapsamında altyapı ve interface implementasyonu olarak hazırlanmıştır.
/// </summary>
public class LaravelApiClient : ILaravelApiClient
{
    private readonly HttpClient _httpClient;
    private readonly IOptions<ServiceOptions> _options;
    private readonly ILogger<LaravelApiClient> _logger;

    public LaravelApiClient(
        HttpClient httpClient,
        IOptions<ServiceOptions> options,
        ILogger<LaravelApiClient> logger)
    {
        _httpClient = httpClient;
        _options = options;
        _logger = logger;
    }

    public Task<bool> ValidateLicenseAsync(string licenseKey, string deviceToken, CancellationToken cancellationToken = default)
    {
        _logger.LogInformation("[LaravelApiClient STUB] Uzak Laravel API'ye lisans doğrulama isteği gönderiliyor. BaseUrl: {BaseUrl}, Key: {Key}", 
            _options.Value.ApiUrl, licenseKey);

        // İleriki sprintlerde gerçek HTTP POST/GET isteği burada yapılacak
        return Task.FromResult(true);
    }

    public Task<bool> SyncBranchAccountAsync(int branchId, CancellationToken cancellationToken = default)
    {
        _logger.LogInformation("[LaravelApiClient STUB] Uzak Laravel API'ye şube senkronizasyon isteği gönderiliyor. BranchId: {BranchId}", branchId);

        return Task.FromResult(true);
    }

    public Task<bool> SendHeartbeatAsync(string deviceUuid, CancellationToken cancellationToken = default)
    {
        _logger.LogInformation("[LaravelApiClient STUB] Uzak Laravel API'ye canlılık sinyali iletiliyor. DeviceUuid: {DeviceUuid}", deviceUuid);

        return Task.FromResult(true);
    }
}

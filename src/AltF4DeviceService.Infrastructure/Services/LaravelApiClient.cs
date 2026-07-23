using System.Net.Http.Json;
using System.Text.Json;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Laravel Web Adisyon API'si ile canlı lisans doğrulama ve API Key tabanlı güvenli iletişim istemcisi.
/// </summary>
public class LaravelApiClient : ILaravelApiClient
{
    private readonly HttpClient _httpClient;
    private readonly IOptions<ServiceOptions> _options;
    private readonly IServiceProvider _serviceProvider;
    private readonly ILogger<LaravelApiClient> _logger;

    private string? _cachedApiKey;

    public LaravelApiClient(
        HttpClient httpClient,
        IOptions<ServiceOptions> options,
        IServiceProvider serviceProvider,
        ILogger<LaravelApiClient> logger)
    {
        _httpClient = httpClient;
        _options = options;
        _serviceProvider = serviceProvider;
        _logger = logger;
    }

    public async Task<bool> ValidateLicenseAsync(string licenseKey, string deviceToken, CancellationToken cancellationToken = default)
    {
        try
        {
            var baseUrl = _options.Value.ApiUrl.TrimEnd('/');
            var endpoint = $"{baseUrl}/v1/license/verify";

            var payload = new
            {
                license_key = licenseKey,
                device_guid = deviceToken ?? Guid.NewGuid().ToString(),
                device_code = _options.Value.DeviceName ?? "KASA-01",
                app_version = "1.0.0",
                os_info = Environment.OSVersion.ToString()
            };

            _logger.LogInformation("Laravel API'ye Lisans Doğrulama İsteği Gönderiliyor. Endpoint: {Endpoint}, Key: {Key}", endpoint, licenseKey);

            var response = await _httpClient.PostAsJsonAsync(endpoint, payload, cancellationToken);
            if (response.IsSuccessStatusCode)
            {
                var content = await response.Content.ReadAsStringAsync(cancellationToken);
                _logger.LogInformation("Laravel API Lisans Yanıtı Alındı: {Content}", content);
                
                using var doc = JsonDocument.Parse(content);
                var root = doc.RootElement;

                if (root.TryGetProperty("success", out var successElement) && successElement.GetBoolean())
                {
                    // 🔑 Sunucunun ürettiği Güvenli Cihaz API Key'ini alıp yerel veritabanına kaydedelim
                    if (root.TryGetProperty("api_key", out var apiKeyProp))
                    {
                        _cachedApiKey = apiKeyProp.GetString();
                        if (!string.IsNullOrWhiteSpace(_cachedApiKey))
                        {
                            using var scope = _serviceProvider.CreateScope();
                            var settingService = scope.ServiceProvider.GetService<ISettingService>();
                            if (settingService != null)
                            {
                                await settingService.SaveSettingAsync("DeviceApiKey", _cachedApiKey, "Sunucu Tarafından Verilen Cihaz API Key", cancellationToken);
                                _logger.LogInformation("🔑 Sunucu API Key SQLite veritabanına kaydedildi: {ApiKey}", _cachedApiKey);
                            }
                        }
                    }

                    return true;
                }
                else
                {
                    _logger.LogWarning("Laravel API Lisans reddedildi veya pasif! Yanıt: {Content}", content);
                    return false;
                }
            }
            else
            {
                _logger.LogWarning("Laravel API Lisans İsteği Başarısız. HTTP Status: {Status}", response.StatusCode);
                return false;
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Laravel API Lisans sunucusuna erişilemedi ({Endpoint}): {Message}", _options.Value.ApiUrl, ex.Message);
        }

        return false;
    }

    public async Task<bool> SyncBranchAccountAsync(int branchId, CancellationToken cancellationToken = default)
    {
        _logger.LogInformation("Laravel API Şube senkronizasyonu gerçekleştiriliyor. BranchId: {BranchId}", branchId);
        return await Task.FromResult(true);
    }

    public async Task<bool> SendHeartbeatAsync(string deviceUuid, CancellationToken cancellationToken = default)
    {
        try
        {
            var baseUrl = _options.Value.ApiUrl.TrimEnd('/');
            var endpoint = $"{baseUrl}/v1/device/ping";

            var payload = new
            {
                device_guid = deviceUuid,
                device_code = _options.Value.DeviceName ?? "KASA-01",
                api_key = _cachedApiKey
            };

            using var requestMessage = new HttpRequestMessage(HttpMethod.Post, endpoint);
            requestMessage.Content = JsonContent.Create(payload);

            if (!string.IsNullOrWhiteSpace(_cachedApiKey))
            {
                requestMessage.Headers.Add("X-Device-Api-Key", _cachedApiKey);
            }

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);
            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Heartbeat sinyali iletilemedi.");
            return false;
        }
    }
}

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
    private string? _cachedDeviceUuid;

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
            var apiBase = baseUrl.EndsWith("/api", StringComparison.OrdinalIgnoreCase) ? baseUrl : $"{baseUrl}/api";
            var endpoint = $"{apiBase}/v1/license/verify";

            string restaurantEmail = _options.Value.RestaurantLoginId;
            try
            {
                using var scope = _serviceProvider.CreateScope();
                var settingService = scope.ServiceProvider.GetService<ISettingService>();
                if (settingService != null)
                {
                    restaurantEmail = await settingService.GetSettingValueAsync("RestaurantLoginId", restaurantEmail, cancellationToken);
                }
            }
            catch { }

            var payload = new
            {
                license_key = licenseKey,
                device_guid = deviceToken ?? Guid.NewGuid().ToString(),
                device_code = _options.Value.DeviceName ?? "KASA-01",
                restaurant_email = restaurantEmail,
                app_version = "1.0.0",
                os_info = Environment.OSVersion.ToString()
            };

            _logger.LogInformation("Laravel API'ye Lisans Doğrulama İsteği Gönderiliyor. Endpoint: {Endpoint}, Lisans: {Key}", endpoint, Mask(licenseKey));

            var response = await _httpClient.PostAsJsonAsync(endpoint, payload, cancellationToken);
            var content = await response.Content.ReadAsStringAsync(cancellationToken);

            if (response.IsSuccessStatusCode)
            {
                // Yanıt gövdesi api_key ve device_token içerir; log dosyasına yazılmaz.
                _logger.LogInformation("Laravel API Lisans Yanıtı Alındı (HTTP {Status}).", response.StatusCode);

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
                                // Anahtarın kendisi ASLA loglanmaz (log dosyaları 30 gün saklanıyor).
                                _logger.LogInformation("Sunucu API Key alındı ve yerel veritabanına kaydedildi (uzunluk: {Length}).", _cachedApiKey.Length);
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
                _logger.LogWarning("Laravel API Lisans İsteği Başarısız. HTTP Status: {Status}, Yanıt: {Content}", response.StatusCode, content);
                return false;
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Laravel API Lisans sunucusuna erişilemedi ({Endpoint}): {Message}. Yerel veritabanı lisans kontrolü yapılıyor...", _options.Value.ApiUrl, ex.Message);
            try
            {
                using var scope = _serviceProvider.CreateScope();
                var licenseService = scope.ServiceProvider.GetService<ILicenseService>();
                if (licenseService != null)
                {
                    return await licenseService.IsLocalLicenseValidAsync(cancellationToken);
                }
            }
            catch { }
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
            var endpoint = $"{ApiBase()}/v1/device/ping";
            var apiKey = await GetApiKeyAsync(cancellationToken);

            var payload = new
            {
                device_guid = deviceUuid,
                device_code = _options.Value.DeviceName ?? "KASA-01",
                api_key = apiKey,
            };

            using var requestMessage = new HttpRequestMessage(HttpMethod.Post, endpoint)
            {
                Content = JsonContent.Create(payload),
            };

            AttachApiKey(requestMessage, apiKey);

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);
            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Heartbeat sinyali iletilemedi.");
            return false;
        }
    }

    public async Task<List<AltF4DeviceService.Domain.DTOs.PrintJobDto>> GetPendingPrintJobsAsync(CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/pending";

            using var requestMessage = new HttpRequestMessage(HttpMethod.Get, endpoint);
            AttachApiKey(requestMessage, await GetApiKeyAsync(cancellationToken));

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);

            if (response.StatusCode is System.Net.HttpStatusCode.Unauthorized or System.Net.HttpStatusCode.Forbidden)
            {
                // API Key geçersiz/lisans pasif: önbelleği düşür ki bir sonraki
                // lisans doğrulamasında yeni anahtar alınabilsin.
                _cachedApiKey = null;
                _logger.LogWarning("Yazdırma kuyruğu reddedildi (HTTP {Code}). Cihaz API Key yenilenmeli.", (int) response.StatusCode);
                return new List<AltF4DeviceService.Domain.DTOs.PrintJobDto>();
            }
            if (response.IsSuccessStatusCode)
            {
                var content = await response.Content.ReadAsStringAsync(cancellationToken);
                using var doc = JsonDocument.Parse(content);
                var root = doc.RootElement;

                if (root.TryGetProperty("jobs", out var jobsElement) && jobsElement.ValueKind == JsonValueKind.Array)
                {
                    var resultList = new List<AltF4DeviceService.Domain.DTOs.PrintJobDto>();
                    foreach (var jobEl in jobsElement.EnumerateArray())
                    {
                        var dto = new AltF4DeviceService.Domain.DTOs.PrintJobDto
                        {
                            Id = jobEl.GetProperty("id").GetInt64(),
                            JobType = jobEl.TryGetProperty("job_type", out var jt) ? jt.GetString() ?? "" : "",
                            PrinterType = jobEl.TryGetProperty("printer_type", out var pt) ? pt.GetString() ?? "" : "",
                            Title = jobEl.TryGetProperty("title", out var tt) ? tt.GetString() ?? "" : "",
                            Status = jobEl.TryGetProperty("status", out var st) ? st.GetString() ?? "pending" : "pending",
                            TargetPrinter = jobEl.TryGetProperty("target_printer", out var tp) ? tp.GetString() ?? "" : "",
                            ConnectionType = jobEl.TryGetProperty("connection_type", out var ct) ? ct.GetString() ?? "windows_driver" : "windows_driver",
                            PaperWidth = jobEl.TryGetProperty("paper_width", out var pw) ? pw.GetInt32() : 80,
                            CharWidth = jobEl.TryGetProperty("char_width", out var cw) ? cw.GetInt32() : 48,
                            Codepage = jobEl.TryGetProperty("codepage", out var cp) ? cp.GetString() ?? "cp857" : "cp857",
                            CreatedAt = jobEl.TryGetProperty("created_at", out var ca) ? ca.GetString() ?? "" : "",
                        };

                        if (jobEl.TryGetProperty("payload", out var payloadEl) && payloadEl.ValueKind == JsonValueKind.Object)
                        {
                            dto.Payload = new AltF4DeviceService.Domain.DTOs.PrintJobPayloadDto
                            {
                                RawText = payloadEl.TryGetProperty("raw_text", out var raw) ? raw.GetString() : null
                            };
                        }

                        resultList.Add(dto);
                    }
                    return resultList;
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Bekleyen fiş işleri çekilemedi.");
        }

        return new List<AltF4DeviceService.Domain.DTOs.PrintJobDto>();
    }

    public async Task<bool> UpdatePrintJobStatusAsync(long jobId, string status, string? errorMessage = null, CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/jobs/{jobId}/status";

            var payload = new
            {
                status,
                error_message = errorMessage,
                // DeviceName ("KASA-01") bir ETİKETTİR, cihaz kimliği değildir.
                // Sunucu gerçek UUID beklediği için eskiden hiçbir kayıt eşleşmiyordu.
                device_guid = await GetDeviceUuidAsync(cancellationToken),
            };

            using var requestMessage = new HttpRequestMessage(HttpMethod.Post, endpoint)
            {
                Content = JsonContent.Create(payload),
            };

            AttachApiKey(requestMessage, await GetApiKeyAsync(cancellationToken));

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);

            if (!response.IsSuccessStatusCode)
            {
                _logger.LogWarning(
                    "Fiş durumu sunucu tarafından kabul edilmedi (Job #{JobId}, Durum: {Status}, HTTP {Code}).",
                    jobId, status, (int) response.StatusCode);
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Fiş işi durumu güncellenemedi (Job #{JobId}, Durum: {Status}).", jobId, status);
            return false;
        }
    }

    public async Task<bool> ClaimPrintJobAsync(long jobId, CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/jobs/{jobId}/claim";

            using var requestMessage = new HttpRequestMessage(HttpMethod.Post, endpoint);
            AttachApiKey(requestMessage, await GetApiKeyAsync(cancellationToken));

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);

            if (response.StatusCode == System.Net.HttpStatusCode.Conflict)
            {
                // Başka bir cihaz (veya polling döngüsü) işi zaten almış: tekrar basma.
                _logger.LogInformation("Fiş #{JobId} zaten başka bir cihaz tarafından alınmış, atlanıyor.", jobId);
                return false;
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Fiş #{JobId} kilitlenemedi.", jobId);
            return false;
        }
    }

    public async Task<bool> SyncPrinterAsync(
        string printerType,
        string printerName,
        int paperWidth,
        int charWidth,
        string codepage,
        bool isEnabled,
        CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/printers";

            var payload = new
            {
                // branch_id sunucuda API Key'den çözülür, istemciden gönderilmez.
                name = string.IsNullOrWhiteSpace(printerName)
                    ? $"{_options.Value.DeviceName} - {printerType}"
                    : printerName,
                type = printerType,
                connection_type = "windows_driver",
                printer_target = printerName ?? string.Empty,
                paper_width = paperWidth,
                char_width = charWidth,
                codepage = codepage,
                is_active = isEnabled,
            };

            using var requestMessage = new HttpRequestMessage(HttpMethod.Post, endpoint)
            {
                Content = JsonContent.Create(payload),
            };

            AttachApiKey(requestMessage, await GetApiKeyAsync(cancellationToken));

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);

            if (!response.IsSuccessStatusCode)
            {
                _logger.LogWarning(
                    "Yazıcı ayarı sunucuya bildirilemedi ({Type}, HTTP {Code}).",
                    printerType, (int) response.StatusCode);
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Yazıcı ayarı sunucuya bildirilirken hata oluştu ({Type}).", printerType);
            return false;
        }
    }

    // ------------------------------------------------------------------

    private string ApiBase()
    {
        var baseUrl = _options.Value.ApiUrl.TrimEnd('/');

        return baseUrl.EndsWith("/api", StringComparison.OrdinalIgnoreCase) ? baseUrl : $"{baseUrl}/api";
    }

    private static void AttachApiKey(HttpRequestMessage request, string? apiKey)
    {
        if (!string.IsNullOrWhiteSpace(apiKey))
        {
            request.Headers.Add("X-Device-Api-Key", apiKey);
        }
    }

    private async Task<string?> GetApiKeyAsync(CancellationToken cancellationToken)
    {
        if (!string.IsNullOrWhiteSpace(_cachedApiKey))
        {
            return _cachedApiKey;
        }

        try
        {
            using var scope = _serviceProvider.CreateScope();
            var settingService = scope.ServiceProvider.GetService<ISettingService>();

            if (settingService != null)
            {
                _cachedApiKey = await settingService.GetSettingValueAsync("DeviceApiKey", string.Empty, cancellationToken);
            }
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Cihaz API Key yerel veritabanından okunamadı.");
        }

        return _cachedApiKey;
    }

    /// <summary>
    /// Cihazın ilk çalıştırmada üretilip SQLite'a yazılan gerçek UUID'sini döner.
    /// </summary>
    private async Task<string> GetDeviceUuidAsync(CancellationToken cancellationToken)
    {
        if (!string.IsNullOrWhiteSpace(_cachedDeviceUuid))
        {
            return _cachedDeviceUuid;
        }

        try
        {
            using var scope = _serviceProvider.CreateScope();
            var unitOfWork = scope.ServiceProvider.GetService<IUnitOfWork>();

            if (unitOfWork != null)
            {
                var devices = await unitOfWork.Devices.GetAllAsync(cancellationToken);
                _cachedDeviceUuid = devices.FirstOrDefault()?.DeviceUuid;
            }
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Cihaz UUID'si yerel veritabanından okunamadı.");
        }

        return _cachedDeviceUuid ?? string.Empty;
    }

    /// <summary>Sırların log dosyasına düşmemesi için maskeleme.</summary>
    private static string Mask(string? secret)
    {
        if (string.IsNullOrWhiteSpace(secret))
        {
            return "(bos)";
        }

        return secret.Length <= 6
            ? new string('*', secret.Length)
            : $"{secret[..4]}***{secret[^2..]}";
    }
}

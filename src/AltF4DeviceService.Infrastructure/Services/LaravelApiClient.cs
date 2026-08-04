using System.Globalization;
using System.Net;
using System.Net.Http.Json;
using System.Text.Json;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Enums;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Laravel Web Adisyon API'si ile lisans dogrulama ve cihaz iletisimi istemcisi.
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

    public async Task<LicenseValidationResultDto> ValidateLicenseAsync(
        string licenseKey,
        string deviceUuid,
        CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(licenseKey))
        {
            return new LicenseValidationResultDto
            {
                IsValid = false,
                Status = LicenseStatus.Unlicensed,
                FailureReason = "Lisans anahtari bos.",
            };
        }

        try
        {
            var baseUrl = _options.Value.ApiUrl.TrimEnd('/');
            var apiBase = baseUrl.EndsWith("/api", StringComparison.OrdinalIgnoreCase) ? baseUrl : $"{baseUrl}/api";
            var endpoint = $"{apiBase}/v1/license/verify";

            var restaurantEmail = _options.Value.RestaurantLoginId;
            try
            {
                using var scope = _serviceProvider.CreateScope();
                var settingService = scope.ServiceProvider.GetService<ISettingService>();
                if (settingService != null)
                {
                    restaurantEmail = await settingService.GetSettingValueAsync(
                        "RestaurantLoginId",
                        restaurantEmail,
                        cancellationToken);
                }
            }
            catch
            {
                // Ayar okunamazsa config'teki varsayilan ile devam et.
            }

            var payload = new
            {
                license_key = licenseKey,
                device_guid = deviceUuid,
                device_code = _options.Value.DeviceName ?? "KASA-01",
                restaurant_email = restaurantEmail,
                app_version = "1.0.0",
                os_info = Environment.OSVersion.ToString(),
            };

            _logger.LogInformation(
                "Laravel API'ye lisans dogrulama istegi gonderiliyor. Endpoint: {Endpoint}, Lisans: {Key}",
                endpoint,
                Mask(licenseKey));

            using var response = await _httpClient.PostAsJsonAsync(endpoint, payload, cancellationToken);
            var content = await response.Content.ReadAsStringAsync(cancellationToken);

            if (response.IsSuccessStatusCode)
            {
                _logger.LogInformation("Laravel API lisans yaniti alindi (HTTP {Status}).", response.StatusCode);
                return await ParseLicenseValidationResponseAsync(content, cancellationToken);
            }

            _logger.LogWarning(
                "Laravel API lisans istegi basarisiz. HTTP Status: {Status}, Yanit: {Content}",
                response.StatusCode,
                content);

            if (response.StatusCode == HttpStatusCode.RequestTimeout
                || response.StatusCode == HttpStatusCode.TooManyRequests
                || (int)response.StatusCode >= 500)
            {
                _logger.LogWarning(
                    "Lisans dogrulama servisi gecici olarak kullanilamiyor (HTTP {Status}). Yerel lisans kontrol edilecek.",
                    (int)response.StatusCode);

                return await LoadLocalLicenseValidationResultAsync(
                    cancellationToken,
                    $"Sunucu gecici hatasi: HTTP {(int)response.StatusCode}");
            }

            return ParseFailedLicenseValidationResponse(content, response.StatusCode);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(
                "Laravel API lisans sunucusuna erisilemedi ({Endpoint}): {Message}. Yerel veritabani lisans kontrolu yapiliyor...",
                _options.Value.ApiUrl,
                ex.Message);

            return await LoadLocalLicenseValidationResultAsync(cancellationToken, ex.Message);
        }
    }

    public async Task<bool> SyncBranchAccountAsync(int branchId, CancellationToken cancellationToken = default)
    {
        _logger.LogInformation("Laravel API sube senkronizasyonu gerceklestiriliyor. BranchId: {BranchId}", branchId);
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

    public async Task<List<PrintJobDto>> GetPendingPrintJobsAsync(CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/pending";

            using var requestMessage = new HttpRequestMessage(HttpMethod.Get, endpoint);
            AttachApiKey(requestMessage, await GetApiKeyAsync(cancellationToken));

            var response = await _httpClient.SendAsync(requestMessage, cancellationToken);

            if (response.StatusCode is HttpStatusCode.Unauthorized or HttpStatusCode.Forbidden)
            {
                _cachedApiKey = null;
                _logger.LogWarning("Yazdirma kuyrugu reddedildi (HTTP {Code}). Cihaz API key yenilenmeli.", (int)response.StatusCode);
                return new List<PrintJobDto>();
            }

            if (response.IsSuccessStatusCode)
            {
                var content = await response.Content.ReadAsStringAsync(cancellationToken);
                using var doc = JsonDocument.Parse(content);
                var root = doc.RootElement;

                if (root.TryGetProperty("jobs", out var jobsElement) && jobsElement.ValueKind == JsonValueKind.Array)
                {
                    var resultList = new List<PrintJobDto>();

                    foreach (var jobEl in jobsElement.EnumerateArray())
                    {
                        var dto = new PrintJobDto
                        {
                            Id = jobEl.GetProperty("id").GetInt64(),
                            JobType = jobEl.TryGetProperty("job_type", out var jobType) ? jobType.GetString() ?? string.Empty : string.Empty,
                            PrinterType = jobEl.TryGetProperty("printer_type", out var printerType) ? printerType.GetString() ?? string.Empty : string.Empty,
                            Title = jobEl.TryGetProperty("title", out var title) ? title.GetString() ?? string.Empty : string.Empty,
                            Status = jobEl.TryGetProperty("status", out var status) ? status.GetString() ?? "pending" : "pending",
                            TargetPrinter = jobEl.TryGetProperty("target_printer", out var targetPrinter) ? targetPrinter.GetString() ?? string.Empty : string.Empty,
                            ConnectionType = jobEl.TryGetProperty("connection_type", out var connectionType) ? connectionType.GetString() ?? "windows_driver" : "windows_driver",
                            PaperWidth = jobEl.TryGetProperty("paper_width", out var paperWidth) ? paperWidth.GetInt32() : 80,
                            CharWidth = jobEl.TryGetProperty("char_width", out var charWidth) ? charWidth.GetInt32() : 48,
                            Codepage = jobEl.TryGetProperty("codepage", out var codepage) ? codepage.GetString() ?? "cp857" : "cp857",
                            CreatedAt = jobEl.TryGetProperty("created_at", out var createdAt) ? createdAt.GetString() ?? string.Empty : string.Empty,
                        };

                        if (jobEl.TryGetProperty("payload", out var payloadEl) && payloadEl.ValueKind == JsonValueKind.Object)
                        {
                            dto.Payload = new PrintJobPayloadDto
                            {
                                RawText = payloadEl.TryGetProperty("raw_text", out var rawText) ? rawText.GetString() : null,
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
            _logger.LogDebug(ex, "Bekleyen fis isleri cekilemedi.");
        }

        return new List<PrintJobDto>();
    }

    public async Task<bool> UpdatePrintJobStatusAsync(
        long jobId,
        string status,
        string? errorMessage = null,
        CancellationToken cancellationToken = default)
    {
        try
        {
            var endpoint = $"{ApiBase()}/v1/print/jobs/{jobId}/status";

            var payload = new
            {
                status,
                error_message = errorMessage,
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
                    "Fis durumu sunucu tarafindan kabul edilmedi (Job #{JobId}, Durum: {Status}, HTTP {Code}).",
                    jobId,
                    status,
                    (int)response.StatusCode);
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Fis isi durumu guncellenemedi (Job #{JobId}, Durum: {Status}).", jobId, status);
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

            if (response.StatusCode == HttpStatusCode.Conflict)
            {
                _logger.LogInformation("Fis #{JobId} zaten baska bir cihaz tarafindan alinmis, atlaniyor.", jobId);
                return false;
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Fis #{JobId} kilitlenemedi.", jobId);
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
                name = string.IsNullOrWhiteSpace(printerName)
                    ? $"{_options.Value.DeviceName} - {printerType}"
                    : printerName,
                type = printerType,
                connection_type = "windows_driver",
                printer_target = printerName ?? string.Empty,
                paper_width = paperWidth,
                char_width = charWidth,
                codepage,
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
                    "Yazici ayari sunucuya bildirilemedi ({Type}, HTTP {Code}).",
                    printerType,
                    (int)response.StatusCode);
            }

            return response.IsSuccessStatusCode;
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Yazici ayari sunucuya bildirilirken hata olustu ({Type}).", printerType);
            return false;
        }
    }

    private async Task<LicenseValidationResultDto> ParseLicenseValidationResponseAsync(
        string content,
        CancellationToken cancellationToken)
    {
        using var doc = JsonDocument.Parse(content);
        var root = doc.RootElement;

        var success = root.TryGetProperty("success", out var successElement) && successElement.GetBoolean();
        var statusText = root.TryGetProperty("status", out var statusElement) ? statusElement.GetString() ?? string.Empty : string.Empty;
        var message = root.TryGetProperty("message", out var messageElement) ? messageElement.GetString() ?? string.Empty : string.Empty;

        if (!success)
        {
            return ParseFailedLicenseValidationResponse(content, HttpStatusCode.OK);
        }

        if (root.TryGetProperty("api_key", out var apiKeyElement))
        {
            _cachedApiKey = apiKeyElement.GetString();

            if (!string.IsNullOrWhiteSpace(_cachedApiKey))
            {
                using var scope = _serviceProvider.CreateScope();
                var settingService = scope.ServiceProvider.GetService<ISettingService>();

                if (settingService != null)
                {
                    await settingService.SaveSettingAsync(
                        "DeviceApiKey",
                        _cachedApiKey,
                        "Sunucu tarafindan verilen cihaz API key",
                        cancellationToken);

                    _logger.LogInformation("Sunucu API key alindi ve yerel veritabanina kaydedildi (uzunluk: {Length}).", _cachedApiKey.Length);
                }
            }
        }

        return new LicenseValidationResultDto
        {
            IsValid = true,
            Status = MapLicenseStatus(statusText, HttpStatusCode.OK),
            DeviceToken = root.TryGetProperty("device_token", out var tokenElement) ? tokenElement.GetString() ?? string.Empty : string.Empty,
            ExpiresAt = root.TryGetProperty("expires_at", out var expiresElement)
                ? ParseDateTime(expiresElement.GetString())
                : null,
            FailureReason = message,
        };
    }

    private LicenseValidationResultDto ParseFailedLicenseValidationResponse(string content, HttpStatusCode statusCode)
    {
        try
        {
            using var doc = JsonDocument.Parse(content);
            var root = doc.RootElement;
            var statusText = root.TryGetProperty("status", out var statusElement) ? statusElement.GetString() ?? string.Empty : string.Empty;
            var message = root.TryGetProperty("message", out var messageElement) ? messageElement.GetString() ?? string.Empty : string.Empty;

            return new LicenseValidationResultDto
            {
                IsValid = false,
                Status = MapLicenseStatus(statusText, statusCode),
                FailureReason = message,
            };
        }
        catch (JsonException)
        {
            return new LicenseValidationResultDto
            {
                IsValid = false,
                Status = MapLicenseStatus(string.Empty, statusCode),
                FailureReason = content,
            };
        }
    }

    private async Task<LicenseValidationResultDto> LoadLocalLicenseValidationResultAsync(
        CancellationToken cancellationToken,
        string failureReason)
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var unitOfWork = scope.ServiceProvider.GetService<IUnitOfWork>();

            if (unitOfWork == null)
            {
                return new LicenseValidationResultDto
                {
                    IsValid = false,
                    Status = LicenseStatus.Unlicensed,
                    UsedLocalFallback = true,
                    FailureReason = failureReason,
                };
            }

            var licenses = await unitOfWork.Licenses.GetAllAsync(cancellationToken);
            var license = licenses.FirstOrDefault();

            if (license == null)
            {
                return new LicenseValidationResultDto
                {
                    IsValid = false,
                    Status = LicenseStatus.Unlicensed,
                    UsedLocalFallback = true,
                    FailureReason = failureReason,
                };
            }

            var effectiveStatus = license.Status;
            var isValid = effectiveStatus == LicenseStatus.Active;

            if (license.ExpiresAt.HasValue && license.ExpiresAt.Value <= DateTime.UtcNow)
            {
                effectiveStatus = LicenseStatus.Expired;
                isValid = false;
            }

            return new LicenseValidationResultDto
            {
                IsValid = isValid,
                Status = effectiveStatus,
                DeviceToken = license.DeviceToken ?? string.Empty,
                ExpiresAt = license.ExpiresAt,
                UsedLocalFallback = true,
                FailureReason = failureReason,
            };
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Yerel lisans kontrolu tamamlanamadi.");

            return new LicenseValidationResultDto
            {
                IsValid = false,
                Status = LicenseStatus.Unlicensed,
                UsedLocalFallback = true,
                FailureReason = failureReason,
            };
        }
    }

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
            _logger.LogDebug(ex, "Cihaz API key yerel veritabanindan okunamadi.");
        }

        return _cachedApiKey;
    }

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
            _logger.LogDebug(ex, "Cihaz UUID'si yerel veritabanindan okunamadi.");
        }

        return _cachedDeviceUuid ?? string.Empty;
    }

    private static LicenseStatus MapLicenseStatus(string statusText, HttpStatusCode httpStatusCode)
    {
        return statusText.Trim().ToLowerInvariant() switch
        {
            "active" => LicenseStatus.Active,
            "suspended" => LicenseStatus.Suspended,
            "devicemismatch" => LicenseStatus.Suspended,
            "devicelimit" => LicenseStatus.Suspended,
            "invalid" => LicenseStatus.Expired,
            "expired" => LicenseStatus.Expired,
            "unauthorized" => LicenseStatus.Unlicensed,
            _ when httpStatusCode == HttpStatusCode.Conflict => LicenseStatus.Suspended,
            _ when httpStatusCode == HttpStatusCode.Forbidden => LicenseStatus.Expired,
            _ => LicenseStatus.Unlicensed,
        };
    }

    private static DateTime? ParseDateTime(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        if (DateTime.TryParse(
                value,
                CultureInfo.InvariantCulture,
                DateTimeStyles.AssumeUniversal | DateTimeStyles.AdjustToUniversal,
                out var parsedUtc))
        {
            return parsedUtc;
        }

        if (DateTime.TryParse(value, out var parsed))
        {
            return parsed;
        }

        return null;
    }

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

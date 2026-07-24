using System;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.Infrastructure.Services;

public class NetworkMonitoringService : INetworkMonitoringService
{
    private readonly IHttpClientFactory _httpClientFactory;
    private readonly IOptions<ServiceOptions> _options;
    private readonly ILogger<NetworkMonitoringService> _logger;

    private bool _isOnline = true;
    public bool IsOnline => _isOnline;

    public event EventHandler<NetworkStatusChangedEventArgs>? OnlineStatusChanged;

    public NetworkMonitoringService(
        IHttpClientFactory httpClientFactory,
        IOptions<ServiceOptions> options,
        ILogger<NetworkMonitoringService> logger)
    {
        _httpClientFactory = httpClientFactory;
        _options = options;
        _logger = logger;
    }

    public async Task<bool> CheckConnectivityAsync(CancellationToken cancellationToken = default)
    {
        bool currentOnlineState = false;
        string statusMessage = string.Empty;

        try
        {
            var targetUrl = !string.IsNullOrWhiteSpace(_options.Value.ApiUrl)
                ? $"{_options.Value.ApiUrl.TrimEnd('/')}/v1/device/ping"
                : "https://adisyon.synaptropic.com/api/v1/device/ping";

            using var httpClient = _httpClientFactory.CreateClient();
            httpClient.Timeout = TimeSpan.FromSeconds(4);

            // HEAD or GET ping request
            using var request = new HttpRequestMessage(HttpMethod.Head, targetUrl);
            var response = await httpClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, cancellationToken);

            currentOnlineState = response.IsSuccessStatusCode || response.StatusCode == System.Net.HttpStatusCode.Unauthorized || response.StatusCode == System.Net.HttpStatusCode.Forbidden;
            statusMessage = currentOnlineState ? "İnternet ve Sunucu Erişilebilir" : $"Sunucu Yanıt Vermedi: {(int)response.StatusCode}";
        }
        catch (Exception ex)
        {
            currentOnlineState = false;
            statusMessage = $"İnternet Bağlantı Hatası: {ex.Message}";
        }

        if (currentOnlineState != _isOnline)
        {
            _isOnline = currentOnlineState;
            _logger.LogInformation("📡 Ağ Bağlantı Durumu Değişti: Online = {IsOnline} ({Message})", _isOnline, statusMessage);
            OnlineStatusChanged?.Invoke(this, new NetworkStatusChangedEventArgs(_isOnline, statusMessage));
        }

        return _isOnline;
    }
}

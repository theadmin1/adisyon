using System;
using System.Net.Http;
using System.Net.NetworkInformation;
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
            if (!NetworkInterface.GetIsNetworkAvailable())
            {
                currentOnlineState = false;
                statusMessage = "Ağ bağdaştırıcısı aktif değil.";
            }
            else
            {
                var targetUrl = !string.IsNullOrWhiteSpace(_options.Value.AdisyonWebUrl)
                    ? _options.Value.AdisyonWebUrl
                    : "https://adisyon.synaptropic.com/login";

                using var httpClient = _httpClientFactory.CreateClient();
                httpClient.Timeout = TimeSpan.FromSeconds(3);

                using var request = new HttpRequestMessage(HttpMethod.Get, targetUrl);
                var response = await httpClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, cancellationToken);

                // Herhangi bir HTTP yanıtı geldiyse (200, 302, 404 vs.) internet ve sunucu erişilebilir demektir
                currentOnlineState = true;
                statusMessage = "İnternet ve Sunucu Erişilebilir";
            }
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

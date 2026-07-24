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
    private int _failedCheckCount = 0;
    private NetworkOverrideMode _overrideMode = NetworkOverrideMode.Automatic;

    public bool IsOnline => _isOnline;

    public NetworkOverrideMode OverrideMode
    {
        get => _overrideMode;
        set => _overrideMode = value;
    }

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
        bool isRequestOk = false;
        string statusMessage = string.Empty;

        if (_overrideMode == NetworkOverrideMode.ForceOffline)
        {
            isRequestOk = false;
            statusMessage = "Çevrimdışı Test Modu Aktif (Admin Paneli Tarafından Zorlandı)";
        }
        else if (_overrideMode == NetworkOverrideMode.ForceOnline)
        {
            isRequestOk = true;
            statusMessage = "Online Test Modu Aktif (Admin Paneli Tarafından Zorlandı)";
        }
        else
        {
            try
            {
                if (!NetworkInterface.GetIsNetworkAvailable())
                {
                    isRequestOk = false;
                    statusMessage = "Ağ bağdaştırıcısı aktif değil.";
                }
                else
                {
                    var targetUrl = !string.IsNullOrWhiteSpace(_options.Value.AdisyonWebUrl)
                        ? _options.Value.AdisyonWebUrl
                        : "https://adisyon.synaptropic.com/login";

                    using var httpClient = _httpClientFactory.CreateClient();
                    httpClient.Timeout = TimeSpan.FromSeconds(4);

                    using var request = new HttpRequestMessage(HttpMethod.Get, targetUrl);
                    var response = await httpClient.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, cancellationToken);

                    // Herhangi bir HTTP yanıtı geldiyse internet bağlantısı aktiftir
                    isRequestOk = true;
                    statusMessage = "İnternet ve Sunucu Erişilebilir";
                }
            }
            catch (Exception ex)
            {
                isRequestOk = false;
                statusMessage = $"İnternet Bağlantı Hatası: {ex.Message}";
            }
        }

        if (isRequestOk)
        {
            _failedCheckCount = 0;
            if (!_isOnline)
            {
                _isOnline = true;
                _logger.LogInformation("📡 Ağ Bağlantı Durumu Değişti: Online = True ({Message})", statusMessage);
                OnlineStatusChanged?.Invoke(this, new NetworkStatusChangedEventArgs(true, statusMessage));
            }
        }
        else
        {
            _failedCheckCount++;
            // Manuel zorlama durumunda tek denemede, otomatikte 2 denemede geçiş yap
            int threshold = _overrideMode == NetworkOverrideMode.ForceOffline ? 1 : 2;

            if (_isOnline && _failedCheckCount >= threshold)
            {
                _isOnline = false;
                _logger.LogWarning("📡 Ağ Bağlantı Durumu Değişti: Online = False ({FailedCount} üst üste başarısız kontrol - {Message})", _failedCheckCount, statusMessage);
                OnlineStatusChanged?.Invoke(this, new NetworkStatusChangedEventArgs(false, statusMessage));
            }
        }

        return _isOnline;
    }
}

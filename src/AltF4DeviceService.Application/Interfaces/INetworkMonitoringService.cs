using System;
using System.Threading;
using System.Threading.Tasks;

namespace AltF4DeviceService.Application.Interfaces;

public enum NetworkOverrideMode
{
    Automatic = 0,
    ForceOnline = 1,
    ForceOffline = 2
}

public class NetworkStatusChangedEventArgs : EventArgs
{
    public bool IsOnline { get; }
    public string Message { get; }

    public NetworkStatusChangedEventArgs(bool isOnline, string message)
    {
        IsOnline = isOnline;
        Message = message;
    }
}

/// <summary>
/// Ağ bağlantısı ve sunucu erişilebilirliğini periyodik olarak kontrol eden servis arayüzü.
/// </summary>
public interface INetworkMonitoringService
{
    bool IsOnline { get; }
    NetworkOverrideMode OverrideMode { get; set; }
    event EventHandler<NetworkStatusChangedEventArgs>? OnlineStatusChanged;
    Task<bool> CheckConnectivityAsync(CancellationToken cancellationToken = default);
}

using System;
using System.Threading;
using System.Threading.Tasks;

namespace AltF4DeviceService.Application.Interfaces;

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
    event EventHandler<NetworkStatusChangedEventArgs>? OnlineStatusChanged;
    Task<bool> CheckConnectivityAsync(CancellationToken cancellationToken = default);
}

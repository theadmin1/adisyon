namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// ÖKC terminaline giden fiziksel taşıma katmanı (TCP/IP, seri port, SDK).
///
/// Protokolden (GMP-3) bağımsızdır: yalnızca bayt gönderir/alır. Böylece
/// bağlantı tipi değiştiğinde protokol kodu, protokol değiştiğinde bağlantı
/// kodu etkilenmez.
/// </summary>
public interface IPosTransport : IDisposable
{
    /// <summary>Bağlantının açık olup olmadığı.</summary>
    bool IsConnected { get; }

    /// <summary>İnsan tarafından okunabilir hedef tanımı (log/hata mesajları için).</summary>
    string Description { get; }

    Task ConnectAsync(CancellationToken cancellationToken = default);

    Task DisconnectAsync();

    /// <summary>Terminale ham bayt gönderir.</summary>
    Task SendAsync(byte[] data, CancellationToken cancellationToken = default);

    /// <summary>
    /// Terminalden tam bir yanıt çerçevesi okur.
    /// Kart işlemleri müşteri etkileşimi gerektirdiği için uzun sürebilir;
    /// zaman aşımı çağıran tarafından belirlenir.
    /// </summary>
    Task<byte[]> ReceiveAsync(TimeSpan timeout, CancellationToken cancellationToken = default);
}

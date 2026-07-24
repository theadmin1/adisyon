using System.Net.Sockets;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// ÖKC terminaline TCP/IP (Ethernet / WiFi) üzerinden bağlanan taşıma katmanı.
/// Yeni nesil ÖKC'lerde en yaygın bağlantı tipidir.
/// </summary>
public class TcpPosTransport : IPosTransport
{
    private readonly string _host;
    private readonly int _port;
    private readonly ILogger _logger;

    private TcpClient? _client;
    private NetworkStream? _stream;

    public TcpPosTransport(string host, int port, ILogger logger)
    {
        _host = host;
        _port = port;
        _logger = logger;
    }

    public bool IsConnected => _client?.Connected == true;

    public string Description => $"TCP {_host}:{_port}";

    public async Task ConnectAsync(CancellationToken cancellationToken = default)
    {
        if (IsConnected)
        {
            return;
        }

        _client = new TcpClient
        {
            NoDelay = true,   // Fiş komutları küçük; Nagle gecikmesi istemiyoruz
        };

        // Bağlantı için makul bir üst sınır: terminal kapalıysa kasiyer
        // dakikalarca beklememeli.
        using var connectCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        connectCts.CancelAfter(TimeSpan.FromSeconds(10));

        try
        {
            await _client.ConnectAsync(_host, _port, connectCts.Token);
            _stream = _client.GetStream();

            _logger.LogInformation("ÖKC terminaline bağlanıldı: {Target}", Description);
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"ÖKC terminaline bağlanılamadı ({Description}): bağlantı zaman aşımına uğradı.");
        }
    }

    public Task DisconnectAsync()
    {
        try
        {
            _stream?.Dispose();
            _client?.Close();
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "ÖKC bağlantısı kapatılırken hata oluştu.");
        }
        finally
        {
            _stream = null;
            _client = null;
        }

        return Task.CompletedTask;
    }

    public async Task SendAsync(byte[] data, CancellationToken cancellationToken = default)
    {
        if (_stream == null)
        {
            throw new InvalidOperationException("ÖKC bağlantısı açık değil.");
        }

        await _stream.WriteAsync(data, cancellationToken);
        await _stream.FlushAsync(cancellationToken);
    }

    public async Task<byte[]> ReceiveAsync(TimeSpan timeout, CancellationToken cancellationToken = default)
    {
        if (_stream == null)
        {
            throw new InvalidOperationException("ÖKC bağlantısı açık değil.");
        }

        using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        timeoutCts.CancelAfter(timeout);

        var buffer = new byte[4096];
        using var received = new MemoryStream();

        try
        {
            // Terminal yanıtı birden fazla pakette gelebilir; en az bir okuma
            // yapıp ardından hazır veri kalmayana kadar toplarız.
            do
            {
                int read = await _stream.ReadAsync(buffer, timeoutCts.Token);

                if (read == 0)
                {
                    break; // Karşı taraf bağlantıyı kapattı
                }

                received.Write(buffer, 0, read);
            }
            while (_stream.DataAvailable);
        }
        catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
        {
            throw new TimeoutException($"ÖKC terminali yanıt vermedi ({Description}, {timeout.TotalSeconds:0} sn).");
        }

        return received.ToArray();
    }

    public void Dispose()
    {
        DisconnectAsync().GetAwaiter().GetResult();
        GC.SuppressFinalize(this);
    }
}

using System.IO.Ports;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// ÖKC terminaline seri port (RS232 / USB-Serial) üzerinden bağlanan taşıma katmanı.
/// Eski kurulumlarda ve bazı masaüstü ÖKC modellerinde kullanılır.
/// </summary>
public class SerialPosTransport : IPosTransport
{
    private readonly string _portName;
    private readonly int _baudRate;
    private readonly ILogger _logger;

    private SerialPort? _port;

    public SerialPosTransport(string portName, int baudRate, ILogger logger)
    {
        _portName = portName;
        _baudRate = baudRate;
        _logger = logger;
    }

    public bool IsConnected => _port?.IsOpen == true;

    public string Description => $"Seri {_portName} @ {_baudRate}";

    public Task ConnectAsync(CancellationToken cancellationToken = default)
    {
        if (IsConnected)
        {
            return Task.CompletedTask;
        }

        _port = new SerialPort(_portName, _baudRate, Parity.None, 8, StopBits.One)
        {
            Handshake = Handshake.None,
            ReadTimeout = 5000,
            WriteTimeout = 5000,
            DtrEnable = true,
            RtsEnable = true,
        };

        _port.Open();
        _port.DiscardInBuffer();
        _port.DiscardOutBuffer();

        _logger.LogInformation("ÖKC terminaline bağlanıldı: {Target}", Description);

        return Task.CompletedTask;
    }

    public Task DisconnectAsync()
    {
        try
        {
            if (_port?.IsOpen == true)
            {
                _port.Close();
            }

            _port?.Dispose();
        }
        catch (Exception ex)
        {
            _logger.LogDebug(ex, "Seri port kapatılırken hata oluştu.");
        }
        finally
        {
            _port = null;
        }

        return Task.CompletedTask;
    }

    public async Task SendAsync(byte[] data, CancellationToken cancellationToken = default)
    {
        if (_port is not { IsOpen: true })
        {
            throw new InvalidOperationException("Seri port açık değil.");
        }

        await _port.BaseStream.WriteAsync(data, cancellationToken);
        await _port.BaseStream.FlushAsync(cancellationToken);
    }

    public async Task<byte[]> ReceiveAsync(TimeSpan timeout, CancellationToken cancellationToken = default)
    {
        if (_port is not { IsOpen: true })
        {
            throw new InvalidOperationException("Seri port açık değil.");
        }

        var deadline = DateTime.UtcNow + timeout;
        using var received = new MemoryStream();
        var buffer = new byte[1024];

        while (DateTime.UtcNow < deadline)
        {
            cancellationToken.ThrowIfCancellationRequested();

            if (_port.BytesToRead > 0)
            {
                int read = _port.Read(buffer, 0, Math.Min(buffer.Length, _port.BytesToRead));
                received.Write(buffer, 0, read);

                // Kısa bir sessizlik penceresi: çerçevenin tamamının gelmesini bekle.
                await Task.Delay(50, cancellationToken);

                if (_port.BytesToRead == 0)
                {
                    return received.ToArray();
                }

                continue;
            }

            if (received.Length > 0)
            {
                return received.ToArray();
            }

            await Task.Delay(50, cancellationToken);
        }

        if (received.Length > 0)
        {
            return received.ToArray();
        }

        throw new TimeoutException($"ÖKC terminali yanıt vermedi ({Description}, {timeout.TotalSeconds:0} sn).");
    }

    public void Dispose()
    {
        DisconnectAsync().GetAwaiter().GetResult();
        GC.SuppressFinalize(this);
    }
}

using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// Bağlantı tipine göre gerçek terminal ile simülatör arasında seçim yapar.
/// Simülatör YALNIZCA bağlantı tipi açıkça "simulator" seçildiğinde döner.
/// </summary>
public class PosTerminalResolver : IPosTerminalResolver
{
    private const string SimulatorConnectionType = "simulator";

    private readonly IPosConfigService _configService;
    private readonly PosTerminalService _realTerminal;
    private readonly SimulatedPosTerminalService _simulator;
    private readonly ILogger<PosTerminalResolver> _logger;

    public PosTerminalResolver(
        IPosConfigService configService,
        PosTerminalService realTerminal,
        SimulatedPosTerminalService simulator,
        ILogger<PosTerminalResolver> logger)
    {
        _configService = configService;
        _realTerminal = realTerminal;
        _simulator = simulator;
        _logger = logger;
    }

    public async Task<IPosTerminalService> ResolveAsync(CancellationToken cancellationToken = default)
    {
        if (await IsSimulatorAsync(cancellationToken))
        {
            _logger.LogWarning("⚠️ ÖKC SİMÜLATÖR modunda çalışıyor — gerçek tahsilat yapılmaz.");
            return _simulator;
        }

        return _realTerminal;
    }

    public async Task<bool> IsSimulatorAsync(CancellationToken cancellationToken = default)
    {
        var config = await _configService.GetAsync(cancellationToken);

        return string.Equals(config.ConnectionType, SimulatorConnectionType, StringComparison.OrdinalIgnoreCase);
    }
}

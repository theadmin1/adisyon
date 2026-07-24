namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// Cihaza bağlı ÖKC terminalinin yerel yapılandırması.
/// Yazıcıda olduğu gibi bu bilgi CİHAZA aittir; merkezi sunucu hangi terminalin
/// takılı olduğunu bilemez.
/// </summary>
public class PosConfigDto
{
    /// <summary>ÖKC entegrasyonu bu cihazda etkin mi.</summary>
    public bool IsEnabled { get; set; }

    /// <summary>tcp | serial | simulator</summary>
    public string ConnectionType { get; set; } = "tcp";

    public string Host { get; set; } = string.Empty;
    public int Port { get; set; } = 9100;

    public string SerialPort { get; set; } = "COM1";
    public int BaudRate { get; set; } = 9600;

    /// <summary>
    /// Terminal yanıtı için beklenecek süre (saniye).
    /// Müşteri kart okutup PIN girecek; kısa tutulmamalı.
    /// </summary>
    public int TimeoutSeconds { get; set; } = 120;

    /// <summary>Terminal markası/protokolü (şu an yalnızca GMP-3).</summary>
    public string Protocol { get; set; } = "gmp3";

    public string Describe() => ConnectionType?.ToLowerInvariant() switch
    {
        "serial" => $"Seri {SerialPort} @ {BaudRate}",
        "simulator" => "Simülatör (test modu)",
        _ => $"TCP {Host}:{Port}",
    };
}

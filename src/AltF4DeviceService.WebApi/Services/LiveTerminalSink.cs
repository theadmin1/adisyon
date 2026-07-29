using System.Collections.Concurrent;
using Serilog.Core;
using Serilog.Events;

namespace AltF4DeviceService.WebApi.Services;

/// <summary>
/// Serilog canlı log akış yakalayıcısı (In-Memory Sink).
/// Arka planda çalışan C# servisindeki anlık olayları (fiş yazdırıldı, hata oluştu, ağ kesildi vb.)
/// hiçbir dosyaya kaydetmeden anında bellek üzerinden Admin Paneli terminaline aktarır.
/// </summary>
public class LiveTerminalSink : ILogEventSink
{
    private static readonly ConcurrentQueue<string> _buffer = new();
    public static event Action<string>? OnLogEmitted;

    public void Emit(LogEvent logEvent)
    {
        string levelTag = logEvent.Level switch
        {
            LogEventLevel.Error or LogEventLevel.Fatal => "HATA",
            LogEventLevel.Warning => "UYARI",
            LogEventLevel.Information => "BİLGİ",
            _ => "SERVIS"
        };

        string message = logEvent.RenderMessage();
        if (logEvent.Exception != null)
        {
            message += $" | Hata Detayı: {logEvent.Exception.Message}";
        }

        string time = logEvent.Timestamp.ToString("HH:mm:ss");
        string formatted = $"[{time}] [{levelTag}] {message}";

        _buffer.Enqueue(formatted);
        while (_buffer.Count > 120)
        {
            _buffer.TryDequeue(out _);
        }

        OnLogEmitted?.Invoke(formatted);
    }

    /// <summary>
    /// Manuel anlık canlı olay eklemek için (Örn: Fiş Gönderildi).
    /// </summary>
    public static void LogManual(string tag, string message)
    {
        string time = DateTime.Now.ToString("HH:mm:ss");
        string formatted = $"[{time}] [{tag}] {message}";

        _buffer.Enqueue(formatted);
        while (_buffer.Count > 120)
        {
            _buffer.TryDequeue(out _);
        }

        OnLogEmitted?.Invoke(formatted);
    }

    public static IEnumerable<string> GetLogs() => _buffer.ToArray();
    
    public static void Clear()
    {
        while (_buffer.TryDequeue(out _)) { }
    }
}

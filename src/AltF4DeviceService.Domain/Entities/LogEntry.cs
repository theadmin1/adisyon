namespace AltF4DeviceService.Domain.Entities;

/// <summary>
/// Sistem ve servis loglarının SQLite veritabanı tablosunda saklanması için entity model.
/// </summary>
public class LogEntry
{
    /// <summary>
    /// Birincil anahtar.
    /// </summary>
    public int Id { get; set; }

    /// <summary>
    /// Log oluşma zamanı.
    /// </summary>
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;

    /// <summary>
    /// Log seviyesi (Information, Warning, Error, Critical vb.).
    /// </summary>
    public string Level { get; set; } = string.Empty;

    /// <summary>
    /// Log mesajı.
    /// </summary>
    public string Message { get; set; } = string.Empty;

    /// <summary>
    /// Varsa hataya ilişkin Exception bilgisi ve StackTrace.
    /// </summary>
    public string? Exception { get; set; }

    /// <summary>
    /// Ek aralık/bağlam verileri (JSON veya string formatında).
    /// </summary>
    public string? Properties { get; set; }
}

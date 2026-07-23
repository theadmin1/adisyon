namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Windows bildirim alanı (System Tray) üzerinden kullanıcıya masaüstü bildirimi gösterir.
/// Windows 10/11'de balon bildirimleri sistem tarafından native "toast" olarak gösterilir.
/// </summary>
public interface INotificationService
{
    /// <summary>
    /// Masaüstü bildirimi gösterir. Tepsi ikonu henüz hazır değilse sessizce yok sayılır.
    /// </summary>
    /// <param name="title">Bildirim başlığı.</param>
    /// <param name="message">Bildirim içeriği.</param>
    /// <param name="level">Bildirim türü (ikonu belirler).</param>
    void Show(string title, string message, NotificationLevel level = NotificationLevel.Info);
}

public enum NotificationLevel
{
    Info,
    Success,
    Warning,
    Error,
}

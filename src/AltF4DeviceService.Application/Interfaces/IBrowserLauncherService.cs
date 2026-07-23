namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Dahili tarayıcı penceresini arka plandaki servisten açmak/ön plana getirmek için kullanılan arayüz.
/// </summary>
public interface IBrowserLauncherService
{
    /// <summary>
    /// Dahili Chromium tarayıcı penceresini açar veya mevcutsa ön plana getirir.
    /// </summary>
    void OpenBrowser();
}

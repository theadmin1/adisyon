namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Dahili tarayıcı penceresini ve lisans engelleme durumunu yönetmek için kullanılan arayüz.
/// </summary>
public interface IBrowserLauncherService
{
    /// <summary>
    /// Dahili Chromium tarayıcı penceresini açar veya mevcutsa ön plana getirir.
    /// </summary>
    void OpenBrowser();

    /// <summary>
    /// Lisans durumuna göre tarayıcıyı kilitler veya açar.
    /// </summary>
    void UpdateLicenseState(bool isValid, string reason = "");
}

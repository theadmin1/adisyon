using System.Diagnostics;
using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.WebApi.Forms;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using WinFormsApp = System.Windows.Forms.Application;

namespace AltF4DeviceService.WebApi.Tray;

/// <summary>
/// Windows uygulama çubuğunda (System Tray / Bildirim Alanı) çalışan tepsi ikonu ve durum bildirimi servisi.
/// </summary>
[SupportedOSPlatform("windows")]
public class SystemTrayService : IHostedService, IBrowserLauncherService
{
    private Thread? _trayThread;
    private NotifyIcon? _notifyIcon;
    private BrowserForm? _browserForm;
    private AdminPanelForm? _adminPanelForm;
    private readonly IHostApplicationLifetime _appLifetime;
    private readonly IOptions<ServiceOptions> _options;
    private readonly IServiceProvider _serviceProvider;
    private readonly ILogger<SystemTrayService> _logger;

    public SystemTrayService(
        IHostApplicationLifetime appLifetime,
        IOptions<ServiceOptions> options,
        IServiceProvider serviceProvider,
        ILogger<SystemTrayService> logger)
    {
        _appLifetime = appLifetime;
        _options = options;
        _serviceProvider = serviceProvider;
        _logger = logger;
    }

    public Task StartAsync(CancellationToken cancellationToken)
    {
        if (!OperatingSystem.IsWindows())
            return Task.CompletedTask;

        _trayThread = new Thread(() =>
        {
            try
            {
                WinFormsApp.EnableVisualStyles();
                WinFormsApp.SetCompatibleTextRenderingDefault(false);

                var contextMenu = new ContextMenuStrip();

                // 1. Durum Başlığı (Aktif / Running)
                var titleItem = new ToolStripMenuItem("🟢 AltF4 Device Service: Aktif (Running)")
                {
                    Enabled = false,
                    Font = new Font(Control.DefaultFont, FontStyle.Bold)
                };
                contextMenu.Items.Add(titleItem);
                contextMenu.Items.Add(new ToolStripSeparator());

                // 2. Dahili Özel Tarayıcı Aç Bağlantısı
                var adisyonUrl = !string.IsNullOrWhiteSpace(_options.Value.AdisyonWebUrl)
                    ? _options.Value.AdisyonWebUrl
                    : $"http://127.0.0.1:{_options.Value.Port}/health";

                var openApiItem = new ToolStripMenuItem("🖥️ Adisyon Tarayıcısını Aç (Embedded Browser)", null, (s, e) =>
                {
                    OpenEmbeddedBrowser(adisyonUrl);
                });
                contextMenu.Items.Add(openApiItem);

                // 3. Admin Yetki & Yönetim Paneli (Şifre Korumalı)
                var openAdminItem = new ToolStripMenuItem("⚙️ Admin Yetki & Yönetim Paneli", null, (s, e) =>
                {
                    if (AuthenticateAdmin())
                    {
                        OpenAdminPanel();
                    }
                });
                contextMenu.Items.Add(openAdminItem);

                _notifyIcon = new NotifyIcon
                {
                    Icon = SystemIcons.Application,
                    Text = "AltF4 Device Service - Aktif / Running",
                    ContextMenuStrip = contextMenu,
                    Visible = true
                };

                // İkona çift tıklandığında dahili özel tarayıcı açılır
                _notifyIcon.DoubleClick += (s, e) =>
                {
                    OpenEmbeddedBrowser(adisyonUrl);
                };

                // Başlangıç baloncuk bildirimi
                _notifyIcon.ShowBalloonTip(3000, "AltF4 Device Service", $"Servis aktif ve çalışıyor. (Port: {_options.Value.Port})", ToolTipIcon.Info);

                // --- OTOMATİK TARAYICI AÇILIŞI ---
                if (_options.Value.AutoOpenBrowser)
                {
                    _logger.LogInformation("AutoOpenBrowser aktif. Otomatik dahili tarayıcı açılıyor: {Url}", adisyonUrl);
                    OpenEmbeddedBrowser(adisyonUrl);
                }

                WinFormsApp.Run();
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "System Tray başlatılırken hata oluştu.");
            }
        });

        _trayThread.SetApartmentState(ApartmentState.STA);
        _trayThread.IsBackground = true;
        _trayThread.Start();

        return Task.CompletedTask;
    }

    /// <summary>
    /// IBrowserLauncherService arayüzü uygulaması. Dışarıdan veya endpoint'lerden çağrılarak tarayıcıyı açar.
    /// </summary>
    public void OpenBrowser()
    {
        var adisyonUrl = !string.IsNullOrWhiteSpace(_options.Value.AdisyonWebUrl)
            ? _options.Value.AdisyonWebUrl
            : $"http://127.0.0.1:{_options.Value.Port}/health";

        if (_browserForm != null && !_browserForm.IsDisposed)
        {
            if (_browserForm.InvokeRequired)
            {
                _browserForm.Invoke(() => OpenEmbeddedBrowser(adisyonUrl));
            }
            else
            {
                OpenEmbeddedBrowser(adisyonUrl);
            }
        }
        else if (_notifyIcon?.ContextMenuStrip != null && _notifyIcon.ContextMenuStrip.InvokeRequired)
        {
            _notifyIcon.ContextMenuStrip.Invoke(() => OpenEmbeddedBrowser(adisyonUrl));
        }
        else
        {
            OpenEmbeddedBrowser(adisyonUrl);
        }
    }

    private void OpenEmbeddedBrowser(string url)
    {
        try
        {
            var restrictions = _options.Value.BrowserRestrictions ?? new BrowserRestrictionOptions();

            // Açılıştan önce lisans kontrolü yapalım
            bool isLicenseValid = true;
            try
            {
                using var scope = _serviceProvider.CreateScope();
                var licenseService = scope.ServiceProvider.GetService<ILicenseService>();
                if (licenseService != null)
                {
                    isLicenseValid = licenseService.VerifyAndUpdateLicenseAsync().GetAwaiter().GetResult();
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning(ex, "Açılışta lisans kontrolü yapılırken uyarı alındı.");
            }

            if (_browserForm == null || _browserForm.IsDisposed)
            {
                _browserForm = new BrowserForm(url, restrictions);
                if (!isLicenseValid)
                {
                    _browserForm.IsBlocked = true;
                }
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.Show();
                _browserForm.WindowState = FormWindowState.Maximized;
            }
            else
            {
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.BringToFront();
                _browserForm.Activate();
            }

            if (!isLicenseValid)
            {
                _logger.LogWarning("Lisans doğrulanamadı veya pasif! Tarayıcı kilit ekranına yönlendiriliyor.");
                var warningMsg = "Pasife Alınmıştır veya Geçersizdir";
                _browserForm.ShowLicenseBlockedScreen(warningMsg);
                ShowWarningPopup(warningMsg);
            }
            else
            {
                _logger.LogInformation("Lisans aktif ve doğrulandı. Dahili tarayıcı ekranı açılıyor: {Url}", url);
                _browserForm.RestoreBrowser();
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.BringToFront();
                _browserForm.Activate();
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Dahili özel tarayıcı açılırken hata oluştu.");
        }
    }

    private bool AuthenticateAdmin()
    {
        try
        {
            using var loginForm = new AdminLoginForm(_options);
            return loginForm.ShowDialog() == DialogResult.OK;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Admin doğrulama penceresi açılırken hata oluştu.");
            return false;
        }
    }

    private DateTime _lastPopupTime = DateTime.MinValue;

    public void UpdateLicenseState(bool isValid, string reason = "")
    {
        try
        {
            var warningMsg = string.IsNullOrWhiteSpace(reason) ? "Pasife Alınmıştır veya Geçersizdir" : reason;

            if (!isValid)
            {
                _logger.LogWarning("Lisans pasife alındı veya geçersiz! Kilit ekranı ve pop-up penceresi açılıyor.");
                
                if (_browserForm != null && !_browserForm.IsDisposed)
                {
                    _browserForm.ShowLicenseBlockedScreen(warningMsg);
                }

                if ((DateTime.Now - _lastPopupTime).TotalSeconds > 10)
                {
                    _lastPopupTime = DateTime.Now;
                    ShowWarningPopup(warningMsg);
                }
            }
            else
            {
                if (_browserForm != null && !_browserForm.IsDisposed)
                {
                    _browserForm.RestoreBrowser();
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Lisans kilitleme durumu güncellenirken hata oluştu.");
        }
    }

    private void ShowWarningPopup(string reason)
    {
        try
        {
            _logger.LogWarning("Lisans Pop-Up İkaz Penceresi Tetikleniyor: {Reason}", reason);

            var warningThread = new Thread(() =>
            {
                try
                {
                    WinFormsApp.EnableVisualStyles();
                    using var warningForm = new LicenseWarningForm(reason);
                    warningForm.TopMost = true;
                    warningForm.StartPosition = FormStartPosition.CenterScreen;
                    WinFormsApp.Run(warningForm);
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, "Pop-up thread çalıştırılırken hata oluştu.");
                }
            });

            warningThread.SetApartmentState(ApartmentState.STA);
            warningThread.IsBackground = true;
            warningThread.Start();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Uyarı penceresi gösterilirken hata oluştu.");
        }
    }

    private void OpenAdminPanel()
    {
        try
        {
            if (_adminPanelForm == null || _adminPanelForm.IsDisposed)
            {
                _adminPanelForm = new AdminPanelForm(_serviceProvider, _options);
                _adminPanelForm.Show();
            }
            else
            {
                _adminPanelForm.BringToFront();
                _adminPanelForm.Activate();
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Admin Paneli açılırken hata oluştu.");
        }
    }

    public Task StopAsync(CancellationToken cancellationToken)
    {
        if (_notifyIcon != null)
        {
            _notifyIcon.Visible = false;
            _notifyIcon.Dispose();
        }
        if (_browserForm != null && !_browserForm.IsDisposed)
        {
            _browserForm.Close();
        }
        return Task.CompletedTask;
    }
}

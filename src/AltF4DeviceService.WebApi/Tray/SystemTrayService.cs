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
    private SynchronizationContext? _uiSyncContext;
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

                _uiSyncContext = new WindowsFormsSynchronizationContext();
                SynchronizationContext.SetSynchronizationContext(_uiSyncContext);

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

                // --- OTOMATİK TARAYICI AÇILIŞI (WinForms Message Loop Başladıktan Sonra) ---
                EventHandler? onIdle = null;
                onIdle = (s, e) =>
                {
                    WinFormsApp.Idle -= onIdle;
                    if (_options.Value.AutoOpenBrowser)
                    {
                        _logger.LogInformation("AutoOpenBrowser aktif. Otomatik dahili tarayıcı açılıyor: {Url}", adisyonUrl);
                        OpenEmbeddedBrowser(adisyonUrl);
                    }
                };
                WinFormsApp.Idle += onIdle;

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
        else if (_uiSyncContext != null)
        {
            _uiSyncContext.Post(_ => OpenEmbeddedBrowser(adisyonUrl), null);
        }
        else
        {
            OpenEmbeddedBrowser(adisyonUrl);
        }
    }

    private static bool _isWarningPopupActive = false;
    private static readonly object _warningPopupLock = new object();
    private bool _isLicenseValid = true;

    private async void OpenEmbeddedBrowser(string url)
    {
        try
        {
            var restrictions = _options.Value.BrowserRestrictions ?? new BrowserRestrictionOptions();

            if (!_isLicenseValid)
            {
                _logger.LogWarning("Lisans pasif/geçersiz! Tarayıcı açılmıyor, uygulama sonlandırılıyor.");
                ShowWarningPopup("Lisansınız Pasife Alınmıştır veya Geçersizdir");
                _appLifetime.StopApplication();
                return;
            }

            string restaurantId = _options.Value.RestaurantLoginId;
            string restaurantPassword = _options.Value.RestaurantLoginPassword;
            bool autoLogin = _options.Value.AutoLoginEnabled;

            try
            {
                using var scope = _serviceProvider.CreateScope();
                var settingService = scope.ServiceProvider.GetService<ISettingService>();
                if (settingService != null)
                {
                    restaurantId = await settingService.GetSettingValueAsync("RestaurantLoginId", restaurantId);
                    restaurantPassword = await settingService.GetSettingValueAsync("RestaurantLoginPassword", restaurantPassword);
                    var autoLoginStr = await settingService.GetSettingValueAsync("AutoLoginEnabled", autoLogin ? "true" : "false");
                    autoLogin = autoLoginStr.Equals("true", StringComparison.OrdinalIgnoreCase);
                }
            }
            catch { }

            // Lisans aktif -> Tarayıcıyı aç veya ön plana getir
            if (_browserForm == null || _browserForm.IsDisposed)
            {
                _browserForm = new BrowserForm(url, restrictions, restaurantId, restaurantPassword, autoLogin);
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.Show();
                _browserForm.WindowState = FormWindowState.Maximized;
            }

            _logger.LogInformation("Lisans aktif ve doğrulandı. Dahili tarayıcı ekranı açılıyor: {Url}", url);
            _browserForm.RestoreBrowser();
            _browserForm.WindowState = FormWindowState.Maximized;
            _browserForm.BringToFront();
            _browserForm.Activate();
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

    public void UpdateLicenseState(bool isValid, string reason = "")
    {
        _isLicenseValid = isValid;
        try
        {
            var warningMsg = string.IsNullOrWhiteSpace(reason) ? "Pasife Alınmıştır veya Geçersizdir" : reason;

            if (!isValid)
            {
                _logger.LogWarning("Lisans pasife alındı! Tarayıcı kapatılıyor, pop-up uyarısı veriliyor ve uygulama sonlandırılıyor.");
                
                if (_browserForm != null && !_browserForm.IsDisposed)
                {
                    try
                    {
                        if (_browserForm.InvokeRequired)
                        {
                            _browserForm.Invoke(() => { _browserForm.Close(); _browserForm = null; });
                        }
                        else
                        {
                            _browserForm.Close();
                            _browserForm = null;
                        }
                    }
                    catch { }
                }

                if (_notifyIcon != null)
                {
                    _notifyIcon.Visible = false;
                    _notifyIcon.Dispose();
                    _notifyIcon = null;
                }

                ShowWarningPopup(warningMsg);
                _appLifetime.StopApplication();
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
        lock (_warningPopupLock)
        {
            if (_isWarningPopupActive)
            {
                // Zaten ekranda açık bir uyarı pop-up penceresi var, ikincisini üst üste açma
                return;
            }
            _isWarningPopupActive = true;
        }

        try
        {
            _logger.LogWarning("Lisans Pop-Up İkaz Penceresi Açılıyor: {Reason}", reason);

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
                finally
                {
                    lock (_warningPopupLock)
                    {
                        _isWarningPopupActive = false;
                    }
                    Environment.Exit(0);
                }
            });

            warningThread.SetApartmentState(ApartmentState.STA);
            warningThread.IsBackground = false;
            warningThread.Start();
        }
        catch (Exception ex)
        {
            lock (_warningPopupLock)
            {
                _isWarningPopupActive = false;
            }
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
        try
        {
            if (_notifyIcon != null)
            {
                if (_notifyIcon.ContextMenuStrip != null && _notifyIcon.ContextMenuStrip.InvokeRequired)
                {
                    _notifyIcon.ContextMenuStrip.Invoke(() =>
                    {
                        if (_notifyIcon != null)
                        {
                            _notifyIcon.Visible = false;
                            _notifyIcon.Dispose();
                            _notifyIcon = null;
                        }
                    });
                }
                else
                {
                    _notifyIcon.Visible = false;
                    _notifyIcon.Dispose();
                    _notifyIcon = null;
                }
            }
        }
        catch { }

        try
        {
            if (_browserForm != null && !_browserForm.IsDisposed)
            {
                if (_browserForm.InvokeRequired)
                {
                    _browserForm.Invoke(() =>
                    {
                        if (_browserForm != null && !_browserForm.IsDisposed)
                        {
                            _browserForm.Close();
                            _browserForm = null;
                        }
                    });
                }
                else
                {
                    _browserForm.Close();
                    _browserForm = null;
                }
            }
        }
        catch { }

        try
        {
            if (_adminPanelForm != null && !_adminPanelForm.IsDisposed)
            {
                if (_adminPanelForm.InvokeRequired)
                {
                    _adminPanelForm.Invoke(() =>
                    {
                        if (_adminPanelForm != null && !_adminPanelForm.IsDisposed)
                        {
                            _adminPanelForm.Close();
                            _adminPanelForm = null;
                        }
                    });
                }
                else
                {
                    _adminPanelForm.Close();
                    _adminPanelForm = null;
                }
            }
        }
        catch { }

        return Task.CompletedTask;
    }
}

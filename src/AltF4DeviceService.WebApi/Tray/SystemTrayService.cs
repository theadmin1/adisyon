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
    private readonly IHostApplicationLifetime _appLifetime;
    private readonly IOptions<ServiceOptions> _options;
    private readonly ILogger<SystemTrayService> _logger;

    public SystemTrayService(
        IHostApplicationLifetime appLifetime,
        IOptions<ServiceOptions> options,
        ILogger<SystemTrayService> logger)
    {
        _appLifetime = appLifetime;
        _options = options;
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

                // 3. Log Klasörünü Aç
                var openLogsItem = new ToolStripMenuItem("📁 Log Klasörünü Aç", null, (s, e) =>
                {
                    try
                    {
                        var logPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "logs");
                        if (!Directory.Exists(logPath))
                        {
                            Directory.CreateDirectory(logPath);
                        }
                        Process.Start(new ProcessStartInfo
                        {
                            FileName = logPath,
                            UseShellExecute = true
                        });
                    }
                    catch (Exception ex)
                    {
                        _logger.LogError(ex, "Log klasörü açılırken hata oluştu.");
                    }
                });
                contextMenu.Items.Add(openLogsItem);

                contextMenu.Items.Add(new ToolStripSeparator());

                // 4. Servisi Kapat
                var exitItem = new ToolStripMenuItem("❌ Servisi Durdur", null, (s, e) =>
                {
                    _logger.LogInformation("System Tray üzerinden servis durdurma istendi.");
                    _appLifetime.StopApplication();
                });
                contextMenu.Items.Add(exitItem);

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

            if (_browserForm == null || _browserForm.IsDisposed)
            {
                _browserForm = new BrowserForm(url, restrictions);
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.Show();
                _browserForm.WindowState = FormWindowState.Maximized;
            }
            else
            {
                _browserForm.WindowState = FormWindowState.Maximized;
                _browserForm.Navigate(url);
                _browserForm.BringToFront();
                _browserForm.Activate();
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Dahili özel tarayıcı açılırken hata oluştu.");
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

using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.Options;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// Dış tarayıcılar (Chrome vb.) yerine servisin kendi içinde barındırdığı
/// Kiosk / Kısıtlanabilir Chromium tabanlı dahili Adisyon tarayıcı penceresi.
/// </summary>
[SupportedOSPlatform("windows")]
public class BrowserForm : Form
{
    private readonly string _initialUrl;
    private readonly BrowserRestrictionOptions _restrictions;
    private readonly string _restaurantId;
    private readonly string _restaurantPassword;
    private readonly bool _autoLoginEnabled;
    private WebView2? _webView;
    private TextBox _urlTextBox = null!;
    private Panel _topBar = null!;
    private Panel _offlineBanner = null!;
    private Label _lblOfflineText = null!;
    private Panel _loadingPanel = null!;
    private bool _isCurrentOfflineMode = false;
    private string _lastOfflineUrl = "http://127.0.0.1:8000";
    private int _offlineRetryCount = 0;
    private readonly HashSet<string> _autoLoginAttemptedOrigins =
        new(StringComparer.OrdinalIgnoreCase);
    public bool IsBlocked { get; set; } = false;

    public BrowserForm(
        string initialUrl, 
        BrowserRestrictionOptions restrictions, 
        string restaurantId = "", 
        string restaurantPassword = "", 
        bool autoLoginEnabled = true)
    {
        _initialUrl = initialUrl;
        _restrictions = restrictions ?? new BrowserRestrictionOptions();
        _restaurantId = restaurantId ?? string.Empty;
        _restaurantPassword = restaurantPassword ?? string.Empty;
        _autoLoginEnabled = autoLoginEnabled;
        InitializeCustomComponents();
    }

    private void InitializeCustomComponents()
    {
        Text = "Adisyon Pos Otomasyon";
        Size = new Size(1280, 800);
        MinimumSize = new Size(800, 600);
        Icon = AltF4DeviceService.WebApi.Tray.SystemTrayService.GetAppIcon();
        BackColor = Color.FromArgb(30, 30, 30);

        if (_restrictions.EnableKioskFullScreen)
        {
            FormBorderStyle = FormBorderStyle.None;
            WindowState = FormWindowState.Maximized;
            Bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1920, 1080);
            TopMost = true;
        }
        else
        {
            WindowState = FormWindowState.Maximized;
            StartPosition = FormStartPosition.CenterScreen;
        }

        // Üst Navigasyon Çubuğu (Kiosk ayarına göre gösterilir veya gizlenir)
        _topBar = new Panel
        {
            Dock = DockStyle.Top,
            Height = 46,
            BackColor = Color.FromArgb(45, 45, 48),
            Padding = new Padding(8),
            Visible = !_restrictions.HideNavigationControls
        };

        var btnBack = CreateButton("◀", 36, (s, e) => { if (_webView?.CanGoBack == true) _webView.GoBack(); });
        var btnForward = CreateButton("▶", 36, (s, e) => { if (_webView?.CanGoForward == true) _webView.GoForward(); });
        var btnReload = CreateButton("🔄", 36, (s, e) => { _webView?.Reload(); });

        _urlTextBox = new TextBox
        {
            Text = _initialUrl,
            Height = 28,
            Width = 450,
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            BackColor = Color.FromArgb(28, 28, 28),
            ForeColor = Color.White,
            BorderStyle = BorderStyle.FixedSingle,
            Margin = new Padding(8, 4, 8, 4)
        };

        _urlTextBox.KeyDown += (s, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                Navigate(_urlTextBox.Text);
                e.SuppressKeyPress = true;
            }
        };

        // Hızlı Link Butonları
        var btnHealth = CreateButton("Health", 70, (s, e) => Navigate("http://127.0.0.1:18500/health"));
        var btnDevice = CreateButton("Device", 70, (s, e) => Navigate("http://127.0.0.1:18500/device"));
        var btnLicense = CreateButton("License", 70, (s, e) => Navigate("http://127.0.0.1:18500/license"));
        var btnBranch = CreateButton("Branch", 70, (s, e) => Navigate("http://127.0.0.1:18500/branch"));

        var flowPanel = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            AutoSize = true,
            WrapContents = false
        };

        flowPanel.Controls.Add(btnBack);
        flowPanel.Controls.Add(btnForward);
        flowPanel.Controls.Add(btnReload);
        flowPanel.Controls.Add(_urlTextBox);
        flowPanel.Controls.Add(btnHealth);
        flowPanel.Controls.Add(btnDevice);
        flowPanel.Controls.Add(btnLicense);
        flowPanel.Controls.Add(btnBranch);

        _topBar.Controls.Add(flowPanel);

        // Tam Ekran Özel Başlık Çubuğu & Pencere Kontrol Butonları (Aşağı İndir / Kapat)
        var headerBar = new Panel
        {
            Dock = DockStyle.Top,
            Height = 32,
            BackColor = Color.FromArgb(20, 20, 24),
            Padding = new Padding(10, 0, 0, 0)
        };

        var lblHeaderTitle = new Label
        {
            Text = "Adisyon Pos Otomasyon",
            ForeColor = Color.FromArgb(170, 170, 170),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleLeft
        };

        var btnMinimize = new Button
        {
            Text = "—",
            Width = 46,
            Dock = DockStyle.Right,
            FlatStyle = FlatStyle.Flat,
            ForeColor = Color.White,
            BackColor = Color.FromArgb(20, 20, 24),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnMinimize.FlatAppearance.BorderSize = 0;
        btnMinimize.FlatAppearance.MouseOverBackColor = Color.FromArgb(60, 60, 65);
        btnMinimize.Click += (s, e) =>
        {
            TopMost = false;
            WindowState = FormWindowState.Minimized;
        };

        var btnClose = new Button
        {
            Text = "✕",
            Width = 46,
            Dock = DockStyle.Right,
            FlatStyle = FlatStyle.Flat,
            ForeColor = Color.White,
            BackColor = Color.FromArgb(20, 20, 24),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnClose.FlatAppearance.BorderSize = 0;
        btnClose.FlatAppearance.MouseOverBackColor = Color.FromArgb(228, 30, 45);
        btnClose.Click += (s, e) =>
        {
            Close();
        };

        headerBar.Controls.Add(lblHeaderTitle);
        headerBar.Controls.Add(btnMinimize);
        headerBar.Controls.Add(btnClose);

        // 🟢 / 🔴 ÇEVRİMDIŞI MOD DURUM ÇUBUĞU (OFFLINE / ONLINE STATUS BAR)
        _offlineBanner = new Panel
        {
            Dock = DockStyle.Top,
            Height = 36,
            BackColor = Color.FromArgb(185, 28, 28), // Koyu Kırmızı (Offline)
            Padding = new Padding(10, 0, 10, 0),
            Visible = false
        };

        _lblOfflineText = new Label
        {
            Text = "🔴 ÇEVRİMDIŞI MOD (OFFLINE) — İnternet Kesildi, Yerel Kasa Çalışıyor (Localhost)",
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter
        };
        _offlineBanner.Controls.Add(_lblOfflineText);

        // Chromium WebView2 Kontrolü
        _webView = new WebView2
        {
            Dock = DockStyle.Fill,
            Visible = false
        };

        _loadingPanel = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(12, 13, 18),
            Visible = true
        };

        var loadingContent = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 4
        };
        loadingContent.RowStyles.Add(new RowStyle(SizeType.Percent, 46F));
        loadingContent.RowStyles.Add(new RowStyle(SizeType.Absolute, 42F));
        loadingContent.RowStyles.Add(new RowStyle(SizeType.Absolute, 28F));
        loadingContent.RowStyles.Add(new RowStyle(SizeType.Percent, 54F));

        var loadingTitle = new Label
        {
            Text = "Adisyon hazırlanıyor…",
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter,
            Font = new Font("Segoe UI", 17F, FontStyle.Bold),
            ForeColor = Color.White
        };

        var loadingSubtitle = new Label
        {
            Text = "Arayüz ve stiller yükleniyor",
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.TopCenter,
            Font = new Font("Segoe UI", 10F),
            ForeColor = Color.FromArgb(156, 163, 175)
        };

        loadingContent.Controls.Add(loadingTitle, 0, 1);
        loadingContent.Controls.Add(loadingSubtitle, 0, 2);
        _loadingPanel.Controls.Add(loadingContent);

        Controls.Add(_webView);
        Controls.Add(_loadingPanel);
        Controls.Add(_offlineBanner);
        Controls.Add(_topBar);
        Controls.Add(headerBar);

        InitializeWebView();
    }

    /// <summary>
    /// İnternet bağlantı durumuna göre Kiosk tarayıcısını Domain <-> Localhost arasında yeniler ve bilgi çubuğunu günceller.
    /// </summary>
    public void SetNetworkMode(bool isOnline, string localUrl = "http://127.0.0.1:8000")
    {
        if (InvokeRequired)
        {
            Invoke(() => SetNetworkMode(isOnline, localUrl));
            return;
        }

        try
        {
            bool isOfflineNow = !isOnline;
            if (_isCurrentOfflineMode == isOfflineNow)
                return; // Zaten bu moddayız

            _isCurrentOfflineMode = isOfflineNow;

            if (isOfflineNow)
            {
                // 🔴 OFFLINE MODA GEÇİŞ
                _offlineBanner.BackColor = Color.FromArgb(185, 28, 28); // Red
                _lblOfflineText.Text = "🔴 ÇEVRİMDIŞI MOD (OFFLINE) — İnternet Kesildi, Yerel Kasa Çalışıyor (Localhost)";
                _offlineBanner.Visible = true;

                _lastOfflineUrl = localUrl;
                _offlineRetryCount = 0;

                // Yerel Laravel Kasa Web Arayüzüne Yönlendir
                ShowLoadingOverlay();
                Navigate(localUrl);
            }
            else
            {
                // 🟢 ONLINE MODA GEÇİŞ
                _offlineBanner.BackColor = Color.FromArgb(16, 185, 129); // Emerald Green
                _lblOfflineText.Text = "🟢 ONLİNE MOD — Bağlantı Sağlandı, Yerel Veriler Senkronize Ediliyor...";
                _offlineBanner.Visible = true;

                // Canlı sunucu adresine dön
                Navigate(_initialUrl);

                // 3 saniye sonra yeşil çubuğu gizle
                var timer = new System.Windows.Forms.Timer { Interval = 4000 };
                timer.Tick += (s, e) =>
                {
                    _offlineBanner.Visible = false;
                    timer.Stop();
                    timer.Dispose();
                };
                timer.Start();
            }
        }
        catch { }
    }

    private async void InitializeWebView()
    {
        try
        {
            if (_webView != null)
            {
                // 🚀 YÜKSEK PERFORMANS & OFFLINE HIZLANDIRMA YAPILANDIRMASI
                // Microsoft WebView2 varsayılan olarak Windows Proxy Auto-Detect (WPAD) taraması yapar.
                // Yerel 127.0.0.1 (Localhost) isteklerinde bu durum her istekte 1-2 saniye gecikmeye yol açar.
                // --no-proxy-server bayrağı ile bu gecikme sıfırlanır ve yerel mod Chrome hızına ulaşır.
                string userDataFolder = System.IO.Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "AdisyonPosOtomasyon",
                    "WebView2Cache"
                );

                try
                {
                    System.IO.Directory.CreateDirectory(userDataFolder);
                }
                catch { }

                var options = new CoreWebView2EnvironmentOptions(
                    additionalBrowserArguments: "--no-proxy-server --disable-background-timer-throttling --disable-features=CalculateNativeWinOcclusion --enable-gpu-rasterization"
                );

                var env = await CoreWebView2Environment.CreateAsync(null, userDataFolder, options);
                await _webView.EnsureCoreWebView2Async(env);

                // --- Güvenlik ve Kısıtlama Kuralları Entegrasyonu ---
                var settings = _webView.CoreWebView2.Settings;

                // F12 ve DevTools Kısıtlaması
                settings.AreDevToolsEnabled = !_restrictions.DisableDevTools;

                // Sağ Tık Bağlam Menüsü (İncele) Kısıtlaması
                settings.AreDefaultContextMenusEnabled = !_restrictions.DisableContextMenu;

                // Zoom ve diğer aksiyonlar
                settings.IsZoomControlEnabled = true;
                settings.IsScriptEnabled = true;
                settings.IsStatusBarEnabled = false;
                settings.IsSwipeNavigationEnabled = false;

                _webView.CoreWebView2.PermissionRequested += OnPermissionRequested;
                await ConfigureBrowserNotificationAlertsAsync();

                // URL Değişim Takibi
                _webView.CoreWebView2.SourceChanged += (s, e) =>
                {
                    if (_urlTextBox != null && _webView.Source != null)
                    {
                        _urlTextBox.Text = _webView.Source.ToString();
                    }
                };

                // Alan Adı (Domain) Kısıtlaması Kontrolü
                _webView.CoreWebView2.NavigationStarting += OnNavigationStarting;

                // Otomatik Giriş (Auto-Login) Entegrasyonu
                _webView.CoreWebView2.NavigationCompleted += OnNavigationCompleted;

                if (IsBlocked)
                {
                    ShowLicenseBlockedScreen("Lisansınız Pasife Alınmıştır veya Geçersizdir");
                }
                else
                {
                    _webView.Source = new Uri(_initialUrl);
                }
            }
        }
        catch (Exception ex)
        {
            if (IsDisposed || Disposing || ex.HResult == unchecked((int)0x80004004))
            {
                // Form kapanırken veya iptal edildiğinde WebView2 başlatma hatasını sessizce yut
                return;
            }
            MessageBox.Show($"Dahili Chromium WebView2 tarayıcısı başlatılırken hata oluştu: {ex.Message}", "Tarayıcı Hatası", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void OnNavigationCompleted(object? sender, CoreWebView2NavigationCompletedEventArgs e)
    {
        if (e.IsSuccess && _isCurrentOfflineMode)
        {
            _offlineRetryCount = 0;
        }

        if (!e.IsSuccess && _isCurrentOfflineMode && _webView?.CoreWebView2 != null)
        {
            if (_offlineRetryCount < 4)
            {
                _offlineRetryCount++;
                await Task.Delay(800);
                Navigate(_lastOfflineUrl);
                return;
            }

            ShowOfflinePage(_urlTextBox?.Text ?? _lastOfflineUrl);
            return;
        }

        await RevealWebViewAsync();

        if (!e.IsSuccess || !_autoLoginEnabled || string.IsNullOrWhiteSpace(_restaurantId) || _webView == null)
            return;

        try
        {
            var currentUrl = _webView.Source?.ToString() ?? "";
            
            // Sadece giriş sayfasındaysak Otomatik Giriş Script'ini enjekte et.
            // Hatalı/eski bir kayıt varsa POST tekrar /login'e döner. Aynı origin
            // için yeniden otomatik gönderim yapmak sonsuz giriş döngüsüne neden
            // olduğundan uygulama oturumu boyunca yalnızca bir kez deneriz.
            if ((currentUrl.EndsWith("/login", StringComparison.OrdinalIgnoreCase) ||
                 currentUrl.Contains("/login?", StringComparison.OrdinalIgnoreCase))
                && Uri.TryCreate(currentUrl, UriKind.Absolute, out var loginUri)
                && _autoLoginAttemptedOrigins.Add(loginUri.GetLeftPart(UriPartial.Authority)))
            {
                var jsonUser = System.Text.Json.JsonSerializer.Serialize(_restaurantId);
                var jsonPass = System.Text.Json.JsonSerializer.Serialize(_restaurantPassword ?? "");

                string autoLoginJs = $@"
                (function() {{
                    var userVal = {jsonUser};
                    var passVal = {jsonPass};

                    function fillAndSubmit() {{
                        var userInput = document.querySelector(""input[name='email']"") || 
                                        document.querySelector(""input[name='username']"") || 
                                        document.querySelector(""input[name='restaurant_id']"") || 
                                        document.querySelector(""input[name='login']"") || 
                                        document.querySelector(""input[type='email']"") || 
                                        document.querySelector(""input[type='text']"");

                        var passInput = document.querySelector(""input[name='password']"") || 
                                        document.querySelector(""input[type='password']"");

                        if (userInput && userVal) {{
                            userInput.value = userVal;
                            userInput.dispatchEvent(new Event('input', {{ bubbles: true }}));
                            userInput.dispatchEvent(new Event('change', {{ bubbles: true }}));
                        }}

                        if (passInput && passVal) {{
                            passInput.value = passVal;
                            passInput.dispatchEvent(new Event('input', {{ bubbles: true }}));
                            passInput.dispatchEvent(new Event('change', {{ bubbles: true }}));
                        }}

                        if (userInput && passInput && userVal) {{
                            setTimeout(function() {{
                                var submitBtn = document.querySelector(""button[type='submit']"") || 
                                                document.querySelector(""input[type='submit']"") || 
                                                document.querySelector(""form button"");
                                if (submitBtn) {{
                                    submitBtn.click();
                                }}
                            }}, 300);
                        }}
                    }}

                    if (document.readyState === 'complete' || document.readyState === 'interactive') {{
                        fillAndSubmit();
                    }} else {{
                        document.addEventListener('DOMContentLoaded', fillAndSubmit);
                    }}
                }})();
                ";

                await _webView.CoreWebView2.ExecuteScriptAsync(autoLoginJs);
            }
        }
        catch { }
    }

    private void OnPermissionRequested(object? sender, CoreWebView2PermissionRequestedEventArgs e)
    {
        if (e.PermissionKind != CoreWebView2PermissionKind.Notifications)
        {
            return;
        }

        e.State = CoreWebView2PermissionState.Allow;
    }

    private async Task ConfigureBrowserNotificationAlertsAsync()
    {
        if (_webView?.CoreWebView2 == null)
        {
            return;
        }

        const string notificationBridgeScript = """
            (() => {
                if (window.__adisyonNotificationAlertBridgeInstalled) {
                    return;
                }

                window.__adisyonNotificationAlertBridgeInstalled = true;

                function buildAlertMessage(title, options) {
                    const parts = [];
                    const prefix = '[Tarayici Bildirimi]';
                    const safeTitle = typeof title === 'string' ? title.trim() : '';
                    const safeBody = options && typeof options.body === 'string' ? options.body.trim() : '';

                    parts.push(prefix);

                    if (safeTitle) {
                        parts.push(safeTitle);
                    }

                    if (safeBody) {
                        parts.push(safeBody);
                    }

                    return parts.join('\n\n');
                }

                function forwardToAlert(title, options) {
                    const message = buildAlertMessage(title, options);

                    window.setTimeout(() => {
                        try {
                            window.alert(message);
                        } catch {
                        }
                    }, 0);
                }

                const OriginalNotification = window.Notification;
                if (typeof OriginalNotification === 'function') {
                    window.Notification = new Proxy(OriginalNotification, {
                        construct(target, args, newTarget) {
                            const instance = Reflect.construct(target, args, newTarget);

                            try {
                                if (OriginalNotification.permission === 'granted') {
                                    forwardToAlert(args[0], args[1]);
                                }
                            } catch {
                            }

                            return instance;
                        },
                        get(target, prop, receiver) {
                            return Reflect.get(target, prop, receiver);
                        }
                    });
                }

                const serviceWorkerProto = window.ServiceWorkerRegistration && window.ServiceWorkerRegistration.prototype;
                if (serviceWorkerProto && typeof serviceWorkerProto.showNotification === 'function') {
                    const originalShowNotification = serviceWorkerProto.showNotification;

                    serviceWorkerProto.showNotification = function(title, options) {
                        forwardToAlert(title, options);
                        return originalShowNotification.apply(this, arguments);
                    };
                }
            })();
            """;

        await _webView.CoreWebView2.AddScriptToExecuteOnDocumentCreatedAsync(notificationBridgeScript);
    }

    private void OnNavigationStarting(object? sender, CoreWebView2NavigationStartingEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(e.Uri) ||
            e.Uri.StartsWith("data:", StringComparison.OrdinalIgnoreCase) ||
            e.Uri.StartsWith("about:", StringComparison.OrdinalIgnoreCase))
        {
            return;
        }

        // Localhost dahil her gerçek sayfa geçişinde WebView'i CSS'siz ilk
        // boyaması görünmeden hazırlık katmanının arkasında yükle.
        ShowLoadingOverlay();

        if (_restrictions.RestrictNavigationToAllowedDomains && Uri.TryCreate(e.Uri, UriKind.Absolute, out var targetUri))
        {
            var host = targetUri.Host.ToLowerInvariant();

            if (string.IsNullOrEmpty(host) ||
                host.Equals("127.0.0.1", StringComparison.OrdinalIgnoreCase) ||
                host.Equals("localhost", StringComparison.OrdinalIgnoreCase) ||
                host.Equals("::1", StringComparison.OrdinalIgnoreCase))
            {
                return;
            }

            bool isAllowed = _restrictions.AllowedDomains.Any(domain => 
                host.Equals(domain.ToLowerInvariant(), StringComparison.OrdinalIgnoreCase) || 
                host.EndsWith("." + domain.ToLowerInvariant(), StringComparison.OrdinalIgnoreCase));

            if (!isAllowed)
            {
                e.Cancel = true;
                MessageBox.Show($"Erişim Engellendi!\n\n'{targetUri.Host}' alan adına erişim kısıtlanmıştır.\nSadece yetkili Adisyon sistemi sayfalarına erişebilirsiniz.", 
                    "Güvenlik Kısıtlaması", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }
    }

    private void ShowLoadingOverlay()
    {
        if (IsDisposed || Disposing)
        {
            return;
        }

        if (InvokeRequired)
        {
            BeginInvoke(ShowLoadingOverlay);
            return;
        }

        if (_webView != null)
        {
            _webView.Visible = false;
        }

        if (_loadingPanel != null)
        {
            _loadingPanel.Visible = true;
            _loadingPanel.BringToFront();
        }

        _offlineBanner?.BringToFront();
        _topBar?.BringToFront();
    }

    private async Task RevealWebViewAsync()
    {
        if (IsDisposed || Disposing)
        {
            return;
        }

        // NavigationCompleted belgenin tamamlandığını bildirir. İki kısa UI turu daha
        // beklemek CSS, web fontları ve ilk tarayıcı boyamasının ekrana oturmasını sağlar.
        await Task.Delay(_isCurrentOfflineMode ? 350 : 180);

        if (IsDisposed || Disposing)
        {
            return;
        }

        if (InvokeRequired)
        {
            BeginInvoke(() =>
            {
                if (_webView != null) _webView.Visible = true;
                if (_loadingPanel != null) _loadingPanel.Visible = false;
            });
            return;
        }

        if (_webView != null)
        {
            _webView.Visible = true;
        }

        if (_loadingPanel != null)
        {
            _loadingPanel.Visible = false;
        }
    }

    public void Navigate(string url)
    {
        try
        {
            if (!url.StartsWith("http://") && !url.StartsWith("https://"))
            {
                url = "http://" + url;
            }
            if (_webView != null && _webView.CoreWebView2 != null)
            {
                _webView.Source = new Uri(url);
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Sayfa açılırken hata oluştu: {ex.Message}");
        }
    }

    public void ShowOfflinePage(string localUrl)
    {
        try
        {
            var html = $@"
<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <style>
        body {{ background-color: #0c0d12; color: #ffffff; font-family: 'Segoe UI', Arial, sans-serif; display: flex; height: 100vh; margin: 0; justify-content: center; align-items: center; text-align: center; }}
        .card {{ background: #151722; border: 1px solid #dc2626; border-radius: 20px; padding: 45px; max-width: 580px; box-shadow: 0 25px 50px rgba(220, 38, 38, 0.25); }}
        .icon {{ font-size: 64px; margin-bottom: 20px; }}
        h1 {{ font-size: 24px; color: #ef4444; margin-bottom: 12px; font-weight: bold; letter-spacing: 0.5px; }}
        p {{ color: #9ca3af; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }}
        .badge {{ background: #450a0a; color: #fca5a5; padding: 10px 20px; border-radius: 30px; font-size: 12px; font-weight: bold; display: inline-block; border: 1px solid #991b1b; }}
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>📡</div>
        <h1>ÇEVRİMDIŞI MOD (OFFLINE)</h1>
        <p>İnternet bağlantısı kesildi. Kasa çevrimdışı çalışma moduna alındı.<br>İnternet bağlantısı sağlandığında sistem otomatik olarak canlı sunucuya bağlanacaktır.</p>
        <div class='badge'>Hedef Adres: {localUrl}</div>
    </div>
</body>
</html>";

            if (_webView?.CoreWebView2 != null)
            {
                _webView.CoreWebView2.NavigateToString(html);
            }
        }
        catch { }
    }

    public void ShowLicenseBlockedScreen(string reason = "Lisansınız Pasife Alınmıştır")
    {
        IsBlocked = true;
        try
        {
            if (InvokeRequired)
            {
                Invoke(() => ShowLicenseBlockedScreen(reason));
                return;
            }

            var html = $@"
<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <style>
        body {{ background-color: #0c0d12; color: #ffffff; font-family: 'Segoe UI', Arial, sans-serif; display: flex; height: 100vh; margin: 0; justify-content: center; align-items: center; text-align: center; }}
        .card {{ background: #151722; border: 1px solid #dc2626; border-radius: 20px; padding: 50px; max-width: 550px; box-shadow: 0 25px 50px rgba(220, 38, 38, 0.25); }}
        .icon {{ font-size: 72px; margin-bottom: 24px; }}
        h1 {{ font-size: 26px; color: #ef4444; margin-bottom: 14px; font-weight: bold; letter-spacing: 0.5px; }}
        p {{ color: #9ca3af; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }}
        .badge {{ background: #450a0a; color: #fca5a5; padding: 10px 20px; border-radius: 30px; font-size: 13px; font-weight: bold; display: inline-block; border: 1px solid #991b1b; }}
    </style>
</head>
<body>
    <div class='card'>
        <div class='icon'>🔒</div>
        <h1>LİSANS ERİŞİMİ ENGELLENDİ</h1>
        <p>Restoran Adisyon sistem lisansınız <strong>{reason}</strong> durumundadır.<br>Kasa ve sipariş sistemine erişim durdurulmuştur.</p>
        <div class='badge'>Lütfen Sistem Yöneticiniz İle İletişime Geçiniz</div>
    </div>
</body>
</html>";

            if (_webView?.CoreWebView2 != null)
            {
                _webView.CoreWebView2.NavigateToString(html);
            }
        }
        catch { }
    }

    public void RestoreBrowser()
    {
        IsBlocked = false;
        try
        {
            if (InvokeRequired)
            {
                Invoke(RestoreBrowser);
                return;
            }

            if (WindowState == FormWindowState.Minimized)
            {
                WindowState = FormWindowState.Maximized;
            }

            BringToFront();
            Activate();

            if (_webView != null && _webView.CoreWebView2 != null)
            {
                if (_webView.Source == null || !_webView.Source.ToString().Contains("synaptropic.com"))
                {
                    _webView.Source = new Uri(_initialUrl);
                }
            }
        }
        catch { }
    }

    private Button CreateButton(string text, int width, EventHandler onClick)
    {
        var btn = new Button
        {
            Text = text,
            Width = width,
            Height = 30,
            FlatStyle = FlatStyle.Flat,
            ForeColor = Color.White,
            BackColor = Color.FromArgb(60, 60, 65),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            Cursor = Cursors.Hand,
            Margin = new Padding(2, 2, 4, 2)
        };
        btn.FlatAppearance.BorderSize = 0;
        btn.Click += onClick;
        return btn;
    }

    protected override void OnLoad(EventArgs e)
    {
        base.OnLoad(e);
        if (_restrictions.EnableKioskFullScreen)
        {
            FormBorderStyle = FormBorderStyle.None;
            Bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1920, 1080);
            WindowState = FormWindowState.Maximized;
            TopMost = true;
        }
        else
        {
            WindowState = FormWindowState.Maximized;
        }
    }

    protected override void OnShown(EventArgs e)
    {
        base.OnShown(e);
        if (_restrictions.EnableKioskFullScreen)
        {
            FormBorderStyle = FormBorderStyle.None;
            Bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1920, 1080);
            WindowState = FormWindowState.Maximized;
            TopMost = true;
        }
        else
        {
            WindowState = FormWindowState.Maximized;
        }
        BringToFront();
        Activate();
    }

    protected override void OnResize(EventArgs e)
    {
        base.OnResize(e);
        if (WindowState != FormWindowState.Minimized && _restrictions.EnableKioskFullScreen)
        {
            TopMost = true;
            WindowState = FormWindowState.Maximized;
        }
    }
}

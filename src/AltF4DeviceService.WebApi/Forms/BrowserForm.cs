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
    private WebView2? _webView;
    private TextBox _urlTextBox = null!;
    private Panel _topBar = null!;

    public BrowserForm(string initialUrl, BrowserRestrictionOptions restrictions)
    {
        _initialUrl = initialUrl;
        _restrictions = restrictions ?? new BrowserRestrictionOptions();
        InitializeCustomComponents();
    }

    private void InitializeCustomComponents()
    {
        Text = "AltF4 Adisyon Sistemi - Dahili Tarayıcı";
        Size = new Size(1280, 800);
        MinimumSize = new Size(800, 600);
        Icon = SystemIcons.Application;
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
            Text = "AltF4 Adisyon Portal",
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

        // Chromium WebView2 Kontrolü
        _webView = new WebView2
        {
            Dock = DockStyle.Fill
        };

        Controls.Add(_webView);
        Controls.Add(_topBar);
        Controls.Add(headerBar);

        InitializeWebView();
    }

    private async void InitializeWebView()
    {
        try
        {
            if (_webView != null)
            {
                await _webView.EnsureCoreWebView2Async(null);

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

                _webView.Source = new Uri(_initialUrl);
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Dahili Chromium WebView2 tarayıcısı başlatılırken hata oluştu: {ex.Message}", "Tarayıcı Hatası", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private void OnNavigationStarting(object? sender, CoreWebView2NavigationStartingEventArgs e)
    {
        if (_restrictions.RestrictNavigationToAllowedDomains && Uri.TryCreate(e.Uri, UriKind.Absolute, out var targetUri))
        {
            var host = targetUri.Host.ToLowerInvariant();

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

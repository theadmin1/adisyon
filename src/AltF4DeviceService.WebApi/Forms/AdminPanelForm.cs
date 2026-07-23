using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// AltF4 Device Service - Ultra Modern Dark Dashboard Admin Paneli (WinForms GUI).
/// </summary>
[SupportedOSPlatform("windows")]
public class AdminPanelForm : Form
{
    private readonly IServiceProvider _serviceProvider;
    private readonly IOptions<ServiceOptions> _options;

    // Sol Navigasyon Menüsü ve İçerik Panelleri
    private Panel _sidebar = null!;
    private Panel _contentContainer = null!;
    private readonly Dictionary<string, Panel> _tabPanels = new();
    private readonly Dictionary<string, Button> _navButtons = new();
    private string _activeTab = "license";

    // Form Kontrolleri
    private TextBox _txtLicenseKey = null!;
    private Label _lblLicenseStatusBadge = null!;
    private Label _lblDeviceToken = null!;
    private TextBox _txtBranchName = null!;
    private TextBox _txtDeviceCode = null!;
    private TextBox _txtPort = null!;
    private TextBox _txtWebUrl = null!;

    // Güvenlik Kısıtlamaları Kontrolleri
    private CheckBox _chkDisableDevTools = null!;
    private CheckBox _chkDisableContextMenu = null!;
    private CheckBox _chkEnableKioskFullScreen = null!;
    private CheckBox _chkHideNavigationControls = null!;
    private CheckBox _chkRestrictDomains = null!;
    private TextBox _txtAllowedDomains = null!;

    // Log & Canlı Durum Kontrolleri
    private RichTextBox _rtbLogs = null!;
    private Label _lblUptime = null!;
    private Label _lblDbStatus = null!;

    public AdminPanelForm(IServiceProvider serviceProvider, IOptions<ServiceOptions> options)
    {
        _serviceProvider = serviceProvider;
        _options = options;
        InitializeModernUi();
        LoadDataAsync();
    }

    private void InitializeModernUi()
    {
        Text = "AltF4 Adisyon - Servis Admin Yönetim Paneli";
        Size = new Size(980, 640);
        MinimumSize = new Size(950, 600);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedSingle;
        MaximizeBox = false;
        Icon = SystemIcons.Shield;
        BackColor = Color.FromArgb(18, 19, 26); // Ultra Dark Theme Background
        ForeColor = Color.FromArgb(235, 237, 243);

        // --- 1. ÜST HEADER BAR ---
        var headerBar = new Panel
        {
            Dock = DockStyle.Top,
            Height = 65,
            BackColor = Color.FromArgb(25, 27, 36),
            Padding = new Padding(20, 0, 20, 0)
        };

        var lblAppLogo = new Label
        {
            Text = "⚡ AltF4 Device Service",
            Font = new Font("Segoe UI", 13F, FontStyle.Bold),
            ForeColor = Color.White,
            AutoSize = true,
            Location = new Point(20, 12)
        };

        var lblSubTitle = new Label
        {
            Text = "Restoran POS ve Servis Yönetim Paneli",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(140, 145, 165),
            AutoSize = true,
            Location = new Point(22, 37)
        };

        var statusPill = new Panel
        {
            Size = new Size(210, 36),
            Location = new Point(740, 14),
            BackColor = Color.FromArgb(16, 42, 34),
            Padding = new Padding(10, 6, 10, 6)
        };

        var lblStatusText = new Label
        {
            Text = "🟢 SERVİS AKTİF (18500)",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(52, 211, 153),
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter
        };
        statusPill.Controls.Add(lblStatusText);

        headerBar.Controls.Add(lblAppLogo);
        headerBar.Controls.Add(lblSubTitle);
        headerBar.Controls.Add(statusPill);

        // --- 2. SOL SİDEBAR NAVİGASYON ---
        _sidebar = new Panel
        {
            Dock = DockStyle.Left,
            Width = 230,
            BackColor = Color.FromArgb(22, 24, 32),
            Padding = new Padding(12, 16, 12, 16)
        };

        var flowNav = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            AutoSize = true
        };

        var btnNavLicense = CreateNavButton("license", "🔑  Lisans & Şube", (s, e) => SwitchTab("license"));
        var btnNavDevice = CreateNavButton("device", "💻  Cihaz & Servis", (s, e) => SwitchTab("device"));
        var btnNavSecurity = CreateNavButton("security", "🛡️  Tarayıcı Güvenliği", (s, e) => SwitchTab("security"));
        var btnNavLogs = CreateNavButton("logs", "📊  Sistem & Loglar", (s, e) => SwitchTab("logs"));

        flowNav.Controls.Add(btnNavLicense);
        flowNav.Controls.Add(btnNavDevice);
        flowNav.Controls.Add(btnNavSecurity);
        flowNav.Controls.Add(btnNavLogs);
        _sidebar.Controls.Add(flowNav);

        // --- 3. SAĞ İÇERİK KONTEYNERİ ---
        _contentContainer = new Panel
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(18, 19, 26),
            Padding = new Padding(24)
        };

        // Sekme Panellerini Oluştur
        _tabPanels["license"] = CreateLicensePanel();
        _tabPanels["device"] = CreateDevicePanel();
        _tabPanels["security"] = CreateSecurityPanel();
        _tabPanels["logs"] = CreateLogsPanel();

        foreach (var pnl in _tabPanels.Values)
        {
            pnl.Dock = DockStyle.Fill;
            pnl.Visible = false;
            _contentContainer.Controls.Add(pnl);
        }

        Controls.Add(_contentContainer);
        Controls.Add(_sidebar);
        Controls.Add(headerBar);

        // Varsayılan Sekmeyi Aç
        SwitchTab("license");
    }

    private Button CreateNavButton(string key, string text, EventHandler onClick)
    {
        var btn = new Button
        {
            Text = text,
            Width = 206,
            Height = 44,
            FlatStyle = FlatStyle.Flat,
            ForeColor = Color.FromArgb(160, 165, 185),
            BackColor = Color.Transparent,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            TextAlign = ContentAlignment.MiddleLeft,
            Padding = new Padding(14, 0, 0, 0),
            Cursor = Cursors.Hand,
            Margin = new Padding(0, 0, 0, 6)
        };
        btn.FlatAppearance.BorderSize = 0;
        btn.Click += onClick;
        _navButtons[key] = btn;
        return btn;
    }

    private void SwitchTab(string tabKey)
    {
        _activeTab = tabKey;
        foreach (var kvp in _tabPanels)
        {
            kvp.Value.Visible = (kvp.Key == tabKey);
        }

        foreach (var kvp in _navButtons)
        {
            if (kvp.Key == tabKey)
            {
                kvp.Value.BackColor = Color.FromArgb(88, 101, 242); // Active Blurple Accent
                kvp.Value.ForeColor = Color.White;
            }
            else
            {
                kvp.Value.BackColor = Color.Transparent;
                kvp.Value.ForeColor = Color.FromArgb(160, 165, 185);
            }
        }
    }

    // --- SEKME 1: LİSANS VE ŞUBE PANELİ ---
    private Panel CreateLicensePanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardLicense = CreateCardPanel("Lisans Anahtarı ve Doğrulama Durumu", 240);

        var lblLicKey = CreateFieldLabel("Lisans Anahtarı (License Key):", 20, 45);
        _txtLicenseKey = CreateModernTextBox(20, 70, 450);

        _lblLicenseStatusBadge = new Label
        {
            Text = "AKTİF",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.White,
            BackColor = Color.FromArgb(16, 185, 129),
            Size = new Size(110, 32),
            Location = new Point(485, 69),
            TextAlign = ContentAlignment.MiddleCenter
        };

        var lblTokenTitle = CreateFieldLabel("Cihaz Yetki Tokenı (Device Token):", 20, 115);
        _lblDeviceToken = new Label
        {
            Text = "a1b2c3d4-e5f6-7890-abcd-1234567890ab",
            Font = new Font("Consolas", 9.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(160, 165, 185),
            Location = new Point(20, 140),
            AutoSize = true
        };

        var btnSaveLic = CreatePrimaryButton("💾 Lisans Anahtarını Güncelle", 20, 175, (s, e) => SaveLicenseKey());
        var btnVerifyLic = CreateSecondaryButton("🔄 Lisansı API ile Doğrula", 240, 175, (s, e) => VerifyLicense());

        cardLicense.Controls.Add(lblLicKey);
        cardLicense.Controls.Add(_txtLicenseKey);
        cardLicense.Controls.Add(_lblLicenseStatusBadge);
        cardLicense.Controls.Add(lblTokenTitle);
        cardLicense.Controls.Add(_lblDeviceToken);
        cardLicense.Controls.Add(btnSaveLic);
        cardLicense.Controls.Add(btnVerifyLic);

        var cardBranch = CreateCardPanel("Şube ve Restoran Bilgileri", 170);
        cardBranch.Location = new Point(0, 260);

        var lblBranchName = CreateFieldLabel("Şube Adı:", 20, 45);
        _txtBranchName = CreateModernTextBox(20, 70, 450);

        cardBranch.Controls.Add(lblBranchName);
        cardBranch.Controls.Add(_txtBranchName);

        mainPanel.Controls.Add(cardLicense);
        mainPanel.Controls.Add(cardBranch);

        return mainPanel;
    }

    // --- SEKME 2: CİHAZ VE SERVİS PANELİ ---
    private Panel CreateDevicePanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardDevice = CreateCardPanel("Cihaz ve Bağlantı Yapılandırması", 360);

        var lblCode = CreateFieldLabel("Cihaz Kodu (örn. KASA-01):", 20, 45);
        _txtDeviceCode = CreateModernTextBox(20, 70, 300);

        var lblPort = CreateFieldLabel("Yerel HTTP Minimal API Portu:", 340, 45);
        _txtPort = CreateModernTextBox(340, 70, 140);

        var lblUrl = CreateFieldLabel("Dahili Tarayıcı Hedef URL (Adisyon Web):", 20, 130);
        _txtWebUrl = CreateModernTextBox(20, 155, 600);

        var btnSaveDevice = CreatePrimaryButton("💾 Cihaz Yapılandırmasını Kaydet", 20, 230, (s, e) => SaveDeviceSettings());

        cardDevice.Controls.Add(lblCode);
        cardDevice.Controls.Add(_txtDeviceCode);
        cardDevice.Controls.Add(lblPort);
        cardDevice.Controls.Add(_txtPort);
        cardDevice.Controls.Add(lblUrl);
        cardDevice.Controls.Add(_txtWebUrl);
        cardDevice.Controls.Add(btnSaveDevice);

        mainPanel.Controls.Add(cardDevice);

        return mainPanel;
    }

    // --- SEKME 3: TARAYICI VE GÜVENLİK PANELİ ---
    private Panel CreateSecurityPanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardSecurity = CreateCardPanel("Dahili Chromium Tarayıcı Güvenlik ve Kiosk Kuralları", 450);

        _chkDisableDevTools = CreateModernSwitch("Geliştirici Araçlarını (F12 / DevTools) Kısıtla", "Kullanıcıların tarayıcı kodlarını veya konsolu açmasını engeller.", 20, 45);
        _chkDisableContextMenu = CreateModernSwitch("Sağ Tık Bağlam Menüsünü (İncele) Kısıtla", "Sağ tık yapılarak öğeyi denetle menüsünün açılmasını engeller.", 20, 105);
        _chkEnableKioskFullScreen = CreateModernSwitch("Tam Ekran Kiosk Modu", "Windows görev çubuğunu ve üst pencere başlığını gizleyerek tam ekran çalışır.", 20, 165);
        _chkHideNavigationControls = CreateModernSwitch("Üst Navigasyon Çubuğunu Gizle", "Geri, İleri ve URL giriş çubuğunu gizleyerek tam koruma sağlar.", 20, 225);
        _chkRestrictDomains = CreateModernSwitch("Alan Adı (Domain) Beyaz Liste Kısıtlaması", "Sadece belirlenen yetkili adreslere gezinmeye izin verir.", 20, 285);

        var lblDomains = CreateFieldLabel("İzin Verilen Alan Adları (virgülle ayırın):", 20, 345);
        _txtAllowedDomains = CreateModernTextBox(20, 368, 600);

        var btnSaveSec = CreatePrimaryButton("💾 Güvenlik Kurallarını Kaydet & Uygula", 20, 405, (s, e) => SaveSecurityRestrictions());

        cardSecurity.Controls.Add(_chkDisableDevTools);
        cardSecurity.Controls.Add(_chkDisableContextMenu);
        cardSecurity.Controls.Add(_chkEnableKioskFullScreen);
        cardSecurity.Controls.Add(_chkHideNavigationControls);
        cardSecurity.Controls.Add(_chkRestrictDomains);
        cardSecurity.Controls.Add(lblDomains);
        cardSecurity.Controls.Add(_txtAllowedDomains);
        cardSecurity.Controls.Add(btnSaveSec);

        mainPanel.Controls.Add(cardSecurity);

        return mainPanel;
    }

    // --- SEKME 4: BİLGİ VE LOG İZLEYİCİ PANELİ ---
    private Panel CreateLogsPanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardStatus = CreateCardPanel("Servis ve Veritabanı Durumu", 100);
        
        _lblUptime = new Label { Text = "Çalışma Süresi: 00:00:00", AutoSize = true, Location = new Point(20, 45), Font = new Font("Segoe UI", 9.5F, FontStyle.Bold) };
        _lblDbStatus = new Label { Text = "Veritabanı: SQLite Bağlandı (altf4_device.db)", AutoSize = true, Location = new Point(280, 45), Font = new Font("Segoe UI", 9.5F, FontStyle.Bold), ForeColor = Color.FromArgb(52, 211, 153) };

        cardStatus.Controls.Add(_lblUptime);
        cardStatus.Controls.Add(_lblDbStatus);

        var cardLogs = CreateCardPanel("Canlı Log Kayıtları", 350);
        cardLogs.Location = new Point(0, 115);

        _rtbLogs = new RichTextBox
        {
            Location = new Point(16, 45),
            Size = new Size(640, 230),
            BackColor = Color.FromArgb(14, 15, 20),
            ForeColor = Color.FromArgb(52, 211, 153),
            Font = new Font("Consolas", 9.5F, FontStyle.Regular),
            BorderStyle = BorderStyle.None,
            ReadOnly = true
        };

        var btnLogFolder = CreateSecondaryButton("📁 Log Klasörünü Aç", 16, 290, (s, e) => OpenLogFolder());

        cardLogs.Controls.Add(_rtbLogs);
        cardLogs.Controls.Add(btnLogFolder);

        mainPanel.Controls.Add(cardStatus);
        mainPanel.Controls.Add(cardLogs);

        return mainPanel;
    }

    // --- YARDIMCI GÜZELLEŞTİRİLMİŞ BİLEŞENLER ---
    private Panel CreateCardPanel(string title, int height)
    {
        var pnl = new Panel
        {
            Size = new Size(680, height),
            BackColor = Color.FromArgb(25, 27, 36),
            Margin = new Padding(0, 0, 0, 20)
        };

        var lblTitle = new Label
        {
            Text = title,
            Font = new Font("Segoe UI", 10.5F, FontStyle.Bold),
            ForeColor = Color.White,
            Location = new Point(16, 12),
            AutoSize = true
        };

        var lineDivider = new Panel
        {
            Location = new Point(16, 36),
            Size = new Size(648, 1),
            BackColor = Color.FromArgb(42, 45, 58)
        };

        pnl.Controls.Add(lblTitle);
        pnl.Controls.Add(lineDivider);
        return pnl;
    }

    private Label CreateFieldLabel(string text, int x, int y)
    {
        return new Label
        {
            Text = text,
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(160, 165, 185),
            Location = new Point(x, y),
            AutoSize = true
        };
    }

    private TextBox CreateModernTextBox(int x, int y, int width)
    {
        return new TextBox
        {
            Location = new Point(x, y),
            Size = new Size(width, 32),
            BackColor = Color.FromArgb(18, 19, 26),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            BorderStyle = BorderStyle.FixedSingle
        };
    }

    private CheckBox CreateModernSwitch(string title, string subtext, int x, int y)
    {
        var chk = new CheckBox
        {
            Text = title,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.White,
            Location = new Point(x, y),
            AutoSize = true,
            Cursor = Cursors.Hand
        };
        return chk;
    }

    private Button CreatePrimaryButton(string text, int x, int y, EventHandler onClick)
    {
        var btn = new Button
        {
            Text = text,
            Location = new Point(x, y),
            Size = new Size(210, 38),
            BackColor = Color.FromArgb(88, 101, 242), // Primary Blurple
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btn.FlatAppearance.BorderSize = 0;
        btn.Click += onClick;
        return btn;
    }

    private Button CreateSecondaryButton(string text, int x, int y, EventHandler onClick)
    {
        var btn = new Button
        {
            Text = text,
            Location = new Point(x, y),
            Size = new Size(190, 38),
            BackColor = Color.FromArgb(42, 45, 58),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btn.FlatAppearance.BorderSize = 0;
        btn.Click += onClick;
        return btn;
    }

    // --- VERİ YÜKLEME VE İŞLEMLER ---
    private async void LoadDataAsync()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var licenseService = scope.ServiceProvider.GetRequiredService<ILicenseService>();
            var deviceService = scope.ServiceProvider.GetRequiredService<IDeviceService>();
            var branchService = scope.ServiceProvider.GetRequiredService<IBranchService>();
            var settingService = scope.ServiceProvider.GetRequiredService<ISettingService>();

            var license = await licenseService.GetOrCreateLicenseAsync();
            var device = await deviceService.GetOrCreateDeviceIdentityAsync();
            var branch = await branchService.GetOrCreateBranchAccountAsync();
            var restrictions = await settingService.GetBrowserRestrictionsAsync();

            _txtLicenseKey.Text = license.LicenseKey;
            _lblLicenseStatusBadge.Text = license.Status == "Active" ? "AKTİF" : "PASİF";
            _lblLicenseStatusBadge.BackColor = license.Status == "Active" ? Color.FromArgb(16, 185, 129) : Color.FromArgb(239, 68, 68);
            _lblDeviceToken.Text = license.DeviceToken;

            _txtBranchName.Text = branch.BranchName;
            _txtDeviceCode.Text = device.DeviceCode;
            _txtPort.Text = _options.Value.Port.ToString();
            _txtWebUrl.Text = _options.Value.AdisyonWebUrl;

            _chkDisableDevTools.Checked = restrictions.DisableDevTools;
            _chkDisableContextMenu.Checked = restrictions.DisableContextMenu;
            _chkEnableKioskFullScreen.Checked = restrictions.EnableKioskFullScreen;
            _chkHideNavigationControls.Checked = restrictions.HideNavigationControls;
            _chkRestrictDomains.Checked = restrictions.RestrictNavigationToAllowedDomains;
            _txtAllowedDomains.Text = string.Join(", ", restrictions.AllowedDomains);

            AppendLog("Admin Paneli yüklendi. SQLite veritabanı aktif.");
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Veriler yüklenirken hata oluştu: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void SaveLicenseKey()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var licenseService = scope.ServiceProvider.GetRequiredService<ILicenseService>();
            await licenseService.UpdateLicenseKeyAsync(_txtLicenseKey.Text.Trim());
            MessageBox.Show("Lisans anahtarı başarıyla güncellendi!", "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
            LoadDataAsync();
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Lisans güncellenemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void VerifyLicense()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var licenseService = scope.ServiceProvider.GetRequiredService<ILicenseService>();
            var isValid = await licenseService.VerifyAndUpdateLicenseAsync();
            MessageBox.Show(isValid ? "Lisans başarıyla doğrulandı ve AKTİF!" : "Lisans doğrulaması BAŞARISIZ!", "Lisans Kontrolü", MessageBoxButtons.OK, isValid ? MessageBoxIcon.Information : MessageBoxIcon.Warning);
            LoadDataAsync();
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Doğrulama hatası: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void SaveDeviceSettings()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var settingService = scope.ServiceProvider.GetRequiredService<ISettingService>();
            await settingService.SaveSettingAsync("DeviceCode", _txtDeviceCode.Text.Trim(), "Cihaz Kodu");
            await settingService.SaveSettingAsync("AdisyonWebUrl", _txtWebUrl.Text.Trim(), "Dahili Tarayıcı URL");

            _options.Value.AdisyonWebUrl = _txtWebUrl.Text.Trim();
            MessageBox.Show("Cihaz ve servis ayarları başarıyla kaydedildi!", "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Ayarlar kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void SaveSecurityRestrictions()
    {
        try
        {
            var domains = _txtAllowedDomains.Text.Split(new[] { ',', ';' }, StringSplitOptions.RemoveEmptyEntries)
                .Select(d => d.Trim()).ToList();

            var restrictions = new BrowserRestrictionOptions
            {
                DisableDevTools = _chkDisableDevTools.Checked,
                DisableContextMenu = _chkDisableContextMenu.Checked,
                EnableKioskFullScreen = _chkEnableKioskFullScreen.Checked,
                HideNavigationControls = _chkHideNavigationControls.Checked,
                RestrictNavigationToAllowedDomains = _chkRestrictDomains.Checked,
                AllowedDomains = domains
            };

            using var scope = _serviceProvider.CreateScope();
            var settingService = scope.ServiceProvider.GetRequiredService<ISettingService>();
            await settingService.SaveBrowserRestrictionsAsync(restrictions);

            MessageBox.Show("Güvenlik kuralları veritabanına kaydedildi ve servise uygulandı!", "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Güvenlik kuralları kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private void OpenLogFolder()
    {
        try
        {
            var path = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "logs");
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);
            Process.Start(new ProcessStartInfo { FileName = path, UseShellExecute = true });
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Log klasörü açılamadı: {ex.Message}");
        }
    }

    private void AppendLog(string message)
    {
        if (_rtbLogs != null)
        {
            _rtbLogs.AppendText($"[{DateTime.Now:HH:mm:ss}] {message}\n");
        }
    }
}

using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Domain.Interfaces;
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
    private TextBox _txtAdminUser = null!;
    private TextBox _txtAdminPass = null!;
    private TextBox _txtRestaurantLoginId = null!;
    private TextBox _txtRestaurantLoginPassword = null!;
    private CheckBox _chkAutoLoginEnabled = null!;

    // Güvenlik Kısıtlamaları Kontrolleri
    private CheckBox _chkDisableDevTools = null!;
    private CheckBox _chkDisableContextMenu = null!;
    private CheckBox _chkEnableKioskFullScreen = null!;
    private CheckBox _chkHideNavigationControls = null!;
    private CheckBox _chkRestrictDomains = null!;
    private TextBox _txtAllowedDomains = null!;

    // Termal Yazıcı Eşleştirme Kontrolleri (fiş türü -> Windows yazıcısı)
    private static readonly string[] PrinterTypeKeys = { "kitchen", "cashier", "bar" };
    private const string DefaultPrinterChoice = "(Varsayılan Windows yazıcısı)";
    private readonly Dictionary<string, ComboBox> _printerCombos = new();
    private readonly Dictionary<string, ComboBox> _paperCombos = new();
    private readonly Dictionary<string, CheckBox> _printerEnabledBoxes = new();
    private CheckBox _chkPrintNotifications = null!;
    private Label _lblPrinterStatus = null!;

    // ÖKC / Yazarkasa POS Kontrolleri
    private CheckBox? _chkPosEnabled;
    private ComboBox? _cmbPosConnection;
    private Label? _lblPosHost;
    private TextBox? _txtPosHost;
    private Label? _lblPosPort;
    private TextBox? _txtPosPort;
    private Label? _lblPosSerial;
    private TextBox? _txtPosSerial;
    private Label? _lblPosBaud;
    private TextBox? _txtPosBaud;
    private TextBox? _txtPosTimeout;
    private Label? _lblPosSimWarning;
    private Label? _lblPosStatus;

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
        ReloadInstalledPrinters();
        LoadPosConfigAsync();
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
            Size = new Size(180, 36),
            Location = new Point(540, 14),
            BackColor = Color.FromArgb(16, 42, 34),
            Padding = new Padding(10, 6, 10, 6)
        };

        var lblStatusText = new Label
        {
            Text = "🟢 SERVİS AKTİF (18500)",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(52, 211, 153),
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter
        };
        statusPill.Controls.Add(lblStatusText);

        var btnHeaderStopService = new Button
        {
            Text = "🛑 Servisi Durdur",
            Size = new Size(170, 36),
            Location = new Point(740, 14),
            BackColor = Color.FromArgb(220, 38, 38), // Red Accent
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnHeaderStopService.FlatAppearance.BorderSize = 0;
        btnHeaderStopService.Click += (s, e) => StopServiceAndExit();

        headerBar.Controls.Add(lblAppLogo);
        headerBar.Controls.Add(lblSubTitle);
        headerBar.Controls.Add(statusPill);
        headerBar.Controls.Add(btnHeaderStopService);

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
        var btnNavPrinters = CreateNavButton("printers", "🖨️  Termal Yazıcılar", (s, e) => SwitchTab("printers"));
        var btnNavPos = CreateNavButton("pos", "💳  ÖKC / Yazarkasa", (s, e) => SwitchTab("pos"));
        var btnNavSecurity = CreateNavButton("security", "🛡️  Tarayıcı Güvenliği", (s, e) => SwitchTab("security"));
        var btnNavNetwork = CreateNavButton("network", "📡  Ağ & Offline Test", (s, e) => SwitchTab("network"));
        var btnNavLogs = CreateNavButton("logs", "📊  Sistem & Loglar", (s, e) => SwitchTab("logs"));

        flowNav.Controls.Add(btnNavLicense);
        flowNav.Controls.Add(btnNavDevice);
        flowNav.Controls.Add(btnNavPrinters);
        flowNav.Controls.Add(btnNavPos);
        flowNav.Controls.Add(btnNavSecurity);
        flowNav.Controls.Add(btnNavNetwork);
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
        _tabPanels["printers"] = CreatePrintersPanel();
        _tabPanels["pos"] = CreatePosPanel();
        _tabPanels["security"] = CreateSecurityPanel();
        _tabPanels["network"] = CreateNetworkPanel();
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

        var cardRestaurantAuth = CreateCardPanel("🌐 Restoran Otomatik Giriş Bilgileri (Laravel POS)", 200);
        cardRestaurantAuth.Location = new Point(0, 380);

        var lblRestId = CreateFieldLabel("Restoran ID / E-Posta / Kullanıcı Adı:", 20, 45);
        _txtRestaurantLoginId = CreateModernTextBox(20, 70, 280);

        var lblRestPass = CreateFieldLabel("Restoran Giriş Şifresi:", 320, 45);
        _txtRestaurantLoginPassword = CreateModernTextBox(320, 70, 280);

        _chkAutoLoginEnabled = CreateModernSwitch("Otomatik Giriş Yetkisi Ver", "Tarayıcı açıldığında yetki verip otomatik oturum açar.", 20, 125);

        var btnSaveRestaurantAuth = CreatePrimaryButton("💾 Restoran Giriş Bilgilerini Kaydet", 350, 130, (s, e) => SaveRestaurantCredentials());

        cardRestaurantAuth.Controls.Add(lblRestId);
        cardRestaurantAuth.Controls.Add(_txtRestaurantLoginId);
        cardRestaurantAuth.Controls.Add(lblRestPass);
        cardRestaurantAuth.Controls.Add(_txtRestaurantLoginPassword);
        cardRestaurantAuth.Controls.Add(_chkAutoLoginEnabled);
        cardRestaurantAuth.Controls.Add(btnSaveRestaurantAuth);

        var cardAdminAuth = CreateCardPanel("🔒 Admin Giriş Bilgileri Değiştirme (Kullanıcı Adı & Şifre)", 210);
        cardAdminAuth.Location = new Point(0, 600);

        var lblAdminUser = CreateFieldLabel("Admin Kullanıcı Adı:", 20, 45);
        _txtAdminUser = CreateModernTextBox(20, 70, 280);

        var lblAdminPass = CreateFieldLabel("Yeni Admin Şifresi:", 320, 45);
        _txtAdminPass = CreateModernTextBox(320, 70, 280);

        var btnSaveAdminAuth = CreatePrimaryButton("🔑 Admin Giriş Bilgilerini Kaydet", 20, 140, (s, e) => SaveAdminCredentials());

        cardAdminAuth.Controls.Add(lblAdminUser);
        cardAdminAuth.Controls.Add(_txtAdminUser);
        cardAdminAuth.Controls.Add(lblAdminPass);
        cardAdminAuth.Controls.Add(_txtAdminPass);
        cardAdminAuth.Controls.Add(btnSaveAdminAuth);

        mainPanel.Controls.Add(cardDevice);
        mainPanel.Controls.Add(cardRestaurantAuth);
        mainPanel.Controls.Add(cardAdminAuth);

        return mainPanel;
    }

    // --- SEKME 3: TARAYICI VE GÜVENLİK PANELİ ---
    // --- SEKME 3: TERMAL YAZICI EŞLEŞTİRME PANELİ ---
    //
    // Fiziki yazıcı seçimi merkezi web panelinden yapılamaz: hangi Windows
    // yazıcısının kurulu olduğunu yalnızca bu cihaz bilebilir. Bu yüzden
    // eşleştirme burada yapılır, sunucuya yalnızca kağıt/satır genişliği
    // bildirilir (fiş metni orada üretildiği için).
    private Panel CreatePrintersPanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardInfo = CreateCardPanel("Yazıcı Eşleştirme", 68);
        cardInfo.Controls.Add(new Label
        {
            Text = "Her fiş türünün hangi Windows yazıcısından çıkacağını seçin.\r\n"
                 + "Boş bırakılan alanlar sistemin varsayılan yazıcısını kullanır.",
            Location = new Point(18, 40),
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(160, 165, 185)
        });

        var cardPrinters = CreateCardPanel("Fiş Türüne Göre Yazıcılar", 300);
        cardPrinters.Location = new Point(0, 84);

        int y = 50;
        foreach (var type in PrinterTypeKeys)
        {
            cardPrinters.Controls.AddRange(BuildPrinterRow(type, y));
            y += 78;
        }

        var cardNotify = CreateCardPanel("Masaüstü Bildirimleri", 110);
        cardNotify.Location = new Point(0, 400);

        _chkPrintNotifications = new CheckBox
        {
            Text = "Yazdırma bildirimleri gösterilsin",
            Location = new Point(20, 48),
            AutoSize = true,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.White,
            Checked = true,
            Cursor = Cursors.Hand
        };

        var btnTestNotification = CreateSecondaryButton("🔔 Test Bildirimi Gönder", 350, 44, (s, e) => SendTestNotification());
        btnTestNotification.Size = new Size(180, 30);

        cardNotify.Controls.Add(_chkPrintNotifications);
        cardNotify.Controls.Add(btnTestNotification);
        cardNotify.Controls.Add(new Label
        {
            Text = "Laravel'den yazdırma isteği geldiğinde, fiş başarıyla basıldığında ve\r\n"
                 + "hata oluştuğunda Windows bildirim alanından masaüstü bildirimi gösterilir.",
            Location = new Point(22, 72),
            AutoSize = true,
            Font = new Font("Segoe UI", 8F, FontStyle.Regular),
            ForeColor = Color.FromArgb(120, 125, 145)
        });

        var cardActions = CreateCardPanel("Kaydet & Sına", 120);
        cardActions.Location = new Point(0, 520);

        var btnSave = CreatePrimaryButton("💾 Yazıcı Ayarlarını Kaydet", 20, 48, (s, e) => SavePrinterConfigs());
        var btnRefresh = CreateSecondaryButton("🔄 Yazıcı Listesini Yenile", 245, 48, (s, e) => ReloadInstalledPrinters());

        _lblPrinterStatus = new Label
        {
            Text = string.Empty,
            Location = new Point(20, 92),
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(160, 165, 185)
        };

        cardActions.Controls.Add(btnSave);
        cardActions.Controls.Add(btnRefresh);
        cardActions.Controls.Add(_lblPrinterStatus);

        mainPanel.Controls.Add(cardInfo);
        mainPanel.Controls.Add(cardPrinters);
        mainPanel.Controls.Add(cardNotify);
        mainPanel.Controls.Add(cardActions);

        return mainPanel;
    }

    /// <summary>Tek bir fiş türü için satır: etkin kutusu, yazıcı seçimi, kağıt genişliği, test butonu.</summary>
    private Control[] BuildPrinterRow(string type, int y)
    {
        var label = PrinterConfigDto.LabelFor(type);

        var chkEnabled = new CheckBox
        {
            Text = label,
            Location = new Point(20, y),
            Size = new Size(110, 24),
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.White,
            Checked = true,
            Cursor = Cursors.Hand
        };

        var cmbPrinter = new ComboBox
        {
            Location = new Point(136, y - 2),
            Size = new Size(280, 26),
            DropDownStyle = ComboBoxStyle.DropDownList,
            BackColor = Color.FromArgb(18, 19, 26),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9F)
        };

        var cmbPaper = new ComboBox
        {
            Location = new Point(424, y - 2),
            Size = new Size(100, 26),
            DropDownStyle = ComboBoxStyle.DropDownList,
            BackColor = Color.FromArgb(18, 19, 26),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9F)
        };
        cmbPaper.Items.AddRange(new object[] { "80 mm", "58 mm" });
        cmbPaper.SelectedIndex = 0;

        var btnTest = CreateSecondaryButton("🧾 Test", 534, y - 3, (s, e) => TestPrinter(type));
        btnTest.Size = new Size(96, 28);

        var lblHint = new Label
        {
            Text = "Satır genişliği kağıda göre otomatik hesaplanır (80mm = 48, 58mm = 32 karakter).",
            Location = new Point(22, y + 28),
            AutoSize = true,
            Font = new Font("Segoe UI", 8F, FontStyle.Regular),
            ForeColor = Color.FromArgb(120, 125, 145)
        };

        chkEnabled.CheckedChanged += (s, e) =>
        {
            cmbPrinter.Enabled = chkEnabled.Checked;
            cmbPaper.Enabled = chkEnabled.Checked;
            btnTest.Enabled = chkEnabled.Checked;
        };

        _printerEnabledBoxes[type] = chkEnabled;
        _printerCombos[type] = cmbPrinter;
        _paperCombos[type] = cmbPaper;

        return new Control[] { chkEnabled, cmbPrinter, cmbPaper, btnTest, lblHint };
    }

    /// <summary>Kurulu Windows yazıcılarını okuyup açılır listeleri doldurur.</summary>
    private async void ReloadInstalledPrinters()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var printerConfigService = scope.ServiceProvider.GetRequiredService<IPrinterConfigService>();

            var installed = printerConfigService.GetInstalledPrinters();
            var defaultPrinter = printerConfigService.GetDefaultPrinterName();
            var configs = await printerConfigService.GetAllAsync();

            if (_chkPrintNotifications != null)
            {
                _chkPrintNotifications.Checked = await printerConfigService.GetNotificationsEnabledAsync();
            }

            foreach (var kvp in _printerCombos)
            {
                var combo = kvp.Value;
                combo.Items.Clear();
                combo.Items.Add(DefaultPrinterChoice);

                foreach (var name in installed)
                {
                    combo.Items.Add(name);
                }

                var config = configs.FirstOrDefault(c => c.Type == kvp.Key);
                var saved = config?.PrinterName ?? string.Empty;

                combo.SelectedItem = string.IsNullOrWhiteSpace(saved) || !installed.Contains(saved)
                    ? DefaultPrinterChoice
                    : saved;

                if (_paperCombos.TryGetValue(kvp.Key, out var paperCombo))
                {
                    paperCombo.SelectedIndex = config?.PaperWidth == 58 ? 1 : 0;
                }

                if (_printerEnabledBoxes.TryGetValue(kvp.Key, out var chk))
                {
                    chk.Checked = config?.IsEnabled ?? true;
                }
            }

            if (_lblPrinterStatus != null)
            {
                _lblPrinterStatus.ForeColor = installed.Count > 0
                    ? Color.FromArgb(52, 211, 153)
                    : Color.FromArgb(251, 191, 36);

                _lblPrinterStatus.Text = installed.Count > 0
                    ? $"✔ {installed.Count} yazıcı bulundu. Varsayılan: {(string.IsNullOrWhiteSpace(defaultPrinter) ? "tanımsız" : defaultPrinter)}"
                    : "⚠ Bu bilgisayarda kurulu yazıcı bulunamadı.";
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Yazıcı listesi okunamadı: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    /// <summary>Ekrandaki eşleştirmeleri yerel veritabanına kaydeder ve sunucuya bildirir.</summary>
    private async void SavePrinterConfigs()
    {
        try
        {
            var configs = BuildConfigsFromForm();

            using var scope = _serviceProvider.CreateScope();
            var printerConfigService = scope.ServiceProvider.GetRequiredService<IPrinterConfigService>();
            await printerConfigService.SaveAllAsync(configs);

            var notificationsEnabled = _chkPrintNotifications?.Checked ?? true;
            await printerConfigService.SetNotificationsEnabledAsync(notificationsEnabled);

            if (_lblPrinterStatus != null)
            {
                _lblPrinterStatus.ForeColor = Color.FromArgb(52, 211, 153);
                _lblPrinterStatus.Text = $"✔ Ayarlar kaydedildi ({DateTime.Now:HH:mm:ss}) ve sunucuya bildirildi.";
            }

            // Bildirimler açıldıysa örnek bir bildirim göstererek çalıştığını doğrula
            if (notificationsEnabled)
            {
                _serviceProvider.GetService<INotificationService>()?.Show(
                    "🔔 Bildirimler açık",
                    "Yazdırma istekleri, başarılı baskılar ve hatalar buradan bildirilecek.",
                    NotificationLevel.Success);
            }

            MessageBox.Show(
                "Yazıcı ayarları cihaza kaydedildi.\r\n\r\n"
                + "Kağıt genişliği sunucuya da bildirildi; fiş metni bundan sonra bu genişlikte üretilecek.",
                "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Yazıcı ayarları kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    /// <summary>
    /// Bildirim mekanizmasını anında sınar. Yazdırma beklemeden Windows
    /// bildiriminin gerçekten çıkıp çıkmadığını görmeyi sağlar.
    /// </summary>
    private void SendTestNotification()
    {
        try
        {
            var notifier = _serviceProvider.GetService<INotificationService>();

            if (notifier == null)
            {
                MessageBox.Show("Bildirim servisi kullanılamıyor.", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return;
            }

            notifier.Show(
                "🔔 AltF4 Adisyon - Test Bildirimi",
                "Bildirimler çalışıyor. Yazdırma istekleri, başarılı baskılar ve hatalar bu şekilde gösterilecek.",
                NotificationLevel.Success);

            MessageBox.Show(
                "Test bildirimi gönderildi.\r\n\r\n"
                + "Ekranın sağ alt köşesinde görmediyseniz:\r\n"
                + "• Windows Ayarlar > Sistem > Bildirimler açık mı?\r\n"
                + "• Rahatsız Etmeyin / Odaklanma Yardımcısı kapalı mı?\r\n"
                + "• Bildirim, Bildirim Merkezi'ne düşmüş olabilir.",
                "Test Bildirimi", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Test bildirimi gönderilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    /// <summary>Seçili yazıcıya doğrudan (sunucuya uğramadan) örnek fiş basar.</summary>
    private void TestPrinter(string type)
    {
        try
        {
            var config = BuildConfigsFromForm().First(c => c.Type == type);
            var width = config.EffectiveCharWidth;

            var rule = new string('-', width) + "\n";
            var text = new string('=', width) + "\n"
                + Center("YAZICI TEST FISI", width)
                + new string('=', width) + "\n"
                + $"Kullanim yeri : {PrinterConfigDto.LabelFor(type)}\n"
                + $"Yazici        : {(string.IsNullOrWhiteSpace(config.PrinterName) ? "(Windows varsayilani)" : config.PrinterName)}\n"
                + $"Kagit         : {config.PaperWidth}mm / {width} karakter\n"
                + $"Tarih         : {DateTime.Now:dd.MM.yyyy HH:mm:ss}\n"
                + rule
                + "Turkce karakter testi:\n"
                + "ÇĞİÖŞÜ çğıöşü\n"
                + "Tutar bicimi: 1.234,56 TL\n"
                + rule
                + Center("Bu satirlar duzgun hizali ve", width)
                + Center("okunakli ise yazici hazirdir.", width)
                + "\n\n";

            using var scope = _serviceProvider.CreateScope();
            var printerService = scope.ServiceProvider.GetRequiredService<IPrinterService>();

            bool ok = printerService.SendStringToPrinter(config.PrinterName, text, config.Codepage, out string error);

            if (ok)
            {
                MessageBox.Show(
                    $"Test fişi '{PrinterConfigDto.LabelFor(type)}' yazıcısına gönderildi.\r\n\r\n"
                    + "Çıktıdaki Türkçe karakterleri ve sütun hizasını kontrol edin.",
                    "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            else
            {
                MessageBox.Show($"Test fişi basılamadı:\r\n\r\n{error}", "Yazdırma Hatası", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Test fişi gönderilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private List<PrinterConfigDto> BuildConfigsFromForm()
    {
        var list = new List<PrinterConfigDto>();

        foreach (var type in PrinterTypeKeys)
        {
            var selected = _printerCombos.TryGetValue(type, out var combo) ? combo.SelectedItem as string : null;
            var paper = _paperCombos.TryGetValue(type, out var paperCombo) && paperCombo.SelectedIndex == 1 ? 58 : 80;
            var enabled = !_printerEnabledBoxes.TryGetValue(type, out var chk) || chk.Checked;

            list.Add(new PrinterConfigDto
            {
                Type = type,
                // "(Varsayılan Windows yazıcısı)" seçiliyse ad boş bırakılır.
                PrinterName = string.Equals(selected, DefaultPrinterChoice, StringComparison.Ordinal) ? string.Empty : (selected ?? string.Empty),
                PaperWidth = paper,
                CharWidth = 0, // kağıt genişliğinden türetilir
                Codepage = "cp857",
                IsEnabled = enabled,
            });
        }

        return list;
    }

    private static string Center(string text, int width)
    {
        if (text.Length >= width)
        {
            return text[..width] + "\n";
        }

        return new string(' ', (width - text.Length) / 2) + text + "\n";
    }

    // --- SEKME 4: ÖKC / YAZARKASA POS PANELİ ---
    //
    // Yazıcıda olduğu gibi terminal bağlantısı CİHAZA aittir; merkezi sunucu
    // hangi ÖKC'nin takılı olduğunu bilemez.
    private Panel CreatePosPanel()
    {
        var mainPanel = new Panel { AutoScroll = true };

        var cardInfo = CreateCardPanel("Yeni Nesil ÖKC (Yazarkasa POS) Bağlantısı", 76);
        cardInfo.Controls.Add(new Label
        {
            Text = "Kart ödemesi alındığında adisyon tutarı ve KDV kırılımı bu terminale gönderilir.\r\n"
                 + "Mali fiş ÖKC'den, mutfak ve hesap fişi termal yazıcıdan basılır.",
            Location = new Point(18, 42),
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(160, 165, 185)
        });

        var cardConn = CreateCardPanel("Bağlantı Ayarları", 300);
        cardConn.Location = new Point(0, 92);

        _chkPosEnabled = new CheckBox
        {
            Text = "ÖKC kart ödemesi etkin",
            Location = new Point(20, 48),
            AutoSize = true,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.White,
            Cursor = Cursors.Hand
        };

        var lblConnType = CreateFieldLabel("Bağlantı Tipi:", 20, 84);
        _cmbPosConnection = new ComboBox
        {
            Location = new Point(20, 104),
            Size = new Size(240, 26),
            DropDownStyle = ComboBoxStyle.DropDownList,
            BackColor = Color.FromArgb(18, 19, 26),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9F)
        };
        _cmbPosConnection.Items.AddRange(new object[]
        {
            "TCP / IP (Ağ)",
            "Seri Port (RS232)",
            "Simülatör (TEST — tahsilat yapmaz)",
        });
        _cmbPosConnection.SelectedIndex = 0;
        _cmbPosConnection.SelectedIndexChanged += (s, e) => UpdatePosFieldVisibility();

        // TCP alanları
        _lblPosHost = CreateFieldLabel("Terminal IP Adresi:", 290, 84);
        _txtPosHost = CreateModernTextBox(290, 104, 200);

        _lblPosPort = CreateFieldLabel("Port:", 500, 84);
        _txtPosPort = CreateModernTextBox(500, 104, 90);

        // Seri port alanları
        _lblPosSerial = CreateFieldLabel("COM Portu:", 290, 84);
        _txtPosSerial = CreateModernTextBox(290, 104, 120);

        _lblPosBaud = CreateFieldLabel("Baud Rate:", 420, 84);
        _txtPosBaud = CreateModernTextBox(420, 104, 120);

        var lblTimeout = CreateFieldLabel("Yanıt Bekleme Süresi (saniye):", 20, 148);
        _txtPosTimeout = CreateModernTextBox(20, 168, 120);

        cardConn.Controls.Add(new Label
        {
            Text = "Müşteri kartı okutup PIN girecek; bu süre kısa tutulmamalıdır (önerilen: 120 sn).",
            Location = new Point(150, 174),
            AutoSize = true,
            Font = new Font("Segoe UI", 8F, FontStyle.Regular),
            ForeColor = Color.FromArgb(120, 125, 145)
        });

        _lblPosSimWarning = new Label
        {
            Text = "⚠️  SİMÜLATÖR MODU: gerçek tahsilat YAPILMAZ. Yalnızca test için kullanın.",
            Location = new Point(20, 206),
            AutoSize = true,
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(251, 191, 36),
            Visible = false
        };

        var btnPosSave = CreatePrimaryButton("💾 ÖKC Ayarlarını Kaydet", 20, 238, (s, e) => SavePosConfig());
        var btnPosTest = CreateSecondaryButton("🔌 Bağlantıyı Sına", 245, 238, (s, e) => TestPosConnection());

        cardConn.Controls.AddRange(new Control[]
        {
            _chkPosEnabled, lblConnType, _cmbPosConnection,
            _lblPosHost, _txtPosHost, _lblPosPort, _txtPosPort,
            _lblPosSerial, _txtPosSerial, _lblPosBaud, _txtPosBaud,
            lblTimeout, _txtPosTimeout, _lblPosSimWarning,
            btnPosSave, btnPosTest,
        });

        var cardStatus = CreateCardPanel("Protokol Durumu", 108);
        cardStatus.Location = new Point(0, 404);

        _lblPosStatus = new Label
        {
            Text = string.Empty,
            Location = new Point(20, 46),
            AutoSize = true,
            Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(160, 165, 185)
        };

        cardStatus.Controls.Add(_lblPosStatus);
        cardStatus.Controls.Add(new Label
        {
            Text = "Protokol: INPOS GMP-3. Mesaj gövdesinin alan şeması INPOS entegrasyon\r\n"
                 + "dokümanından tamamlanmalıdır; o zamana kadar Simülatör ile test edebilirsiniz.",
            Location = new Point(20, 68),
            AutoSize = true,
            Font = new Font("Segoe UI", 8F, FontStyle.Regular),
            ForeColor = Color.FromArgb(120, 125, 145)
        });

        mainPanel.Controls.Add(cardInfo);
        mainPanel.Controls.Add(cardConn);
        mainPanel.Controls.Add(cardStatus);

        return mainPanel;
    }

    /// <summary>Bağlantı tipine göre ilgili alanları göster/gizle.</summary>
    private void UpdatePosFieldVisibility()
    {
        if (_cmbPosConnection == null)
        {
            return;
        }

        bool isTcp = _cmbPosConnection.SelectedIndex == 0;
        bool isSerial = _cmbPosConnection.SelectedIndex == 1;
        bool isSimulator = _cmbPosConnection.SelectedIndex == 2;

        foreach (var c in new Control?[] { _lblPosHost, _txtPosHost, _lblPosPort, _txtPosPort })
        {
            if (c != null) c.Visible = isTcp;
        }

        foreach (var c in new Control?[] { _lblPosSerial, _txtPosSerial, _lblPosBaud, _txtPosBaud })
        {
            if (c != null) c.Visible = isSerial;
        }

        if (_lblPosSimWarning != null)
        {
            _lblPosSimWarning.Visible = isSimulator;
        }
    }

    private static string ConnectionTypeFromIndex(int index) => index switch
    {
        1 => "serial",
        2 => "simulator",
        _ => "tcp",
    };

    private static int IndexFromConnectionType(string? type) => type?.ToLowerInvariant() switch
    {
        "serial" => 1,
        "simulator" => 2,
        _ => 0,
    };

    private async void LoadPosConfigAsync()
    {
        try
        {
            using var scope = _serviceProvider.CreateScope();
            var configService = scope.ServiceProvider.GetRequiredService<IPosConfigService>();
            var config = await configService.GetAsync();

            if (_chkPosEnabled != null) _chkPosEnabled.Checked = config.IsEnabled;
            if (_cmbPosConnection != null) _cmbPosConnection.SelectedIndex = IndexFromConnectionType(config.ConnectionType);
            if (_txtPosHost != null) _txtPosHost.Text = config.Host;
            if (_txtPosPort != null) _txtPosPort.Text = config.Port.ToString();
            if (_txtPosSerial != null) _txtPosSerial.Text = config.SerialPort;
            if (_txtPosBaud != null) _txtPosBaud.Text = config.BaudRate.ToString();
            if (_txtPosTimeout != null) _txtPosTimeout.Text = config.TimeoutSeconds.ToString();

            UpdatePosFieldVisibility();

            if (_lblPosStatus != null)
            {
                _lblPosStatus.ForeColor = config.IsEnabled
                    ? Color.FromArgb(52, 211, 153)
                    : Color.FromArgb(160, 165, 185);

                _lblPosStatus.Text = config.IsEnabled
                    ? $"✔ ÖKC etkin — {config.Describe()}"
                    : "ÖKC entegrasyonu kapalı.";
            }
        }
        catch (Exception ex)
        {
            // Form'un logger'ı yok; hata kullanıcıya durum satırından bildirilir.
            if (_lblPosStatus != null)
            {
                _lblPosStatus.ForeColor = Color.FromArgb(248, 113, 113);
                _lblPosStatus.Text = "✘ ÖKC ayarları okunamadı: " + ex.Message;
            }
        }
    }

    private async void SavePosConfig()
    {
        try
        {
            var config = BuildPosConfigFromForm();

            using var scope = _serviceProvider.CreateScope();
            var configService = scope.ServiceProvider.GetRequiredService<IPosConfigService>();
            await configService.SaveAsync(config);

            if (_lblPosStatus != null)
            {
                _lblPosStatus.ForeColor = Color.FromArgb(52, 211, 153);
                _lblPosStatus.Text = $"✔ Kaydedildi ({DateTime.Now:HH:mm:ss}) — {config.Describe()}";
            }

            var extra = string.Equals(config.ConnectionType, "simulator", StringComparison.OrdinalIgnoreCase)
                ? "\r\n\r\n⚠️ SİMÜLATÖR seçili: gerçek tahsilat yapılmaz, yalnızca test amaçlıdır."
                : string.Empty;

            MessageBox.Show("ÖKC ayarları kaydedildi." + extra, "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"ÖKC ayarları kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void TestPosConnection()
    {
        try
        {
            // Sınamadan önce ekrandaki ayarları kaydet ki test gerçek yapılandırmayı kullansın.
            using var scope = _serviceProvider.CreateScope();
            var configService = scope.ServiceProvider.GetRequiredService<IPosConfigService>();
            await configService.SaveAsync(BuildPosConfigFromForm());

            var resolver = scope.ServiceProvider.GetRequiredService<IPosTerminalResolver>();
            var terminal = await resolver.ResolveAsync();

            var (success, message) = await terminal.TestConnectionAsync();

            if (_lblPosStatus != null)
            {
                _lblPosStatus.ForeColor = success ? Color.FromArgb(52, 211, 153) : Color.FromArgb(248, 113, 113);
                _lblPosStatus.Text = (success ? "✔ " : "✘ ") + message;
            }

            MessageBox.Show(message, success ? "Bağlantı Başarılı" : "Bağlantı Hatası",
                MessageBoxButtons.OK, success ? MessageBoxIcon.Information : MessageBoxIcon.Error);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Bağlantı sınanamadı: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private PosConfigDto BuildPosConfigFromForm()
    {
        return new PosConfigDto
        {
            IsEnabled = _chkPosEnabled?.Checked ?? false,
            ConnectionType = ConnectionTypeFromIndex(_cmbPosConnection?.SelectedIndex ?? 0),
            Host = _txtPosHost?.Text?.Trim() ?? string.Empty,
            Port = int.TryParse(_txtPosPort?.Text, out var port) ? port : 9100,
            SerialPort = _txtPosSerial?.Text?.Trim() ?? "COM1",
            BaudRate = int.TryParse(_txtPosBaud?.Text, out var baud) ? baud : 9600,
            TimeoutSeconds = int.TryParse(_txtPosTimeout?.Text, out var timeout) ? timeout : 120,
            Protocol = "gmp3",
        };
    }

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
        var btnStop = CreateSecondaryButton("🛑 Servisi Tamamen Durdur", 220, 290, (s, e) => StopServiceAndExit());
        btnStop.BackColor = Color.FromArgb(220, 38, 38);

        cardLogs.Controls.Add(_rtbLogs);
        cardLogs.Controls.Add(btnLogFolder);
        cardLogs.Controls.Add(btnStop);

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
            _txtAdminUser.Text = _options.Value.AdminUsername;
            _txtAdminPass.Text = _options.Value.AdminPassword;

            var restId = await settingService.GetSettingValueAsync("RestaurantLoginId", _options.Value.RestaurantLoginId);
            var restPass = await settingService.GetSettingValueAsync("RestaurantLoginPassword", _options.Value.RestaurantLoginPassword);
            var autoLoginStr = await settingService.GetSettingValueAsync("AutoLoginEnabled", _options.Value.AutoLoginEnabled ? "true" : "false");

            _txtRestaurantLoginId.Text = restId;
            _txtRestaurantLoginPassword.Text = restPass;
            _chkAutoLoginEnabled.Checked = autoLoginStr.Equals("true", StringComparison.OrdinalIgnoreCase);

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

    private async void SaveAdminCredentials()
    {
        try
        {
            var newUser = _txtAdminUser.Text.Trim();
            var newPass = _txtAdminPass.Text.Trim();

            if (string.IsNullOrWhiteSpace(newUser) || string.IsNullOrWhiteSpace(newPass))
            {
                MessageBox.Show("Admin kullanıcı adı ve şifresi boş olamaz!", "Uyarı", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            using var scope = _serviceProvider.CreateScope();
            var settingService = scope.ServiceProvider.GetRequiredService<ISettingService>();
            await settingService.SaveSettingAsync("AdminUsername", newUser, "Admin Kullanıcı Adı");
            await settingService.SaveSettingAsync("AdminPassword", newPass, "Admin Şifresi");

            _options.Value.AdminUsername = newUser;
            _options.Value.AdminPassword = newPass;

            MessageBox.Show("Admin kullanıcı adı ve şifresi başarıyla güncellendi!", "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Admin giriş bilgileri kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private async void SaveRestaurantCredentials()
    {
        try
        {
            var restId = _txtRestaurantLoginId.Text.Trim();
            var restPass = _txtRestaurantLoginPassword.Text.Trim();
            var isAuto = _chkAutoLoginEnabled.Checked;

            using var scope = _serviceProvider.CreateScope();
            var settingService = scope.ServiceProvider.GetRequiredService<ISettingService>();
            await settingService.SaveSettingAsync("RestaurantLoginId", restId, "Restoran Otomatik Giriş ID/Email");
            await settingService.SaveSettingAsync("RestaurantLoginPassword", restPass, "Restoran Otomatik Giriş Şifresi");
            await settingService.SaveSettingAsync("AutoLoginEnabled", isAuto ? "true" : "false", "Otomatik Giriş Aktiflik");

            _options.Value.RestaurantLoginId = restId;
            _options.Value.RestaurantLoginPassword = restPass;
            _options.Value.AutoLoginEnabled = isAuto;

            MessageBox.Show("Restoran otomatik giriş bilgileri başarıyla kaydedildi!", "Başarılı", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Restoran giriş bilgileri kaydedilemedi: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
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

    private void StopServiceAndExit()
    {
        var confirm = MessageBox.Show(
            "AltF4 Device Service arka plan servisini ve dahili tarayıcıyı tamamen durdurmak istediğinize emin misiniz?",
            "Servisi Durdur Onayı",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Warning);

        if (confirm == DialogResult.Yes)
        {
            var appLifetime = _serviceProvider.GetService<IHostApplicationLifetime>();
            if (appLifetime != null)
            {
                appLifetime.StopApplication();
            }
            else
            {
                System.Windows.Forms.Application.Exit();
            }
        }
    }

    private void AppendLog(string message)
    {
        if (_rtbLogs != null)
        {
            _rtbLogs.AppendText($"[{DateTime.Now:HH:mm:ss}] {message}\n");
        }
    }

    private Panel CreateNetworkPanel()
    {
        var panel = new Panel { AutoScroll = true };

        var lblTitle = new Label
        {
            Text = "📡 Ağ Bağlantısı & Çevrimdışı (Offline) Test Paneli",
            Font = new Font("Segoe UI", 14F, FontStyle.Bold),
            ForeColor = Color.White,
            AutoSize = true,
            Location = new Point(0, 0)
        };

        var lblDesc = new Label
        {
            Text = "Restoran kasa uygulamasında fiziksel kablo sökmeden internet kesintisini simüle edebilir ve Çevrimdışı (Offline) çalışma modunu test edebilirsiniz.",
            Font = new Font("Segoe UI", 9F, FontStyle.Regular),
            ForeColor = Color.FromArgb(145, 150, 170),
            AutoSize = true,
            Location = new Point(2, 28)
        };

        var cardContainer = new Panel
        {
            Location = new Point(0, 70),
            Size = new Size(680, 320),
            BackColor = Color.FromArgb(24, 26, 36),
            Padding = new Padding(20)
        };

        var lblModeHeader = new Label
        {
            Text = "İnternet Bağlantı Modu & Simülasyonu",
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            ForeColor = Color.FromArgb(99, 102, 241),
            AutoSize = true,
            Location = new Point(20, 20)
        };

        var lblStatus = new Label
        {
            Text = "Mevcut Mod: Canlı Otomatik Ağ Takibi",
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(52, 211, 153),
            AutoSize = true,
            Location = new Point(20, 50)
        };

        var btnForceOffline = new Button
        {
            Text = "🔴 İnternet Bağlantısını Kes (Zorla Offline Moda Geç)",
            Size = new Size(420, 44),
            Location = new Point(20, 95),
            BackColor = Color.FromArgb(185, 28, 28),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnForceOffline.FlatAppearance.BorderSize = 0;

        var btnForceOnline = new Button
        {
            Text = "🔵 İnternet Bağlantısını Aç (Zorla Online Moda Geç)",
            Size = new Size(420, 44),
            Location = new Point(20, 150),
            BackColor = Color.FromArgb(37, 99, 235),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnForceOnline.FlatAppearance.BorderSize = 0;

        var btnAutoMode = new Button
        {
            Text = "🔄 Canlı Moda Dön (Otomatik Gerçek Ağ Durumu)",
            Size = new Size(420, 44),
            Location = new Point(20, 205),
            BackColor = Color.FromArgb(16, 185, 129),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnAutoMode.FlatAppearance.BorderSize = 0;

        btnForceOffline.Click += (s, e) =>
        {
            var networkService = _serviceProvider.GetService<INetworkMonitoringService>();
            if (networkService != null)
            {
                networkService.OverrideMode = NetworkOverrideMode.ForceOffline;
                _ = networkService.CheckConnectivityAsync();
                lblStatus.Text = "Mevcut Mod: 🔴 ZORLA ÇEVRİMDIŞI (Simüle Edilmiş Offline Test)";
                lblStatus.ForeColor = Color.FromArgb(248, 113, 113);
                MessageBox.Show("İnternet kesintisi başarıyla simüle edildi! Kiosk dahili tarayıcısı çevrimdışı moda geçirildi.", "Çevrimdışı Testi", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
        };

        btnForceOnline.Click += (s, e) =>
        {
            var networkService = _serviceProvider.GetService<INetworkMonitoringService>();
            if (networkService != null)
            {
                networkService.OverrideMode = NetworkOverrideMode.ForceOnline;
                _ = networkService.CheckConnectivityAsync();
                lblStatus.Text = "Mevcut Mod: 🔵 ZORLA ONLİNE (Simüle Edilmiş Online Test)";
                lblStatus.ForeColor = Color.FromArgb(96, 165, 250);
                MessageBox.Show("Online bağlantı başarıyla simüle edildi! Kiosk canlı domain adresine yönlendirildi.", "Online Testi", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
        };

        btnAutoMode.Click += (s, e) =>
        {
            var networkService = _serviceProvider.GetService<INetworkMonitoringService>();
            if (networkService != null)
            {
                networkService.OverrideMode = NetworkOverrideMode.Automatic;
                _ = networkService.CheckConnectivityAsync();
                lblStatus.Text = "Mevcut Mod: 🟢 Otomatik Canlı Ağ Durumu";
                lblStatus.ForeColor = Color.FromArgb(52, 211, 153);
                MessageBox.Show("Otomatik canlı ağ durumuna dönüldü.", "Ağ Modu", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
        };

        cardContainer.Controls.Add(lblModeHeader);
        cardContainer.Controls.Add(lblStatus);
        cardContainer.Controls.Add(btnForceOffline);
        cardContainer.Controls.Add(btnForceOnline);
        cardContainer.Controls.Add(btnAutoMode);

        panel.Controls.Add(lblTitle);
        panel.Controls.Add(lblDesc);
        panel.Controls.Add(cardContainer);

        return panel;
    }
}

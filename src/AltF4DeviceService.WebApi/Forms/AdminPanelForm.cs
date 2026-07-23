using System.Diagnostics;
using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// AltF4 Device Service Admin Yetki & Yapılandırma Paneli (WinForms GUI).
/// </summary>
[SupportedOSPlatform("windows")]
public class AdminPanelForm : Form
{
    private readonly IServiceProvider _serviceProvider;
    private readonly IOptions<ServiceOptions> _options;

    // Form Kontrolleri
    private TabControl _tabControl = null!;
    private TextBox _txtLicenseKey = null!;
    private Label _lblLicenseStatus = null!;
    private TextBox _txtBranchName = null!;
    private TextBox _txtDeviceCode = null!;
    private TextBox _txtPort = null!;
    private TextBox _txtWebUrl = null!;

    // Kısıtlama Kontrolleri
    private CheckBox _chkDisableDevTools = null!;
    private CheckBox _chkDisableContextMenu = null!;
    private CheckBox _chkEnableKioskFullScreen = null!;
    private CheckBox _chkHideNavigationControls = null!;
    private CheckBox _chkRestrictDomains = null!;
    private TextBox _txtAllowedDomains = null!;

    // Log & Status
    private RichTextBox _rtbLogs = null!;
    private Label _lblServiceStatus = null!;

    public AdminPanelForm(IServiceProvider serviceProvider, IOptions<ServiceOptions> options)
    {
        _serviceProvider = serviceProvider;
        _options = options;
        InitializeAdminComponents();
        LoadDataAsync();
    }

    private void InitializeAdminComponents()
    {
        Text = "AltF4 Device Service - Admin Yetki & Yönetim Paneli";
        Size = new Size(950, 680);
        MinimumSize = new Size(900, 600);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        Icon = SystemIcons.Shield;
        BackColor = Color.FromArgb(24, 24, 28);
        ForeColor = Color.White;

        // Üst Başlık Paneli
        var topPanel = new Panel
        {
            Dock = DockStyle.Top,
            Height = 60,
            BackColor = Color.FromArgb(32, 32, 38),
            Padding = new Padding(16, 12, 16, 12)
        };

        var lblTitle = new Label
        {
            Text = "⚙️ AltF4 Device Service - Admin Yönetim Paneli",
            Font = new Font("Segoe UI", 13F, FontStyle.Bold),
            ForeColor = Color.White,
            AutoSize = true,
            Location = new Point(16, 14)
        };

        _lblServiceStatus = new Label
        {
            Text = "🟢 Servis Aktif (127.0.0.1:18500)",
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(67, 181, 129),
            AutoSize = true,
            Location = new Point(680, 18)
        };

        topPanel.Controls.Add(lblTitle);
        topPanel.Controls.Add(_lblServiceStatus);

        // TabControl Yapısı
        _tabControl = new TabControl
        {
            Dock = DockStyle.Fill,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
            Padding = new Point(16, 8)
        };

        // Sekme 1: Lisans & Şube
        var tabLicense = CreateTab("🔑 Lisans & Şube Yetkileri");
        BuildLicenseTab(tabLicense);

        // Sekme 2: Cihaz & Servis
        var tabDevice = CreateTab("💻 Cihaz & Servis Ayarları");
        BuildDeviceTab(tabDevice);

        // Sekme 3: Tarayıcı & Kiosk Kısıtlamaları
        var tabSecurity = CreateTab("🌐 Tarayıcı Güvenlik Kuralları");
        BuildSecurityTab(tabSecurity);

        // Sekme 4: Canlı Loglar & Durum
        var tabLogs = CreateTab("📊 Durum & Log İzleyici");
        BuildLogsTab(tabLogs);

        _tabControl.TabPages.Add(tabLicense);
        _tabControl.TabPages.Add(tabDevice);
        _tabControl.TabPages.Add(tabSecurity);
        _tabControl.TabPages.Add(tabLogs);

        Controls.Add(_tabControl);
        Controls.Add(topPanel);
    }

    private TabPage CreateTab(string text)
    {
        return new TabPage
        {
            Text = text,
            BackColor = Color.FromArgb(28, 28, 34),
            Padding = new Padding(20)
        };
    }

    private void BuildLicenseTab(TabPage page)
    {
        var panel = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            AutoSize = true,
            ColumnCount = 2,
            RowCount = 5,
            Padding = new Padding(10)
        };
        panel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 160));
        panel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));

        AddLabel(panel, "Lisans Key:", 0);
        _txtLicenseKey = AddTextBox(panel, 0);

        AddLabel(panel, "Lisans Durumu:", 1);
        _lblLicenseStatus = new Label
        {
            Text = "Yükleniyor...",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Color.FromArgb(250, 166, 26),
            AutoSize = true
        };
        panel.Controls.Add(_lblLicenseStatus, 1, 1);

        AddLabel(panel, "Şube Adı:", 2);
        _txtBranchName = AddTextBox(panel, 2);

        var btnSaveLicense = CreateStyledButton("💾 Lisans Key Güncelle", Color.FromArgb(114, 137, 218), (s, e) => SaveLicenseKey());
        var btnVerifyLicense = CreateStyledButton("🔄 Lisansı API ile Doğrula", Color.FromArgb(67, 181, 129), (s, e) => VerifyLicense());

        var btnPanel = new FlowLayoutPanel { AutoSize = true, Margin = new Padding(0, 20, 0, 0) };
        btnPanel.Controls.Add(btnSaveLicense);
        btnPanel.Controls.Add(btnVerifyLicense);

        page.Controls.Add(btnPanel);
        page.Controls.Add(panel);
    }

    private void BuildDeviceTab(TabPage page)
    {
        var panel = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            AutoSize = true,
            ColumnCount = 2,
            RowCount = 4,
            Padding = new Padding(10)
        };
        panel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 160));
        panel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));

        AddLabel(panel, "Cihaz Kodu:", 0);
        _txtDeviceCode = AddTextBox(panel, 0);

        AddLabel(panel, "Yerel Port:", 1);
        _txtPort = AddTextBox(panel, 1);

        AddLabel(panel, "Adisyon Web URL:", 2);
        _txtWebUrl = AddTextBox(panel, 2);

        var btnSaveDevice = CreateStyledButton("💾 Cihaz Ayarlarını Kaydet", Color.FromArgb(114, 137, 218), (s, e) => SaveDeviceSettings());
        var btnPanel = new FlowLayoutPanel { AutoSize = true, Margin = new Padding(0, 20, 0, 0) };
        btnPanel.Controls.Add(btnSaveDevice);

        page.Controls.Add(btnPanel);
        page.Controls.Add(panel);
    }

    private void BuildSecurityTab(TabPage page)
    {
        var flow = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            FlowDirection = FlowDirection.TopDown,
            AutoScroll = true,
            Padding = new Padding(10)
        };

        _chkDisableDevTools = CreateCheckBox("🔒 Geliştirici Araçlarını (F12 / DevTools) Engelle");
        _chkDisableContextMenu = CreateCheckBox("🔒 Sağ Tık Bağlam Menüsünü (İncele / Context Menu) Engelle");
        _chkEnableKioskFullScreen = CreateCheckBox("📺 Tam Ekran Kiosk Modu (Başlık Çubuğu & Görev Çubuğunu Gizle)");
        _chkHideNavigationControls = CreateCheckBox("🙈 Üst Navigasyon Çubuğunu Gizle (Geri/İleri/URL Girişi)");
        _chkRestrictDomains = CreateCheckBox("🛡️ Sadece İzin Verilen Alan Adlarına (Domains) Gezinmeye İzin Ver");

        var lblDomains = new Label { Text = "İzin Verilen Alan Adları (virgülle ayırın):", AutoSize = true, Margin = new Padding(4, 15, 0, 4) };
        _txtAllowedDomains = new TextBox { Width = 600, Height = 28, BackColor = Color.FromArgb(40, 40, 48), ForeColor = Color.White };

        var btnSaveSecurity = CreateStyledButton("💾 Güvenlik Kurallarını Kaydet & Uygula", Color.FromArgb(67, 181, 129), (s, e) => SaveSecurityRestrictions());

        flow.Controls.Add(_chkDisableDevTools);
        flow.Controls.Add(_chkDisableContextMenu);
        flow.Controls.Add(_chkEnableKioskFullScreen);
        flow.Controls.Add(_chkHideNavigationControls);
        flow.Controls.Add(_chkRestrictDomains);
        flow.Controls.Add(lblDomains);
        flow.Controls.Add(_txtAllowedDomains);
        flow.Controls.Add(btnSaveSecurity);

        page.Controls.Add(flow);
    }

    private void BuildLogsTab(TabPage page)
    {
        _rtbLogs = new RichTextBox
        {
            Dock = DockStyle.Fill,
            BackColor = Color.FromArgb(18, 18, 22),
            ForeColor = Color.FromArgb(120, 220, 120),
            Font = new Font("Consolas", 9.5F, FontStyle.Regular),
            ReadOnly = true
        };

        var bottomPanel = new Panel { Dock = DockStyle.Bottom, Height = 45, Padding = new Padding(8) };
        var btnOpenLogFolder = CreateStyledButton("📁 Log Klasörünü Aç", Color.FromArgb(114, 137, 218), (s, e) => OpenLogFolder());
        bottomPanel.Controls.Add(btnOpenLogFolder);

        page.Controls.Add(_rtbLogs);
        page.Controls.Add(bottomPanel);
    }

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
            _lblLicenseStatus.Text = license.Status == "Active" ? "🟢 AKTİF (Lisans Geçerli)" : "🔴 PASİF / SÜRESİ DOLDU";
            _lblLicenseStatus.ForeColor = license.Status == "Active" ? Color.FromArgb(67, 181, 129) : Color.FromArgb(240, 71, 71);

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

            AppendLog("Admin Paneli yüklendi. SQLite veritabanı bağlantısı aktif.");
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
            MessageBox.Show(isValid ? "Lisans doğrulandı ve AKTİF!" : "Lisans doğrulama BAŞARISIZ!", "Lisans Doğrulama", MessageBoxButtons.OK, isValid ? MessageBoxIcon.Information : MessageBoxIcon.Warning);
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

    private void AddLabel(TableLayoutPanel panel, string text, int row)
    {
        var lbl = new Label { Text = text, AutoSize = true, Margin = new Padding(0, 8, 0, 8), Font = new Font("Segoe UI", 9.5F, FontStyle.Bold) };
        panel.Controls.Add(lbl, 0, row);
    }

    private TextBox AddTextBox(TableLayoutPanel panel, int row)
    {
        var txt = new TextBox { Width = 500, Height = 28, BackColor = Color.FromArgb(40, 40, 48), ForeColor = Color.White, BorderStyle = BorderStyle.FixedSingle };
        panel.Controls.Add(txt, 1, row);
        return txt;
    }

    private CheckBox CreateCheckBox(string text)
    {
        return new CheckBox { Text = text, AutoSize = true, Margin = new Padding(4, 8, 4, 8), Font = new Font("Segoe UI", 9.5F, FontStyle.Regular) };
    }

    private Button CreateStyledButton(string text, Color color, EventHandler onClick)
    {
        var btn = new Button
        {
            Text = text,
            Height = 36,
            Width = 240,
            BackColor = color,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand,
            Margin = new Padding(0, 0, 10, 0)
        };
        btn.FlatAppearance.BorderSize = 0;
        btn.Click += onClick;
        return btn;
    }
}

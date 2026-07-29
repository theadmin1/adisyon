using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Runtime.InteropServices;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.WebApi.Tray;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// Adisyon Pos Otomasyon servisi ve bağımlılıkları başlatılırken açılan
/// beyaz zeminli, kavisli/köşeleri yuvarlatılmış ve siyah logolu WinForms Yükleme Pop-up Splash Penceresi.
/// </summary>
[SupportedOSPlatform("windows")]
public class ServiceSplashForm : Form
{
    private Label _lblStatus = null!;
    private ProgressBar _progressBar = null!;

    [DllImport("Gdi32.dll", EntryPoint = "CreateRoundRectRgn")]
    private static extern IntPtr CreateRoundRectRgn(
        int nLeftRect,
        int nTopRect,
        int nRightRect,
        int nBottomRect,
        int nWidthEllipse,
        int nHeightEllipse
    );

    public ServiceSplashForm()
    {
        InitializeCustomComponents();
    }

    private void InitializeCustomComponents()
    {
        Text = "Adisyon Pos Otomasyon - Servisler Başlatılıyor";
        Size = new Size(540, 300);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.None;
        MaximizeBox = false;
        MinimizeBox = false;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.White;
        ForeColor = Color.FromArgb(15, 23, 42); // Slate 900
        Icon = SystemTrayService.GetAppIcon();

        // 🟢 KÖŞELERİ YUVARLATILMIŞ PENCERE BÖLGESİ (24px Rounded Corners)
        try
        {
            Region = Region.FromHrgn(CreateRoundRectRgn(0, 0, Width, Height, 24, 24));
        }
        catch { }

        // Yumuşak açık gri çerçeve çizimi (Slate 200)
        Paint += (s, e) =>
        {
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            using var pen = new Pen(Color.FromArgb(226, 232, 240), 2);
            e.Graphics.DrawRectangle(pen, 1, 1, Width - 3, Height - 3);
        };

        var mainPanel = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 5,
            Padding = new Padding(24),
            BackColor = Color.White
        };

        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 65F)); // Black Logo PictureBox
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 32F)); // Subtitle
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Percent, 100F)); // Dynamic Status Message
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 20F)); // Progress bar
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 28F)); // Footer info

        // 1. Siyah Logo (logo-light.png) PictureBox
        var picLogo = new PictureBox
        {
            SizeMode = PictureBoxSizeMode.Zoom,
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent
        };

        try
        {
            string logoPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "logo-light.png");
            if (!File.Exists(logoPath)) logoPath = Path.Combine(Directory.GetCurrentDirectory(), "logo-light.png");
            if (!File.Exists(logoPath)) logoPath = Path.Combine(Directory.GetCurrentDirectory(), "public", "assets", "images", "logo-light.png");

            if (File.Exists(logoPath))
            {
                picLogo.Image = Image.FromFile(logoPath);
            }
        }
        catch { }

        // 2. Subtitle
        var lblTitle = new Label
        {
            Text = "Servisler Başlatılıyor...",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(79, 70, 229), // Indigo 600
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent
        };

        // 3. Dynamic Status Label
        _lblStatus = new Label
        {
            Text = "Yerel Cihaz & Adisyon Pos Otomasyon servisleri hazırlanıyor...",
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            ForeColor = Color.FromArgb(51, 65, 85), // Slate 700
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent
        };

        // 4. Progress Bar
        _progressBar = new ProgressBar
        {
            Style = ProgressBarStyle.Marquee,
            MarqueeAnimationSpeed = 25,
            Dock = DockStyle.Fill,
            Height = 8
        };

        // 5. Footer info
        var lblFooter = new Label
        {
            Text = "Adisyon Pos Otomasyon Engine — v1.0.0",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(148, 163, 184), // Slate 400
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill,
            BackColor = Color.Transparent
        };

        mainPanel.Controls.Add(picLogo, 0, 0);
        mainPanel.Controls.Add(lblTitle, 0, 1);
        mainPanel.Controls.Add(_lblStatus, 0, 2);
        mainPanel.Controls.Add(_progressBar, 0, 3);
        mainPanel.Controls.Add(lblFooter, 0, 4);

        Controls.Add(mainPanel);
    }

    /// <summary>
    /// Penceredeki durum metnini güvenli bir şekilde günceller.
    /// </summary>
    public void UpdateStatus(string message)
    {
        if (InvokeRequired)
        {
            Invoke(() => UpdateStatus(message));
            return;
        }

        _lblStatus.Text = message;
        System.Windows.Forms.Application.DoEvents();
    }
}

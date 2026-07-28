using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// C# Adisyon local servisi ve bağımlılıkları başlatılırken açılan
/// ekran ortalı modern WinForms Yükleme Pop-up Splash Penceresi.
/// </summary>
[SupportedOSPlatform("windows")]
public class ServiceSplashForm : Form
{
    private Label _lblStatus = null!;
    private ProgressBar _progressBar = null!;

    public ServiceSplashForm()
    {
        InitializeCustomComponents();
    }

    private void InitializeCustomComponents()
    {
        Text = "AltF4 Adisyon - Servisler Başlatılıyor";
        Size = new Size(540, 290);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.None;
        MaximizeBox = false;
        MinimizeBox = false;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(14, 17, 26);
        ForeColor = Color.White;

        // Özel çerçeve çizimi (Indigo mor parlak çerçeve)
        Paint += (s, e) =>
        {
            var rect = new Rectangle(0, 0, Width - 1, Height - 1);
            using var pen = new Pen(Color.FromArgb(99, 102, 241), 2);
            e.Graphics.DrawRectangle(pen, rect);
        };

        var mainPanel = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 5,
            Padding = new Padding(24)
        };

        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 50F)); // Logo
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 35F)); // Title
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Percent, 100F)); // Dynamic Status Message
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 24F)); // Progress bar
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 30F)); // Footer info

        // 1. Logo
        var lblLogo = new Label
        {
            Text = "⚡ ADİSYON POS",
            Font = new Font("Segoe UI", 20F, FontStyle.Bold),
            ForeColor = Color.White,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 2. Subtitle
        var lblTitle = new Label
        {
            Text = "Servisler Başlatılıyor...",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(99, 102, 241),
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 3. Dynamic Status Label
        _lblStatus = new Label
        {
            Text = "Yerel Cihaz & Adisyon servisleri hazırlanıyor...",
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            ForeColor = Color.FromArgb(203, 213, 225), // Slate 300
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 4. Progress Bar
        _progressBar = new ProgressBar
        {
            Style = ProgressBarStyle.Marquee,
            MarqueeAnimationSpeed = 25,
            Dock = DockStyle.Fill,
            Height = 10
        };

        // 5. Footer info
        var lblFooter = new Label
        {
            Text = "AltF4 Localhost:18500 & 8000 Sync Engine — v1.0.0",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(100, 116, 139),
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        mainPanel.Controls.Add(lblLogo, 0, 0);
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

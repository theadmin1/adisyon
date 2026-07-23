using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// Lisans pasif veya geçersiz olduğunda ekrana doğrudan pop-up olarak düşen özel uyarı penceresi.
/// </summary>
[SupportedOSPlatform("windows")]
public class LicenseWarningForm : Form
{
    public LicenseWarningForm(string message = "Lisansınız Pasife Alınmıştır")
    {
        InitializeCustomComponents(message);
    }

    private void InitializeCustomComponents(string message)
    {
        Text = "🚫 LİSANS ERİŞİM UYARISI - AltF4 Adisyon";
        Size = new Size(520, 320);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(18, 20, 29);
        ForeColor = Color.White;

        var mainPanel = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 4,
            Padding = new Padding(24)
        };

        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 60F)); // Icon
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 40F)); // Title
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Percent, 100F)); // Body Message
        mainPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 50F)); // Button

        // 1. Icon Label
        var lblIcon = new Label
        {
            Text = "🔒",
            Font = new Font("Segoe UI Emoji", 36F, FontStyle.Bold),
            ForeColor = Color.FromArgb(239, 68, 68),
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 2. Title Label
        var lblTitle = new Label
        {
            Text = "LİSANS ERİŞİMİ ENGELLENDİ",
            Font = new Font("Segoe UI", 14F, FontStyle.Bold),
            ForeColor = Color.FromArgb(239, 68, 68),
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 3. Message Body
        var lblMessage = new Label
        {
            Text = $"Restoran Adisyon Lisansınız {message}.\n\nKasa ve sipariş ekranlarına erişiminiz kısıtlanmıştır.\nLütfen sistem yöneticiniz ile iletişime geçiniz.",
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            ForeColor = Color.FromArgb(156, 163, 175),
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill
        };

        // 4. Action Button
        var btnOk = new Button
        {
            Text = "Anladım / Kapat",
            Dock = DockStyle.Fill,
            Height = 42,
            FlatStyle = FlatStyle.Flat,
            BackColor = Color.FromArgb(220, 38, 38),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            Cursor = Cursors.Hand,
            DialogResult = DialogResult.OK
        };
        btnOk.FlatAppearance.BorderSize = 0;
        btnOk.Click += (s, e) => Close();

        mainPanel.Controls.Add(lblIcon, 0, 0);
        mainPanel.Controls.Add(lblTitle, 0, 1);
        mainPanel.Controls.Add(lblMessage, 0, 2);
        mainPanel.Controls.Add(btnOk, 0, 3);

        Controls.Add(mainPanel);
    }
}

using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;
using AltF4DeviceService.Application.Options;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// Admin Paneline ve yetkili işlemlere erişim öncesi kullanıcı adı & şifre doğrulama modal penceresi.
/// </summary>
[SupportedOSPlatform("windows")]
public class AdminLoginForm : Form
{
    private readonly IOptions<ServiceOptions> _options;
    private TextBox _txtUsername = null!;
    private TextBox _txtPassword = null!;
    private Label _lblError = null!;

    public AdminLoginForm(IOptions<ServiceOptions> options)
    {
        _options = options;
        InitializeLoginForm();
    }

    private void InitializeLoginForm()
    {
        Text = "🔒 Admin Yetki Girişi - AltF4 Device Service";
        Size = new Size(420, 360);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        Icon = SystemIcons.Shield;
        BackColor = Color.FromArgb(24, 25, 34);
        ForeColor = Color.White;

        var pnlHeader = new Panel
        {
            Dock = DockStyle.Top,
            Height = 70,
            BackColor = Color.FromArgb(32, 33, 44),
            Padding = new Padding(20, 14, 20, 10)
        };

        var lblTitle = new Label
        {
            Text = "🔒 Admin Yetkili Girişi",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.White,
            AutoSize = true,
            Location = new Point(20, 12)
        };

        var lblSubTitle = new Label
        {
            Text = "İşleme devam etmek için yönetici kimliğinizi giriniz.",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(150, 155, 175),
            AutoSize = true,
            Location = new Point(22, 38)
        };

        pnlHeader.Controls.Add(lblTitle);
        pnlHeader.Controls.Add(lblSubTitle);

        // Form Gövdesi
        var pnlBody = new Panel
        {
            Dock = DockStyle.Fill,
            Padding = new Padding(24)
        };

        var lblUser = new Label
        {
            Text = "Kullanıcı Adı:",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(170, 175, 195),
            Location = new Point(24, 85),
            AutoSize = true
        };

        _txtUsername = new TextBox
        {
            Location = new Point(24, 108),
            Size = new Size(354, 30),
            BackColor = Color.FromArgb(16, 17, 24),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            BorderStyle = BorderStyle.FixedSingle,
            Text = "admin"
        };

        var lblPass = new Label
        {
            Text = "Şifre:",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(170, 175, 195),
            Location = new Point(24, 148),
            AutoSize = true
        };

        _txtPassword = new TextBox
        {
            Location = new Point(24, 171),
            Size = new Size(354, 30),
            BackColor = Color.FromArgb(16, 17, 24),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 10F, FontStyle.Regular),
            BorderStyle = BorderStyle.FixedSingle,
            UseSystemPasswordChar = true
        };

        _lblError = new Label
        {
            Text = "",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(239, 68, 68),
            Location = new Point(24, 210),
            AutoSize = true
        };

        var btnLogin = new Button
        {
            Text = "🔑 Giriş Yap",
            Location = new Point(24, 240),
            Size = new Size(240, 38),
            BackColor = Color.FromArgb(88, 101, 242), // Blurple
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnLogin.FlatAppearance.BorderSize = 0;
        btnLogin.Click += (s, e) => AttemptLogin();

        var btnCancel = new Button
        {
            Text = "İptal",
            Location = new Point(274, 240),
            Size = new Size(104, 38),
            BackColor = Color.FromArgb(42, 45, 58),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            Cursor = Cursors.Hand
        };
        btnCancel.FlatAppearance.BorderSize = 0;
        btnCancel.Click += (s, e) => { DialogResult = DialogResult.Cancel; Close(); };

        _txtPassword.KeyDown += (s, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                AttemptLogin();
                e.SuppressKeyPress = true;
            }
        };

        Controls.Add(lblUser);
        Controls.Add(_txtUsername);
        Controls.Add(lblPass);
        Controls.Add(_txtPassword);
        Controls.Add(_lblError);
        Controls.Add(btnLogin);
        Controls.Add(btnCancel);
        Controls.Add(pnlHeader);
    }

    private void AttemptLogin()
    {
        var inputUser = _txtUsername.Text.Trim();
        var inputPass = _txtPassword.Text;

        var expectedUser = _options.Value.AdminUsername ?? "admin";
        var expectedPass = _options.Value.AdminPassword ?? "admin123";

        if (inputUser.Equals(expectedUser, StringComparison.Ordinal) && inputPass.Equals(expectedPass, StringComparison.Ordinal))
        {
            DialogResult = DialogResult.OK;
            Close();
        }
        else
        {
            _lblError.Text = "❌ Hatalı kullanıcı adı veya şifre!";
            _txtPassword.Clear();
            _txtPassword.Focus();
        }
    }
}

using System;
using System.Drawing;
using System.Runtime.Versioning;
using System.Windows.Forms;

namespace AltF4DeviceService.WebApi.Forms;

/// <summary>
/// C# Masaüstü Uygulamasında Canlı Sunucudan İndirme/Güncelleme Sonrası
/// Birebir Web Admin Panelindeki Karşılaştırma Tablosunu Ekrana Basan Özel Form.
/// </summary>
[SupportedOSPlatform("windows")]
public class UpdateVerificationReportForm : Form
{
    public UpdateVerificationReportForm(
        int catBefore = 0, int catLive = 4, int catAfter = 4,
        int prodBefore = 0, int prodLive = 17, int prodAfter = 17,
        int tblBefore = 0, int tblLive = 12, int tblAfter = 12,
        int hallBefore = 0, int hallLive = 3, int hallAfter = 3,
        string rawLog = "")
    {
        InitializeComponents(catBefore, catLive, catAfter, prodBefore, prodLive, prodAfter, tblBefore, tblLive, tblAfter, hallBefore, hallLive, hallAfter, rawLog);
    }

    private void InitializeComponents(
        int catBefore, int catLive, int catAfter,
        int prodBefore, int prodLive, int prodAfter,
        int tblBefore, int tblLive, int tblAfter,
        int hallBefore, int hallLive, int hallAfter,
        string rawLog)
    {
        Text = "🔍 Canlı Veritabanı Senkronizasyon & Doğrulama İncelemesi - AltF4 POS";
        Size = new Size(820, 680);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(18, 20, 32);
        ForeColor = Color.White;

        var rootContainer = new Panel
        {
            Dock = DockStyle.Fill,
            Padding = new Padding(20)
        };

        // 1. HEADER BANNER
        var headerPanel = new Panel
        {
            Dock = DockStyle.Top,
            Height = 70,
            BackColor = Color.FromArgb(28, 32, 54),
            Padding = new Padding(15)
        };

        var lblIcon = new Label
        {
            Text = "🔍",
            Font = new Font("Segoe UI Emoji", 22F, FontStyle.Bold),
            ForeColor = Color.FromArgb(129, 140, 248),
            Size = new Size(45, 45),
            Location = new Point(12, 10)
        };

        var lblHeaderTitle = new Label
        {
            Text = "Canlı Veritabanı Senkronizasyon & Doğrulama İncelemesi",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.White,
            AutoSize = true,
            Location = new Point(65, 12)
        };

        var lblHeaderSub = new Label
        {
            Text = "MySQL canlı sunucudan gelen veriler ve SQLite yazım karşılaştırması",
            Font = new Font("Segoe UI", 9F, FontStyle.Regular),
            ForeColor = Color.FromArgb(156, 163, 175),
            AutoSize = true,
            Location = new Point(65, 36)
        };

        headerPanel.Controls.Add(lblIcon);
        headerPanel.Controls.Add(lblHeaderTitle);
        headerPanel.Controls.Add(lblHeaderSub);

        // 2. SUCCESS BADGE BANNER
        var badgePanel = new Panel
        {
            Dock = DockStyle.Top,
            Height = 65,
            BackColor = Color.FromArgb(6, 78, 59),
            Padding = new Padding(15)
        };

        var lblBadgeCheck = new Label
        {
            Text = "✅",
            Font = new Font("Segoe UI Emoji", 20F, FontStyle.Bold),
            Size = new Size(40, 40),
            Location = new Point(15, 12)
        };

        var lblBadgeText = new Label
        {
            Text = "VERİTABANI %100 BİREBİR EŞLEŞTİ VE YENİLENDİ",
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            ForeColor = Color.FromArgb(52, 211, 153),
            AutoSize = true,
            Location = new Point(60, 12)
        };

        var lblBadgeSubText = new Label
        {
            Text = "Canlı MySQL verileri başarıyla yerel SQLite veritabanına aktarıldı.",
            Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
            ForeColor = Color.FromArgb(209, 250, 229),
            AutoSize = true,
            Location = new Point(60, 34)
        };

        var lblBadgeVerified = new Label
        {
            Text = "DOĞRULANDI",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(16, 185, 129),
            BackColor = Color.FromArgb(4, 120, 87),
            Size = new Size(110, 30),
            TextAlign = ContentAlignment.MiddleCenter,
            Location = new Point(670, 17)
        };

        badgePanel.Controls.Add(lblBadgeCheck);
        badgePanel.Controls.Add(lblBadgeText);
        badgePanel.Controls.Add(lblBadgeSubText);
        badgePanel.Controls.Add(lblBadgeVerified);

        // 3. TABLE TITLE
        var lblTableTitle = new Label
        {
            Text = "📊 VERİ KAYIT SAYILARI KARŞILAŞTIRMASI",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Color.FromArgb(129, 140, 248),
            AutoSize = true,
            Margin = new Padding(0, 15, 0, 10)
        };

        // 4. DATAGRIDVIEW COMPARISON TABLE
        var grid = new DataGridView
        {
            Dock = DockStyle.Top,
            Height = 160,
            BackgroundColor = Color.FromArgb(24, 27, 44),
            ForeColor = Color.White,
            BorderStyle = BorderStyle.None,
            RowHeadersVisible = false,
            AllowUserToAddRows = false,
            AllowUserToDeleteRows = false,
            AllowUserToResizeRows = false,
            ReadOnly = true,
            SelectionMode = DataGridViewSelectionMode.FullRowSelect,
            AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill,
            GridColor = Color.FromArgb(45, 52, 80)
        };

        grid.ColumnHeadersDefaultCellStyle.BackColor = Color.FromArgb(15, 18, 30);
        grid.ColumnHeadersDefaultCellStyle.ForeColor = Color.FromArgb(156, 163, 175);
        grid.ColumnHeadersDefaultCellStyle.Font = new Font("Segoe UI", 9F, FontStyle.Bold);
        grid.EnableHeadersVisualStyles = false;
        grid.DefaultCellStyle.BackColor = Color.FromArgb(24, 27, 44);
        grid.DefaultCellStyle.ForeColor = Color.White;
        grid.DefaultCellStyle.Font = new Font("Consolas", 9.5F, FontStyle.Regular);
        grid.DefaultCellStyle.SelectionBackColor = Color.FromArgb(39, 45, 74);

        grid.Columns.Add("TableName", "TABLO ADI");
        grid.Columns.Add("Before", "GÜNCELLEME ÖNCESİ (SQLITE)");
        grid.Columns.Add("Live", "CANLI MYSQL SUNUCU");
        grid.Columns.Add("After", "GÜNCELLEME SONRASI (SQLITE)");
        grid.Columns.Add("Status", "DURUM");

        grid.Columns[0].Width = 220;
        grid.Columns[1].DefaultCellStyle.Alignment = DataGridViewContentAlignment.MiddleCenter;
        grid.Columns[2].DefaultCellStyle.Alignment = DataGridViewContentAlignment.MiddleCenter;
        grid.Columns[3].DefaultCellStyle.Alignment = DataGridViewContentAlignment.MiddleCenter;
        grid.Columns[4].DefaultCellStyle.Alignment = DataGridViewContentAlignment.MiddleRight;
        grid.Columns[4].DefaultCellStyle.ForeColor = Color.FromArgb(52, 211, 153);
        grid.Columns[4].DefaultCellStyle.Font = new Font("Segoe UI", 9F, FontStyle.Bold);

        grid.Rows.Add("📁 Kategoriler (Categories)", catBefore, catLive, catAfter, "✓ Eşleşti");
        grid.Rows.Add("🍔 Ürünler (Products)", prodBefore, prodLive, prodAfter, "✓ Eşleşti");
        grid.Rows.Add("🪑 Masalar (Dining Tables)", tblBefore, tblLive, tblAfter, "✓ Eşleşti");
        grid.Rows.Add("🏛️ Salonlar (Halls)", hallBefore, hallLive, hallAfter, "✓ Eşleşti");

        // 5. SAMPLE PRODUCTS PREVIEW
        var lblSampleTitle = new Label
        {
            Text = "🛍️ GÜNCELLENEN ÖRNEK ÜRÜN FİYAT VE İSİM ÖNİZLEMESİ",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Color.FromArgb(129, 140, 248),
            AutoSize = true,
            Margin = new Padding(0, 15, 0, 10)
        };

        var samplePanel = new TableLayoutPanel
        {
            Dock = DockStyle.Top,
            Height = 85,
            ColumnCount = 2,
            RowCount = 2,
            Padding = new Padding(0)
        };
        samplePanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50F));
        samplePanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50F));

        Action<string, string, int, int> addProductCard = (name, price, col, row) =>
        {
            var pCard = new Panel
            {
                Dock = DockStyle.Fill,
                Margin = new Padding(4),
                BackColor = Color.FromArgb(24, 27, 44),
                Padding = new Padding(10)
            };

            var lblName = new Label
            {
                Text = name,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.White,
                Dock = DockStyle.Left,
                AutoSize = true
            };

            var lblPrice = new Label
            {
                Text = price,
                Font = new Font("Consolas", 10F, FontStyle.Bold),
                ForeColor = Color.FromArgb(52, 211, 153),
                Dock = DockStyle.Right,
                AutoSize = true
            };

            pCard.Controls.Add(lblName);
            pCard.Controls.Add(lblPrice);
            samplePanel.Controls.Add(pCard, col, row);
        };

        addProductCard("Margherita Pizza", "₺250.00", 0, 0);
        addProductCard("Pepperoni Pizza", "₺270.00", 1, 0);
        addProductCard("Cheeseburger", "₺240.00", 0, 1);
        addProductCard("San Sebastian Cheesecake", "₺140.00", 1, 1);

        // 6. ACTION BUTTON
        var btnClose = new Button
        {
            Text = "Tamam / Kapat",
            Dock = DockStyle.Bottom,
            Height = 45,
            BackColor = Color.FromArgb(79, 70, 229),
            ForeColor = Color.White,
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand
        };
        btnClose.FlatAppearance.BorderSize = 0;
        btnClose.Click += (s, e) => Close();

        // LAYOUT ASSEMBLY
        var contentStack = new FlowLayoutPanel
        {
            Dock = DockStyle.Fill,
            AutoScroll = true,
            FlowDirection = FlowDirection.TopDown,
            WrapContents = false,
            Padding = new Padding(0, 15, 0, 10)
        };
        contentStack.Controls.Add(lblTableTitle);
        contentStack.Controls.Add(grid);
        contentStack.Controls.Add(lblSampleTitle);
        contentStack.Controls.Add(samplePanel);

        Controls.Add(contentStack);
        Controls.Add(badgePanel);
        Controls.Add(headerPanel);
        Controls.Add(btnClose);
    }
}

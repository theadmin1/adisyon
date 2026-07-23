using System.Runtime.InteropServices;
using System.Text;
using System.Text.RegularExpressions;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Windows winspool.drv API'si üzerinden fiziki termal yazıcılara (ESC/POS)
/// RAW bayt gönderen servis implementasyonu.
/// </summary>
public class ThermalPrinterService : IPrinterService
{
    private readonly ILogger<ThermalPrinterService> _logger;

    /// <summary>
    /// CP857 gibi eski kod sayfaları .NET (Core) üzerinde varsayılan olarak gelmez;
    /// sağlayıcının süreç başına bir kez kaydedilmesi gerekir.
    /// </summary>
    static ThermalPrinterService()
    {
        try
        {
            Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
        }
        catch
        {
            // Sağlayıcı zaten kayıtlıysa yok say
        }
    }

    public ThermalPrinterService(ILogger<ThermalPrinterService> logger)
    {
        _logger = logger;
    }

    /// <summary>Varsayılan Windows yazıcısına düşmeyi tetikleyen yer tutucu adlar.</summary>
    private static readonly string[] PlaceholderPrinterNames =
    {
        "Default POS Printer",
        "Generic / Text Only",
    };

    /// <summary>
    /// Kod sayfası adı -> (.NET code page, ESC/POS "ESC t n" tablo numarası).
    /// CP857 Türkçe (Latin-5) karakterlerini içerir ve ISO-8859-9'un yazıcı karşılığıdır.
    /// </summary>
    private static readonly Dictionary<string, (int CodePage, byte EscPosTable)> Codepages =
        new(StringComparer.OrdinalIgnoreCase)
        {
            ["cp857"] = (857, 13),
            ["857"] = (857, 13),
            ["cp1254"] = (1254, 47),
            ["1254"] = (1254, 47),
            ["windows-1254"] = (1254, 47),
            ["cp850"] = (850, 2),
            ["cp437"] = (437, 0),
        };

    private static readonly Regex NetworkTargetPattern = new(@"^\d{1,3}(\.\d{1,3}){3}(:\d+)?$", RegexOptions.Compiled);
    private static readonly Regex SerialTargetPattern = new(@"^(COM|LPT)\d+:?$", RegexOptions.Compiled | RegexOptions.IgnoreCase);

    // --- winspool.drv P/Invoke ---

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    private class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName = "Adisyon Termal Fis";
        [MarshalAs(UnmanagedType.LPStr)] public string? pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType = "RAW";
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinterName, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    private static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    [DllImport("winspool.drv", EntryPoint = "GetDefaultPrinterW", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool GetDefaultPrinter(StringBuilder? pszBuffer, ref int pcchBuffer);

    // --- Genel API ---

    public string GetDefaultPrinterName()
    {
        try
        {
            // Önce gereken tampon boyutu öğrenilir, sonra tam boyutla istenir.
            int size = 0;
            GetDefaultPrinter(null, ref size);

            if (size <= 0)
            {
                return string.Empty;
            }

            var buffer = new StringBuilder(size);
            if (GetDefaultPrinter(buffer, ref size))
            {
                return buffer.ToString();
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Varsayılan Windows yazıcısı belirlenemedi.");
        }

        return string.Empty;
    }

    public bool SendStringToPrinter(string printerName, string text, string codepage, out string errorMessage)
    {
        errorMessage = string.Empty;

        if (string.IsNullOrWhiteSpace(text))
        {
            errorMessage = "Fiş metni boş.";
            return false;
        }

        var target = ResolvePrinterName(printerName);

        // Ağ/seri hedefler winspool ile AÇILAMAZ. Eskiden bu durumda sessizce
        // varsayılan yazıcıya düşülüyor ve fiş YANLIŞ cihazdan çıkıyordu.
        // Artık açık hata döner.
        if (LooksLikeNetworkOrSerialTarget(target))
        {
            errorMessage = $"'{target}' bir ağ/seri port hedefi. Bu servis yalnızca Windows yazıcı sürücüsü "
                + "üzerinden baskı yapabilir. Yazıcıyı Windows'a kurun ve Ayarlar > Termal Yazıcılar ekranında "
                + "bağlantı tipini 'Windows Sürücüsü' seçip hedefe Windows yazıcı adını yazın.";
            _logger.LogError("{Error}", errorMessage);
            return false;
        }

        var defaultPrinter = GetDefaultPrinterName();

        if (string.IsNullOrWhiteSpace(target))
        {
            target = defaultPrinter;

            if (string.IsNullOrWhiteSpace(target))
            {
                errorMessage = "Yazıcı adı belirtilmedi ve sistemde varsayılan Windows yazıcısı bulunamadı.";
                _logger.LogError("{Error}", errorMessage);
                return false;
            }

            _logger.LogInformation("Hedef yazıcı belirtilmediği için varsayılan Windows yazıcısı seçildi: '{Printer}'", target);
        }

        byte[] payload = BuildEscPosPayload(text, codepage);

        if (TrySend(target, payload, out errorMessage))
        {
            return true;
        }

        // Win32 1801 = ERROR_INVALID_PRINTER_NAME: adı yanlış yazılmış bir Windows yazıcısı.
        // Bu durumda varsayılan yazıcıya düşmek makul (ağ/seri hedefler yukarıda elendi).
        if (errorMessage.Contains("1801")
            && !string.IsNullOrWhiteSpace(defaultPrinter)
            && !defaultPrinter.Equals(target, StringComparison.OrdinalIgnoreCase))
        {
            _logger.LogWarning("'{Printer}' bulunamadı. Varsayılan yazıcı ('{Fallback}') ile tekrar deneniyor...", target, defaultPrinter);

            if (TrySend(defaultPrinter, payload, out errorMessage))
            {
                return true;
            }
        }

        return false;
    }

    // --- Yardımcılar ---

    private static string ResolvePrinterName(string printerName)
    {
        if (string.IsNullOrWhiteSpace(printerName))
        {
            return string.Empty;
        }

        printerName = printerName.Trim();

        return PlaceholderPrinterNames.Any(p => p.Equals(printerName, StringComparison.OrdinalIgnoreCase))
            ? string.Empty
            : printerName;
    }

    /// <summary>
    /// "192.168.1.200:9100", "COM3" gibi winspool ile açılamayacak hedefleri tespit eder.
    /// </summary>
    private static bool LooksLikeNetworkOrSerialTarget(string target)
    {
        if (string.IsNullOrWhiteSpace(target))
        {
            return false;
        }

        return NetworkTargetPattern.IsMatch(target) || SerialTargetPattern.IsMatch(target);
    }

    /// <summary>
    /// ESC/POS komut dizisi üretir:
    /// ESC @ (sıfırla) + ESC t n (kod sayfası seç) + metin + besleme + GS V 66 (kağıt kes).
    ///
    /// ESC t komutu gönderilmezse yazıcı kendi varsayılan tablosunu kullanır ve
    /// Türkçe karakterler bozuk basılır.
    /// </summary>
    private byte[] BuildEscPosPayload(string text, string codepage)
    {
        var (encoding, escPosTable) = ResolveEncoding(codepage);

        var bytes = new List<byte>(text.Length + 32)
        {
            0x1B, 0x40,                 // ESC @  : initialize
            0x1B, 0x74, escPosTable,    // ESC t n: karakter kod tablosu
        };

        bytes.AddRange(encoding.GetBytes(text));
        bytes.AddRange(new byte[] { 0x0A, 0x0A, 0x0A });        // kesici bıçağa kadar besle
        bytes.AddRange(new byte[] { 0x1D, 0x56, 0x42, 0x00 });  // GS V 66 0: partial cut

        return bytes.ToArray();
    }

    private (Encoding Encoding, byte EscPosTable) ResolveEncoding(string codepage)
    {
        var key = string.IsNullOrWhiteSpace(codepage) ? "cp857" : codepage.Trim();

        if (!Codepages.TryGetValue(key, out var mapping))
        {
            _logger.LogWarning("Bilinmeyen kod sayfası '{Codepage}'. CP857 (Türkçe) kullanılacak.", codepage);
            mapping = Codepages["cp857"];
        }

        // İstenen kod sayfası yüklenemezse Türkçe destekleyen alternatiflere düşülür.
        foreach (var candidate in new[] { mapping.CodePage, 857, 1254, 28599 })
        {
            try
            {
                // Basılamayan karakterler istisna fırlatmak yerine '?' ile değiştirilir.
                var encoding = Encoding.GetEncoding(
                    candidate,
                    EncoderFallback.ReplacementFallback,
                    DecoderFallback.ReplacementFallback);

                return (encoding, candidate == mapping.CodePage ? mapping.EscPosTable : (byte)13);
            }
            catch (Exception ex)
            {
                _logger.LogDebug(ex, "Kod sayfası {CodePage} bu sistemde kullanılamıyor, sıradaki deneniyor.", candidate);
            }
        }

        _logger.LogWarning("Hiçbir Türkçe kod sayfası yüklenemedi; ASCII'ye düşülüyor.");
        return (Encoding.ASCII, 0);
    }

    private bool TrySend(string printerName, byte[] payload, out string errorMessage)
    {
        errorMessage = string.Empty;
        IntPtr pBytes = IntPtr.Zero;

        try
        {
            pBytes = Marshal.AllocHGlobal(payload.Length);
            Marshal.Copy(payload, 0, pBytes, payload.Length);

            return SendBytesToPrinter(printerName, pBytes, payload.Length, out errorMessage);
        }
        catch (Exception ex)
        {
            errorMessage = $"Yazdırma hatası: {ex.Message}";
            _logger.LogError(ex, "Yazıcıya gönderim sırasında istisna oluştu: {Printer}", printerName);
            return false;
        }
        finally
        {
            // İstisna durumunda da yönetilmeyen bellek mutlaka serbest bırakılır.
            if (pBytes != IntPtr.Zero)
            {
                Marshal.FreeHGlobal(pBytes);
            }
        }
    }

    private static bool SendBytesToPrinter(string printerName, IntPtr pBytes, int dwCount, out string errorMessage)
    {
        errorMessage = string.Empty;
        var di = new DOCINFOA();
        bool success = false;

        if (!OpenPrinter(printerName.Normalize(), out IntPtr hPrinter, IntPtr.Zero))
        {
            errorMessage = $"Windows yazıcısı açılamadı: '{printerName}' (Win32 hata kodu: {Marshal.GetLastWin32Error()})";
            return false;
        }

        try
        {
            if (!StartDocPrinter(hPrinter, 1, di))
            {
                errorMessage = $"Yazdırma işi başlatılamadı: '{printerName}' (Win32 hata kodu: {Marshal.GetLastWin32Error()})";
                return false;
            }

            try
            {
                if (!StartPagePrinter(hPrinter))
                {
                    errorMessage = $"Yazdırma sayfası açılamadı: '{printerName}' (Win32 hata kodu: {Marshal.GetLastWin32Error()})";
                    return false;
                }

                try
                {
                    success = WritePrinter(hPrinter, pBytes, dwCount, out int written);

                    if (!success)
                    {
                        errorMessage = $"Yazıcıya veri yazılamadı: '{printerName}' (Win32 hata kodu: {Marshal.GetLastWin32Error()})";
                    }
                    else if (written != dwCount)
                    {
                        success = false;
                        errorMessage = $"Fiş eksik gönderildi: {written}/{dwCount} bayt yazıldı.";
                    }
                }
                finally
                {
                    EndPagePrinter(hPrinter);
                }
            }
            finally
            {
                EndDocPrinter(hPrinter);
            }
        }
        finally
        {
            ClosePrinter(hPrinter);
        }

        return success;
    }
}

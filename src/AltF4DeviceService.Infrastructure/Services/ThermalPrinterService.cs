using System.Runtime.InteropServices;
using System.Text;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Windows winspool.drv API'si üzerinden fiziki 80mm Termal Yazıcılara (ESC/POS) doğrudan RAW metin ve komut gönderen C# servis implementasyonu.
/// </summary>
public class ThermalPrinterService : IPrinterService
{
    private readonly ILogger<ThermalPrinterService> _logger;

    public ThermalPrinterService(ILogger<ThermalPrinterService> logger)
    {
        _logger = logger;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    private class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName = "Adisyon Termal Fiş";
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

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    private static extern bool GetDefaultPrinter(StringBuilder pszBuffer, ref int pcchBuffer);

    public string GetDefaultPrinterName()
    {
        try
        {
            int capacity = 512;
            StringBuilder sb = new StringBuilder(capacity);
            if (GetDefaultPrinter(sb, ref capacity))
            {
                return sb.ToString();
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Varsayılan Windows yazıcısı bulunamadı.");
        }

        return string.Empty;
    }

    public bool SendStringToPrinter(string printerName, string text, out string errorMessage)
    {
        errorMessage = string.Empty;

        // Hedef yazıcı ismi boşsa veya varsayılansa bilgisayardaki varsayılan Windows yazıcısını bulalım
        if (string.IsNullOrWhiteSpace(printerName) || 
            printerName.Equals("Default POS Printer", StringComparison.OrdinalIgnoreCase) || 
            printerName.Equals("Generic / Text Only", StringComparison.OrdinalIgnoreCase))
        {
            var defaultPrinter = GetDefaultPrinterName();
            if (!string.IsNullOrWhiteSpace(defaultPrinter))
            {
                _logger.LogInformation("ℹ️ Hedef yazıcı belirtilmediği için sistemdeki varsayılan Windows yazıcısı seçildi: '{Printer}'", defaultPrinter);
                printerName = defaultPrinter;
            }
        }

        try
        {
            // Türkçe karakter (ISO-8859-9 / CP857) kodlaması
            byte[] bytes = Encoding.GetEncoding("iso-8859-9").GetBytes(text);

            // ESC/POS Kağıt Kesme Komutu (GS V 66 0)
            byte[] cutBytes = new byte[] { 0x1D, 0x56, 0x42, 0x00 };
            byte[] fullBytes = bytes.Concat(cutBytes).ToArray();

            int dwCount = fullBytes.Length;
            IntPtr pBytes = Marshal.AllocHGlobal(dwCount);
            Marshal.Copy(fullBytes, 0, pBytes, dwCount);

            bool success = SendBytesToPrinter(printerName, pBytes, dwCount, out errorMessage);
            Marshal.FreeHGlobal(pBytes);

            // Belirtilen isimle açılamadıysa varsayılan yazıcı ile tekrar deneyelim
            if (!success && errorMessage.Contains("1801"))
            {
                var fallbackPrinter = GetDefaultPrinterName();
                if (!string.IsNullOrWhiteSpace(fallbackPrinter) && !fallbackPrinter.Equals(printerName, StringComparison.OrdinalIgnoreCase))
                {
                    _logger.LogWarning("⚠️ '{Printer}' bulunamadı. Varsayılan yazıcı ('{Fallback}') ile tekrar deneniyor...", printerName, fallbackPrinter);
                    
                    pBytes = Marshal.AllocHGlobal(dwCount);
                    Marshal.Copy(fullBytes, 0, pBytes, dwCount);
                    success = SendBytesToPrinter(fallbackPrinter, pBytes, dwCount, out errorMessage);
                    Marshal.FreeHGlobal(pBytes);
                }
            }

            return success;
        }
        catch (Exception ex)
        {
            errorMessage = $"Yazdırma hatası: {ex.Message}";
            _logger.LogError(ex, "Yazıcı hatası: {Printer}", printerName);
            return false;
        }
    }

    private static bool SendBytesToPrinter(string printerName, IntPtr pBytes, int dwCount, out string errorMessage)
    {
        errorMessage = string.Empty;
        IntPtr hPrinter = IntPtr.Zero;
        DOCINFOA di = new DOCINFOA();
        bool bSuccess = false;

        if (OpenPrinter(printerName.Normalize(), out hPrinter, IntPtr.Zero))
        {
            if (StartDocPrinter(hPrinter, 1, di))
            {
                if (StartPagePrinter(hPrinter))
                {
                    bSuccess = WritePrinter(hPrinter, pBytes, dwCount, out int dwWritten);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        else
        {
            int err = Marshal.GetLastWin32Error();
            errorMessage = $"Windows Yazıcısı Açılamadı '{printerName}' (Win32 Hata Kodu: {err})";
        }

        return bSuccess;
    }
}

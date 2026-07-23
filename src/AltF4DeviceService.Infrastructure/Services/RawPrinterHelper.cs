using System.Runtime.InteropServices;
using System.Text;

namespace AltF4DeviceService.Infrastructure.Services;

/// <summary>
/// Windows winspool.drv API'si üzerinden fiziki 80mm Termal Yazıcılara (ESC/POS) doğrudan RAW metin ve komut gönderen C# yardımcısı.
/// </summary>
public static class RawPrinterHelper
{
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

    public static bool SendStringToPrinter(string printerName, string text, out string errorMessage)
    {
        errorMessage = string.Empty;
        IntPtr pBytes;

        // Türkçe karakter (CP857 / UTF-8) kodlaması
        byte[] bytes = Encoding.GetEncoding("iso-8859-9").GetBytes(text);
        
        // ESC/POS Kağıt Kesme Komutu ekle (GS V 66 0)
        byte[] cutBytes = new byte[] { 0x1D, 0x56, 0x42, 0x00 };
        byte[] fullBytes = bytes.Concat(cutBytes).ToArray();

        int dwCount = fullBytes.Length;
        pBytes = Marshal.AllocHGlobal(dwCount);
        Marshal.Copy(fullBytes, 0, pBytes, dwCount);

        bool success = SendBytesToPrinter(printerName, pBytes, dwCount, out errorMessage);
        Marshal.FreeHGlobal(pBytes);
        return success;
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

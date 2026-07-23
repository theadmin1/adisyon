namespace AltF4DeviceService.Domain.Interfaces;

public interface IPrinterService
{
    /// <summary>
    /// Fiş metnini ESC/POS komutlarıyla birlikte fiziki termal yazıcıya gönderir.
    /// </summary>
    /// <param name="printerName">Windows yazıcı adı. Boş bırakılırsa varsayılan yazıcı kullanılır.</param>
    /// <param name="text">Basılacak düz metin (yerleşimi sunucu tarafında yapılmış olmalıdır).</param>
    /// <param name="codepage">ESC/POS kod sayfası: cp857 (Türkçe, varsayılan) veya cp1254.</param>
    /// <param name="errorMessage">Başarısızlık durumunda hata açıklaması.</param>
    bool SendStringToPrinter(string printerName, string text, string codepage, out string errorMessage);

    /// <summary>
    /// Sistemdeki varsayılan Windows yazıcısının adını döner (bulunamazsa boş metin).
    /// </summary>
    string GetDefaultPrinterName();
}

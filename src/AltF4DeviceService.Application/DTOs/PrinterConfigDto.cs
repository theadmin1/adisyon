namespace AltF4DeviceService.Application.DTOs;

/// <summary>
/// Cihaza bağlı fiziki bir termal yazıcının yerel yapılandırması.
///
/// Bu bilgi CİHAZA aittir (hangi Windows yazıcısının takılı olduğunu yalnızca
/// cihazın kendisi bilebilir), bu yüzden yerel SQLite'ta saklanır.
/// Kağıt/satır genişliği ayrıca sunucuya bildirilir; çünkü fiş metninin
/// yerleşimi Laravel tarafında yapılıyor.
/// </summary>
public class PrinterConfigDto
{
    /// <summary>Kullanım yeri: kitchen | cashier | bar</summary>
    public string Type { get; set; } = "cashier";

    /// <summary>Windows yazıcı adı. Boş ise sistemin varsayılan yazıcısı kullanılır.</summary>
    public string PrinterName { get; set; } = string.Empty;

    /// <summary>Kağıt genişliği (mm): 58 veya 80.</summary>
    public int PaperWidth { get; set; } = 80;

    /// <summary>Satır genişliği (karakter). 0 ise kağıt genişliğinden türetilir.</summary>
    public int CharWidth { get; set; }

    /// <summary>ESC/POS kod sayfası (Türkçe için cp857).</summary>
    public string Codepage { get; set; } = "cp857";

    /// <summary>Bu kullanım yeri için yazdırma etkin mi.</summary>
    public bool IsEnabled { get; set; } = true;

    /// <summary>Elle girilmemişse kağıt genişliğinden türetilen satır genişliği.</summary>
    public int EffectiveCharWidth => CharWidth >= 24 ? CharWidth : (PaperWidth == 58 ? 32 : 48);

    public static string LabelFor(string type) => type switch
    {
        "kitchen" => "Mutfak",
        "bar" => "Bar",
        _ => "Kasa",
    };
}

using AltF4DeviceService.Domain.DTOs;

namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// ÖKC protokol kodlayıcısı: işlem talebini terminalin anlayacağı baytlara
/// çevirir, terminalin yanıtını sonuç nesnesine dönüştürür.
///
/// Taşıma katmanından (TCP/seri) ayrıdır: protokol değişirse bağlantı kodu,
/// bağlantı değişirse protokol kodu etkilenmez.
/// </summary>
public interface IPosMessageCodec
{
    /// <summary>Protokol adı (log ve ayar ekranı için).</summary>
    string ProtocolName { get; }

    /// <summary>Satış işlemini terminale gönderilecek bayt çerçevesine kodlar.</summary>
    byte[] EncodeSale(PosTransactionDto transaction);

    /// <summary>
    /// Terminalden gelen ham baytları çözer.
    /// Çerçeve henüz tamamlanmadıysa null döner ve çağıran okumaya devam eder.
    /// </summary>
    PosResultDto? DecodeResponse(byte[] response);
}

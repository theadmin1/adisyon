using System.Text;
using System.Text.Json;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// INPOS / GMP-3 protokol kodlayıcısı.
///
/// ⚠️ EKSİK: Mesaj GÖVDESİNİN alan yapısı (komut kodları, alan adları, zorunlu
/// alanlar) INPOS'un resmi GMP-3 entegrasyon dokümanından doldurulmalıdır.
/// Bu dosyada tahmin edilmiş bir alan şeması KULLANILMAMIŞTIR; yanlış bir şema
/// terminalin işlemi reddetmesine ya da daha kötüsü yanlış tutar tahsil
/// etmesine yol açabilirdi.
///
/// Hazır olan kısımlar (dokümandan bağımsız, standart):
///  - STX/ETX çerçeveleme ve LRC (XOR) sağlama toplamı
///  - Tutarın kuruş cinsinden tam sayı taşınması
///  - Yanıt çerçevesinin ayrıştırılması ve sonuç nesnesine dönüştürülmesi
///
/// Doldurulması gereken: <see cref="BuildRequestBody"/> ve <see cref="MapResponseFields"/>.
/// </summary>
public class Gmp3MessageCodec : IPosMessageCodec
{
    private readonly ILogger<Gmp3MessageCodec> _logger;

    public Gmp3MessageCodec(ILogger<Gmp3MessageCodec> logger)
    {
        _logger = logger;
    }

    public string ProtocolName => "INPOS GMP-3";

    private const byte STX = 0x02;
    private const byte ETX = 0x03;

    public byte[] EncodeSale(PosTransactionDto transaction)
    {
        var body = BuildRequestBody(transaction);
        var payload = Encoding.UTF8.GetBytes(body);

        // Çerçeve: STX + gövde + ETX + LRC
        var frame = new List<byte>(payload.Length + 3) { STX };
        frame.AddRange(payload);
        frame.Add(ETX);
        frame.Add(CalculateLrc(payload, ETX));

        _logger.LogDebug("GMP-3 satış çerçevesi hazırlandı ({Bytes} bayt, tutar {Amount} kuruş).",
            frame.Count, transaction.AmountMinor);

        return frame.ToArray();
    }

    public PosResultDto? DecodeResponse(byte[] response)
    {
        if (response.Length == 0)
        {
            return null;
        }

        // Çerçeve tamamlanmadıysa çağıran okumaya devam etmeli.
        int etxIndex = Array.IndexOf(response, ETX);

        if (etxIndex < 0)
        {
            return null;
        }

        int start = response[0] == STX ? 1 : 0;
        var body = Encoding.UTF8.GetString(response, start, etxIndex - start);

        // LRC doğrulaması: bozuk çerçeveyi onay saymak tehlikelidir.
        if (response.Length > etxIndex + 1)
        {
            var payload = response.Skip(start).Take(etxIndex - start).ToArray();
            byte expected = CalculateLrc(payload, ETX);
            byte actual = response[etxIndex + 1];

            if (expected != actual)
            {
                _logger.LogError("GMP-3 yanıtında sağlama toplamı hatası (beklenen {Expected:X2}, gelen {Actual:X2}).",
                    expected, actual);

                return PosResultDto.Failure(
                    "Terminal yanıtı bozuk geldi (sağlama toplamı uyuşmuyor). İşlemin durumunu terminal ekranından doğrulayın.",
                    "LRC_MISMATCH");
            }
        }

        return MapResponseFields(body);
    }

    /// <summary>
    /// ⚠️ DOKÜMAN GEREKLİ — INPOS GMP-3 satış isteği gövdesi.
    ///
    /// Şu an geçici olarak JSON üretiliyor. Gerçek alan adları, komut kodu ve
    /// zorunlu alanlar INPOS entegrasyon dokümanına göre değiştirilmelidir.
    /// </summary>
    private string BuildRequestBody(PosTransactionDto transaction)
    {
        var request = new Dictionary<string, object?>
        {
            ["command"] = "SALE",
            ["amount"] = transaction.AmountMinor,   // kuruş
            ["currency"] = transaction.Currency,
            ["installment"] = transaction.Installment,
            ["reference"] = transaction.Id,
            ["receipt"] = new Dictionary<string, object?>
            {
                ["check_number"] = transaction.Payload?.CheckNumber,
                ["merchant_name"] = transaction.Payload?.MerchantName,
                ["is_partial"] = transaction.Payload?.IsPartial ?? false,
                ["items"] = transaction.Payload?.Items.Select(i => new Dictionary<string, object?>
                {
                    ["name"] = i.Name,
                    ["quantity"] = i.Quantity,
                    ["unit_price"] = i.UnitPriceMinor,
                    ["total"] = i.TotalMinor,
                    ["vat_rate"] = i.VatRate,
                }).ToList(),
                ["vat_breakdown"] = transaction.Payload?.VatBreakdown.Select(v => new Dictionary<string, object?>
                {
                    ["rate"] = v.Rate,
                    ["gross"] = v.GrossMinor,
                    ["vat"] = v.VatMinor,
                }).ToList(),
            },
        };

        return JsonSerializer.Serialize(request);
    }

    /// <summary>
    /// ⚠️ DOKÜMAN GEREKLİ — INPOS GMP-3 yanıt alanlarının eşlenmesi.
    ///
    /// Aşağıdaki alan adları geçicidir. Gerçek adlar dokümandan alınmalıdır.
    /// Alan bulunamazsa işlem ONAYLANMIŞ SAYILMAZ (güvenli varsayılan).
    /// </summary>
    private PosResultDto MapResponseFields(string body)
    {
        try
        {
            using var doc = JsonDocument.Parse(body);
            var root = doc.RootElement;

            string? Str(string name) =>
                root.TryGetProperty(name, out var el) && el.ValueKind == JsonValueKind.String
                    ? el.GetString()
                    : null;

            var responseCode = Str("response_code") ?? Str("code");
            var approved = string.Equals(responseCode, "00", StringComparison.Ordinal);

            return new PosResultDto
            {
                // Güvenli varsayılan: yalnızca açık onay kodu geldiyse "approved".
                Status = approved ? "approved" : "declined",
                ApprovalCode = Str("approval_code") ?? Str("auth_code"),
                ReferenceNumber = Str("rrn") ?? Str("reference_number"),
                MaskedPan = Str("masked_pan") ?? Str("card_no"),
                CardScheme = Str("card_scheme") ?? Str("card_brand"),
                CardHolder = Str("card_holder"),
                BankName = Str("bank_name"),
                TerminalId = Str("terminal_id"),
                MerchantId = Str("merchant_id"),
                FiscalReceiptNo = Str("fiscal_receipt_no") ?? Str("z_no"),
                ApprovedAmountMinor = root.TryGetProperty("approved_amount", out var amt) && amt.TryGetInt64(out var v)
                    ? v
                    : null,
                ErrorCode = approved ? null : responseCode,
                ErrorMessage = approved ? null : (Str("message") ?? "Banka işlemi onaylamadı."),
                RawResponse = new Dictionary<string, object> { ["body"] = body },
            };
        }
        catch (JsonException ex)
        {
            _logger.LogError(ex, "GMP-3 yanıtı çözümlenemedi. Ham yanıt: {Body}", body);

            // Çözümlenemeyen yanıt ASLA onay sayılmaz.
            return PosResultDto.Failure(
                "Terminal yanıtı çözümlenemedi. İşlemin geçip geçmediğini terminal ekranından doğrulayın.",
                "DECODE_ERROR");
        }
    }

    /// <summary>LRC (Longitudinal Redundancy Check): gövde + ETX baytlarının XOR'u.</summary>
    private static byte CalculateLrc(byte[] payload, byte terminator)
    {
        byte lrc = 0;

        foreach (var b in payload)
        {
            lrc ^= b;
        }

        return (byte) (lrc ^ terminator);
    }
}

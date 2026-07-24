namespace AltF4DeviceService.Domain.DTOs;

/// <summary>
/// Laravel'den gelen ÖKC kart işlemi talebi.
/// </summary>
public class PosTransactionDto
{
    public long Id { get; set; }

    /// <summary>sale | refund | void</summary>
    public string Type { get; set; } = "sale";

    /// <summary>
    /// Kuruş cinsinden tutar. ÖKC protokolleri tam sayı bekler; ondalık
    /// yuvarlama farkı tahsilat tutarını kaydırmasın diye int taşınır.
    /// </summary>
    public long AmountMinor { get; set; }

    public string Currency { get; set; } = "TRY";

    /// <summary>0 = peşin, 2+ = taksit sayısı.</summary>
    public int Installment { get; set; }

    public PosPayloadDto? Payload { get; set; }

    public string CreatedAt { get; set; } = string.Empty;

    public decimal Amount => AmountMinor / 100m;
}

/// <summary>Mali fiş için gereken satış kalemleri ve KDV kırılımı.</summary>
public class PosPayloadDto
{
    public string CheckNumber { get; set; } = string.Empty;
    public string Table { get; set; } = string.Empty;
    public string MerchantName { get; set; } = string.Empty;
    public bool IsPartial { get; set; }
    public List<PosLineItemDto> Items { get; set; } = new();
    public List<PosVatGroupDto> VatBreakdown { get; set; } = new();
}

public class PosLineItemDto
{
    public string Name { get; set; } = string.Empty;
    public decimal Quantity { get; set; }
    public long UnitPriceMinor { get; set; }
    public long TotalMinor { get; set; }
    public decimal VatRate { get; set; }
    public bool IsComplimentary { get; set; }
}

public class PosVatGroupDto
{
    public decimal Rate { get; set; }
    public long GrossMinor { get; set; }
    public long VatMinor { get; set; }
}

/// <summary>Terminalden dönen işlem sonucu.</summary>
public class PosResultDto
{
    /// <summary>approved | declined | failed | cancelled</summary>
    public string Status { get; set; } = "failed";

    public string? ApprovalCode { get; set; }
    public string? ReferenceNumber { get; set; }

    /// <summary>Maskeli kart numarası. Tam PAN ASLA taşınmaz/saklanmaz.</summary>
    public string? MaskedPan { get; set; }

    public string? CardScheme { get; set; }
    public string? CardHolder { get; set; }
    public string? BankName { get; set; }
    public string? TerminalId { get; set; }
    public string? MerchantId { get; set; }

    /// <summary>ÖKC mali fiş numarası (yasal belge referansı).</summary>
    public string? FiscalReceiptNo { get; set; }

    /// <summary>Terminal farklı tutar onayladıysa (kısmi onay) gerçek tutar.</summary>
    public long? ApprovedAmountMinor { get; set; }

    public string? ErrorCode { get; set; }
    public string? ErrorMessage { get; set; }

    /// <summary>Sorun giderme için terminalin ham yanıtı.</summary>
    public Dictionary<string, object>? RawResponse { get; set; }

    public bool IsApproved => string.Equals(Status, "approved", StringComparison.OrdinalIgnoreCase);

    public static PosResultDto Failure(string message, string? code = null) => new()
    {
        Status = "failed",
        ErrorCode = code,
        ErrorMessage = message,
    };
}

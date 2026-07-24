using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;
using Microsoft.Extensions.Logging;

namespace AltF4DeviceService.Infrastructure.Services.Pos;

/// <summary>
/// Fiziki terminal olmadan tüm ödeme akışını sınamak için sahte ÖKC.
///
/// Amacı: INPOS GMP-3 protokol dokümanı gelmeden önce Laravel kuyruğu,
/// durum yaşam döngüsü, ödeme kaydı ve kasiyer ekranının uçtan uca
/// çalıştığını doğrulamak.
///
/// Yalnızca bağlantı tipi "simulator" seçildiğinde devreye girer; gerçek
/// kurulumda asla kullanılmaz.
/// </summary>
public class SimulatedPosTerminalService : IPosTerminalService
{
    private readonly ILogger<SimulatedPosTerminalService> _logger;

    public SimulatedPosTerminalService(ILogger<SimulatedPosTerminalService> logger)
    {
        _logger = logger;
    }

    public async Task<PosResultDto> ProcessSaleAsync(
        PosTransactionDto transaction,
        Func<string, Task>? onStatusChanged = null,
        CancellationToken cancellationToken = default)
    {
        _logger.LogWarning(
            "⚠️ SİMÜLATÖR MODU: gerçek tahsilat YAPILMIYOR [#{TxId}] {Amount:0.00} TL",
            transaction.Id, transaction.Amount);

        if (onStatusChanged != null)
        {
            await onStatusChanged("sent");
        }

        await Task.Delay(1200, cancellationToken);

        if (onStatusChanged != null)
        {
            await onStatusChanged("awaiting_card");
        }

        // Kart okutma + PIN + banka onayı süresini taklit et
        await Task.Delay(3000, cancellationToken);

        // Tutarın son hanesi 9 ise reddet: hata akışı da test edilebilsin.
        if (transaction.AmountMinor % 10 == 9)
        {
            _logger.LogInformation("Simülatör: işlem reddedildi (test senaryosu) [#{TxId}]", transaction.Id);

            return new PosResultDto
            {
                Status = "declined",
                ErrorCode = "51",
                ErrorMessage = "Yetersiz bakiye (SİMÜLASYON)",
                RawResponse = new Dictionary<string, object> { ["simulated"] = true },
            };
        }

        return new PosResultDto
        {
            Status = "approved",
            ApprovalCode = Random.Shared.Next(100000, 999999).ToString(),
            ReferenceNumber = DateTime.Now.ToString("yyMMddHHmmss"),
            MaskedPan = "455359******4915",
            CardScheme = "VISA",
            BankName = "SİMÜLASYON BANK",
            TerminalId = "SIM00001",
            MerchantId = "SIMMERCHANT",
            FiscalReceiptNo = "SIM-" + Random.Shared.Next(1000, 9999),
            ApprovedAmountMinor = transaction.AmountMinor,
            RawResponse = new Dictionary<string, object> { ["simulated"] = true },
        };
    }

    public Task<(bool Success, string Message)> TestConnectionAsync(CancellationToken cancellationToken = default)
    {
        return Task.FromResult((true, "Simülatör aktif — gerçek terminal kullanılmıyor, tahsilat yapılmaz."));
    }
}

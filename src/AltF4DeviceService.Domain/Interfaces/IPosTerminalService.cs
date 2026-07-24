using AltF4DeviceService.Domain.DTOs;

namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// Fiziki Yeni Nesil ÖKC (yazarkasa POS) terminaliyle konuşan servis.
/// </summary>
public interface IPosTerminalService
{
    /// <summary>
    /// Kart satış işlemini terminale gönderir ve sonucu bekler.
    /// </summary>
    /// <param name="transaction">İşlem talebi (tutar, taksit, mali fiş kalemleri).</param>
    /// <param name="onStatusChanged">
    /// Ara durum bildirimi (sent, awaiting_card). Kasiyer ekranında
    /// "kart bekleniyor" gösterebilmek için kullanılır.
    /// </param>
    Task<PosResultDto> ProcessSaleAsync(
        PosTransactionDto transaction,
        Func<string, Task>? onStatusChanged = null,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Terminale bağlanılabildiğini sınar (ayarlar ekranındaki test butonu).
    /// </summary>
    Task<(bool Success, string Message)> TestConnectionAsync(CancellationToken cancellationToken = default);
}

using AltF4DeviceService.Domain.Interfaces;

namespace AltF4DeviceService.Application.Interfaces;

/// <summary>
/// Yapılandırmadaki bağlantı tipine göre hangi ÖKC servisinin kullanılacağına
/// karar verir (gerçek terminal veya simülatör).
///
/// Ayrı bir arayüz olmasının sebebi: simülatörün yanlışlıkla gerçek kurulumda
/// devreye girmesi kabul edilemez, bu seçim tek ve denetlenebilir bir yerde durmalı.
/// </summary>
public interface IPosTerminalResolver
{
    /// <summary>Aktif yapılandırmaya uygun terminal servisini döner.</summary>
    Task<IPosTerminalService> ResolveAsync(CancellationToken cancellationToken = default);

    /// <summary>Şu an simülatör modunda mı çalışılıyor.</summary>
    Task<bool> IsSimulatorAsync(CancellationToken cancellationToken = default);
}

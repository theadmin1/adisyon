namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// Laravel Web Adisyon API'si ile haberleşmek için kullanılan HTTP Client arayüzü.
/// İleriki sprintlerde tüm uzak API istekleri bu interface üzerinden gerçekleştirilecektir.
/// </summary>
public interface ILaravelApiClient
{
    /// <summary>
    /// Cihazın geçerli lisans durumunu uzak Laravel API sunucusundan sorgular.
    /// </summary>
    /// <param name="licenseKey">Lisans anahtarı.</param>
    /// <param name="deviceToken">Cihaza atanmış token.</param>
    /// <param name="cancellationToken">İptal tokenı.</param>
    /// <returns>Lisans doğrulama sonucu (başarılı/başarısız ve detayları).</returns>
    Task<bool> ValidateLicenseAsync(string licenseKey, string deviceToken, CancellationToken cancellationToken = default);

    /// <summary>
    /// Şube bilgilerini Laravel API'den günceller.
    /// </summary>
    /// <param name="branchId">Şube ID.</param>
    /// <param name="cancellationToken">İptal tokenı.</param>
    Task<bool> SyncBranchAccountAsync(int branchId, CancellationToken cancellationToken = default);

    /// <summary>
    /// Cihaz canlılık (Heartbeat) sinyalini Laravel sunucusuna iletir.
    /// </summary>
    /// <param name="deviceUuid">Benzersiz Cihaz UUID.</param>
    /// <param name="cancellationToken">İptal tokenı.</param>
    Task<bool> SendHeartbeatAsync(string deviceUuid, CancellationToken cancellationToken = default);
}

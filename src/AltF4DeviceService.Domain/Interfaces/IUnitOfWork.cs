using AltF4DeviceService.Domain.Entities;

namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// Veritabanı işlemlerinin toplu yürütülmesi ve Unit of Work deseni arayüzü.
/// </summary>
public interface IUnitOfWork : IDisposable
{
    IRepository<Device> Devices { get; }
    IRepository<License> Licenses { get; }
    IRepository<BranchAccount> BranchAccounts { get; }
    IRepository<Setting> Settings { get; }
    IRepository<LogEntry> Logs { get; }

    /// <summary>
    /// Değişiklikleri veritabanına kaydeder.
    /// </summary>
    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}

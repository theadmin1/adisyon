using AltF4DeviceService.Domain.Entities;
using AltF4DeviceService.Domain.Interfaces;
using AltF4DeviceService.Infrastructure.Persistence;

namespace AltF4DeviceService.Infrastructure.Persistence.Repositories;

/// <summary>
/// UnitOfWork pattern implementasyonu.
/// </summary>
public class UnitOfWork : IUnitOfWork
{
    private readonly DeviceDbContext _context;

    public IRepository<Device> Devices { get; }
    public IRepository<License> Licenses { get; }
    public IRepository<BranchAccount> BranchAccounts { get; }
    public IRepository<Setting> Settings { get; }
    public IRepository<LogEntry> Logs { get; }

    public UnitOfWork(DeviceDbContext context)
    {
        _context = context;
        Devices = new Repository<Device>(_context);
        Licenses = new Repository<License>(_context);
        BranchAccounts = new Repository<BranchAccount>(_context);
        Settings = new Repository<Setting>(_context);
        Logs = new Repository<LogEntry>(_context);
    }

    public async Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
    {
        return await _context.SaveChangesAsync(cancellationToken);
    }

    public void Dispose()
    {
        _context.Dispose();
    }
}

using AltF4DeviceService.Domain.Entities;
using Microsoft.EntityFrameworkCore;

namespace AltF4DeviceService.Infrastructure.Persistence;

/// <summary>
/// SQLite veritabanı erişimi için Entity Framework Core DbContext sınıfı.
/// </summary>
public class DeviceDbContext : DbContext
{
    public DeviceDbContext(DbContextOptions<DeviceDbContext> options) : base(options)
    {
    }

    public DbSet<Device> Devices => Set<Device>();
    public DbSet<License> Licenses => Set<License>();
    public DbSet<BranchAccount> BranchAccounts => Set<BranchAccount>();
    public DbSet<Setting> Settings => Set<Setting>();
    public DbSet<LogEntry> Logs => Set<LogEntry>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        // Bulunan konfigürasyonları otomatik uygula
        modelBuilder.ApplyConfigurationsFromAssembly(typeof(DeviceDbContext).Assembly);
    }
}

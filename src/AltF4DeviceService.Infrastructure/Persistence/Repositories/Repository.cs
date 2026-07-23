using System.Linq.Expressions;
using AltF4DeviceService.Domain.Interfaces;
using AltF4DeviceService.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;

namespace AltF4DeviceService.Infrastructure.Persistence.Repositories;

/// <summary>
/// Jenerik repository implementasyonu. EF Core tracking çakışmalarını (IdentityConflict) güvenli çözer.
/// </summary>
public class Repository<T> : IRepository<T> where T : class
{
    protected readonly DeviceDbContext _context;
    protected readonly DbSet<T> _dbSet;

    public Repository(DeviceDbContext context)
    {
        _context = context;
        _dbSet = context.Set<T>();
    }

    public async Task<IReadOnlyList<T>> GetAllAsync(CancellationToken cancellationToken = default)
    {
        return await _dbSet.AsNoTracking().ToListAsync(cancellationToken);
    }

    public async Task<IReadOnlyList<T>> FindAsync(Expression<Func<T, bool>> predicate, CancellationToken cancellationToken = default)
    {
        return await _dbSet.AsNoTracking().Where(predicate).ToListAsync(cancellationToken);
    }

    public async Task<T?> GetByIdAsync(int id, CancellationToken cancellationToken = default)
    {
        return await _dbSet.FindAsync(new object[] { id }, cancellationToken);
    }

    public async Task<T?> FirstOrDefaultAsync(Expression<Func<T, bool>> predicate, CancellationToken cancellationToken = default)
    {
        return await _dbSet.FirstOrDefaultAsync(predicate, cancellationToken);
    }

    public async Task AddAsync(T entity, CancellationToken cancellationToken = default)
    {
        await _dbSet.AddAsync(entity, cancellationToken);
    }

    public void Update(T entity)
    {
        var entry = _context.Entry(entity);
        if (entry.State == EntityState.Detached)
        {
            var entityType = _context.Model.FindEntityType(typeof(T));
            var primaryKey = entityType?.FindPrimaryKey();

            if (primaryKey != null)
            {
                var keyValues = primaryKey.Properties
                    .Select(p => p.PropertyInfo?.GetValue(entity))
                    .ToArray();

                var trackedEntity = _dbSet.Local.FirstOrDefault(e =>
                {
                    var eKeyValues = primaryKey.Properties.Select(p => p.PropertyInfo?.GetValue(e)).ToArray();
                    return keyValues.SequenceEqual(eKeyValues);
                });

                if (trackedEntity != null)
                {
                    _context.Entry(trackedEntity).CurrentValues.SetValues(entity);
                    return;
                }
            }

            _dbSet.Update(entity);
        }
    }

    public void Remove(T entity)
    {
        _dbSet.Remove(entity);
    }
}

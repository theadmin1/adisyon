using System.Linq.Expressions;

namespace AltF4DeviceService.Domain.Interfaces;

/// <summary>
/// Domain varlıkları için genel veri erişim arayüzü (Generic Repository Pattern).
/// </summary>
/// <typeparam name="T">Entity tipi.</typeparam>
public interface IRepository<T> where T : class
{
    /// <summary>
    /// Tüm kayıtları asenkron getirir.
    /// </summary>
    Task<IReadOnlyList<T>> GetAllAsync(CancellationToken cancellationToken = default);

    /// <summary>
    /// Belirtilen filtreye uyan kayıtları getirir.
    /// </summary>
    Task<IReadOnlyList<T>> FindAsync(Expression<Func<T, bool>> predicate, CancellationToken cancellationToken = default);

    /// <summary>
    /// ID değerine göre tek kaydı getirir.
    /// </summary>
    Task<T?> GetByIdAsync(int id, CancellationToken cancellationToken = default);

    /// <summary>
    /// Belirtilen koşula uyan ilk kaydı veya varsayılanı getirir.
    /// </summary>
    Task<T?> FirstOrDefaultAsync(Expression<Func<T, bool>> predicate, CancellationToken cancellationToken = default);

    /// <summary>
    /// Yeni kayıt ekler.
    /// </summary>
    Task AddAsync(T entity, CancellationToken cancellationToken = default);

    /// <summary>
    /// Mevcut kaydı günceller.
    /// </summary>
    void Update(T entity);

    /// <summary>
    /// Kaydı siler.
    /// </summary>
    void Remove(T entity);
}

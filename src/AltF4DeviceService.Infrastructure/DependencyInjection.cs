using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Services;
using AltF4DeviceService.Domain.Interfaces;
using AltF4DeviceService.Infrastructure.Persistence;
using AltF4DeviceService.Infrastructure.Persistence.Repositories;
using AltF4DeviceService.Infrastructure.Services;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;

namespace AltF4DeviceService.Infrastructure;

/// <summary>
/// Infrastructure katmanı bağımlılık enjeksiyon (DI) kayıt uzantıları.
/// </summary>
public static class DependencyInjection
{
    public static IServiceCollection AddInfrastructureServices(
        this IServiceCollection services, 
        IConfiguration configuration)
    {
        var connectionString = configuration.GetConnectionString("DefaultConnection") 
            ?? "Data Source=altf4_device.db";

        // SQLite & EF Core
        services.AddDbContext<DeviceDbContext>(options =>
        {
            options.UseSqlite(connectionString);
        });

        // Repositories & Unit of Work
        services.AddScoped<IUnitOfWork, UnitOfWork>();
        services.AddScoped(typeof(IRepository<>), typeof(Repository<>));

        // Application Services
        services.AddScoped<IDeviceService, DeviceService>();
        services.AddScoped<ILicenseService, LicenseService>();
        services.AddScoped<IBranchService, BranchService>();
        services.AddScoped<IHealthService, HealthService>();
        services.AddScoped<ISettingService, SettingService>();

        // Laravel API Client (HttpClient factory ile)
        services.AddHttpClient<ILaravelApiClient, LaravelApiClient>();

        // Thermal POS Printer Service
        services.AddSingleton<IPrinterService, ThermalPrinterService>();

        return services;
    }
}

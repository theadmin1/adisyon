using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using AltF4DeviceService.Application.Workers;
using AltF4DeviceService.Infrastructure;
using AltF4DeviceService.Infrastructure.Persistence;
using AltF4DeviceService.WebApi.Endpoints;
using AltF4DeviceService.WebApi.Middleware;
using AltF4DeviceService.WebApi.Tray;
using Microsoft.EntityFrameworkCore;
using Serilog;

// --- SINGLE INSTANCE & ARKA PLAN SERVİS KONTROLÜ (ÇAKIŞMA ÖNLEME) ---
const string mutexName = "Global\\AltF4DeviceService_SingleInstance_Mutex";
using var mutex = new Mutex(true, mutexName, out bool isNewInstance);

if (!isNewInstance)
{
    // Servis zaten arka planda çalışıyor!
    // Mevcut çalışan servise "Tarayıcıyı Aç/Ön Plana Getir" sinyali gönderelim.
    try
    {
        using var httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(2) };
        _ = httpClient.GetAsync("http://127.0.0.1:18500/open-browser").GetAwaiter().GetResult();
    }
    catch
    {
        // Sessizce geç (zaten servis açık)
    }

    // İkinci uygulamanın port çakışması yaşamadan temiz sonlanması
    return;
}

var builder = WebApplication.CreateBuilder(args);

// 1. Serilog Yapılandırması (Console + File)
builder.Host.UseSerilog((context, services, configuration) =>
{
    configuration
        .ReadFrom.Configuration(context.Configuration)
        .ReadFrom.Services(services)
        .Enrich.FromLogContext();
});

// 2. Windows Service Desteği
builder.Host.UseWindowsService();

// 3. Konfigürasyon Ayarları (ServiceOptions)
builder.Services.Configure<ServiceOptions>(
    builder.Configuration.GetSection(ServiceOptions.SectionName));

// 4. Infrastructure & Application Bağımlılık Kayıtları (Clean Architecture DI)
builder.Services.AddInfrastructureServices(builder.Configuration);

// 5. Arka Plan İşçisi & System Tray Servis Kayıtları
builder.Services.AddHostedService<DeviceBackgroundWorker>();

builder.Services.AddSingleton<SystemTrayService>();
builder.Services.AddSingleton<IBrowserLauncherService>(sp => sp.GetRequiredService<SystemTrayService>());
builder.Services.AddHostedService(sp => sp.GetRequiredService<SystemTrayService>());

// 6. Swagger API Dokümantasyonu
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new() { Title = "AltF4 Device Local API", Version = "v1" });
});

// Local API Port Ayarlaması (http://127.0.0.1:18500)
var serviceOptions = builder.Configuration.GetSection(ServiceOptions.SectionName).Get<ServiceOptions>() ?? new ServiceOptions();
builder.WebHost.UseUrls($"http://127.0.0.1:{serviceOptions.Port}");

var app = builder.Build();

// 7. Merkezi Exception Handling Middleware
app.UseMiddleware<GlobalExceptionMiddleware>();

// 8. Swagger Sadece Development Modunda Aktif
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI(c =>
    {
        c.SwaggerEndpoint("/swagger/v1/swagger.json", "AltF4 Device Local API v1");
    });
}

// 9. Minimal API Endpoint Haritalama
app.MapHealthEndpoints();
app.MapDeviceEndpoints();
app.MapLicenseEndpoints();
app.MapBranchEndpoints();

// 10. Dahili Tarayıcı Açma Endpoint'i (Masaüstü ikonu / Tekil Çalıştırma İçin)
app.MapGet("/open-browser", (IBrowserLauncherService launcher) =>
{
    launcher.OpenBrowser();
    return Results.Ok(new { success = true, message = "Dahili tarayıcı penceresi başarıyla açıldı." });
});

// 11. Otomatik EF Core Migration ve SQLite Veritabanı Oluşturma
using (var scope = app.Services.CreateScope())
{
    var logger = scope.ServiceProvider.GetRequiredService<ILogger<Program>>();
    try
    {
        logger.LogInformation("SQLite veritabanı migration kontrolü yapılıyor...");
        var dbContext = scope.ServiceProvider.GetRequiredService<DeviceDbContext>();
        dbContext.Database.Migrate();
        logger.LogInformation("SQLite veritabanı başarıyla doğrulandı ve güncellendi.");
    }
    catch (Exception ex)
    {
        logger.LogCritical(ex, "Veritabanı migration sırasında hata oluştu!");
    }
}

Log.Information("AltF4 Device Service başlatılıyor... Adres: http://127.0.0.1:{Port}", serviceOptions.Port);

app.Run();

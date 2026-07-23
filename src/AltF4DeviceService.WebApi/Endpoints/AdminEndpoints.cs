using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;

namespace AltF4DeviceService.WebApi.Endpoints;

/// <summary>
/// Admin yetki ve ayar yönetimi için Minimal API endpoint tanımları.
/// </summary>
public static class AdminEndpoints
{
    public static void MapAdminEndpoints(this IEndpointRouteBuilder app)
    {
        var group = app.MapGroup("/admin")
            .WithTags("Admin Management API");

        // Tüm ayarları getir
        group.MapGet("/settings", async (ISettingService settingService, CancellationToken ct) =>
        {
            var settings = await settingService.GetAllSettingsAsync(ct);
            return Results.Ok(ApiResponse<object>.Ok(settings, "Tüm sistem ayarları getirildi."));
        });

        // Lisans anahtarı güncelle
        group.MapPost("/license", async (UpdateLicenseRequest request, ILicenseService licenseService, CancellationToken ct) =>
        {
            if (string.IsNullOrWhiteSpace(request.LicenseKey))
                return Results.BadRequest(ApiResponse<object>.Fail("Lisans anahtarı boş olamaz."));

            var license = await licenseService.UpdateLicenseKeyAsync(request.LicenseKey, ct);
            return Results.Ok(ApiResponse<LicenseDto>.Ok(license, "Lisans anahtarı güncellendi."));
        });

        // Tarayıcı güvenlik kısıtlamalarını güncelle
        group.MapPost("/browser-restrictions", async (BrowserRestrictionOptions restrictions, ISettingService settingService, CancellationToken ct) =>
        {
            await settingService.SaveBrowserRestrictionsAsync(restrictions, ct);
            return Results.Ok(ApiResponse<BrowserRestrictionOptions>.Ok(restrictions, "Tarayıcı kısıtlama kuralları güncellendi."));
        });
    }

    public record UpdateLicenseRequest(string LicenseKey);
}

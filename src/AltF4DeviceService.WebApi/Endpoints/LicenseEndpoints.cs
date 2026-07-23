using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace AltF4DeviceService.WebApi.Endpoints;

/// <summary>
/// License Minimal API endpoint tanımları.
/// </summary>
public static class LicenseEndpoints
{
    public static IEndpointRouteBuilder MapLicenseEndpoints(this IEndpointRouteBuilder endpoints)
    {
        endpoints.MapGet("/license", async (ILicenseService licenseService, CancellationToken ct) =>
        {
            var license = await licenseService.GetOrCreateLicenseAsync(ct);
            return Results.Ok(ApiResponse<LicenseDto>.Ok(license, "Lisans bilgileri getirildi."));
        })
        .WithName("GetLicense")
        .WithTags("License");

        return endpoints;
    }
}

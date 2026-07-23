using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace AltF4DeviceService.WebApi.Endpoints;

/// <summary>
/// Health check Minimal API endpoint tanımları.
/// </summary>
public static class HealthEndpoints
{
    public static IEndpointRouteBuilder MapHealthEndpoints(this IEndpointRouteBuilder endpoints)
    {
        endpoints.MapGet("/health", async (IHealthService healthService, CancellationToken ct) =>
        {
            var status = await healthService.GetHealthStatusAsync(ct);
            return Results.Ok(ApiResponse<HealthStatusDto>.Ok(status, "Servis sağlık durumu getirildi."));
        })
        .WithName("GetHealth")
        .WithTags("Health");

        return endpoints;
    }
}

using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace AltF4DeviceService.WebApi.Endpoints;

/// <summary>
/// Device kimlik Minimal API endpoint tanımları.
/// </summary>
public static class DeviceEndpoints
{
    public static IEndpointRouteBuilder MapDeviceEndpoints(this IEndpointRouteBuilder endpoints)
    {
        endpoints.MapGet("/device", async (IDeviceService deviceService, CancellationToken ct) =>
        {
            var device = await deviceService.GetOrCreateDeviceIdentityAsync(ct);
            return Results.Ok(ApiResponse<DeviceDto>.Ok(device, "Cihaz kimlik bilgileri getirildi."));
        })
        .WithName("GetDevice")
        .WithTags("Device");

        return endpoints;
    }
}

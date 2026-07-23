using AltF4DeviceService.Application.DTOs;
using AltF4DeviceService.Application.Interfaces;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace AltF4DeviceService.WebApi.Endpoints;

/// <summary>
/// Branch Minimal API endpoint tanımları.
/// </summary>
public static class BranchEndpoints
{
    public static IEndpointRouteBuilder MapBranchEndpoints(this IEndpointRouteBuilder endpoints)
    {
        endpoints.MapGet("/branch", async (IBranchService branchService, CancellationToken ct) =>
        {
            var branch = await branchService.GetOrCreateBranchAccountAsync(ct);
            return Results.Ok(ApiResponse<BranchAccountDto>.Ok(branch, "Şube hesabı bilgileri getirildi."));
        })
        .WithName("GetBranch")
        .WithTags("Branch");

        return endpoints;
    }
}

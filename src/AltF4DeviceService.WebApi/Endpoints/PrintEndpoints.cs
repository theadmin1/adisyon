using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;

namespace AltF4DeviceService.WebApi.Endpoints;

public static class PrintEndpoints
{
    public static void MapPrintEndpoints(this IEndpointRouteBuilder app)
    {
        var group = app.MapGroup("/api/v1/print").WithTags("Print Spooler");

        // Laravel veya Tarayıcıdan Doğrudan Yazdırma İsteği Alım Endpoint'i (Direct HTTP Push)
        group.MapPost("/job", async (PrintJobDto jobDto, IPrinterService printerService, ILaravelApiClient apiClient, ILogger<Program> logger) =>
        {
            logger.LogInformation("🚀 Laravel/Web POS'tan Doğrudan Yazdırma Push İsteği Alındı [# {JobId}]: {Title}", jobDto.Id, jobDto.Title);

            // 1. Status: received
            if (jobDto.Id > 0)
            {
                _ = apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "received");
            }

            string rawText = jobDto.Payload?.RawText ?? string.Empty;
            if (string.IsNullOrWhiteSpace(rawText))
            {
                if (jobDto.Id > 0)
                {
                    _ = apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "failed", "Fiş metni boş");
                }
                return Results.BadRequest(new { success = false, message = "Fiş metni boş" });
            }

            string printerName = string.IsNullOrWhiteSpace(jobDto.TargetPrinter) || jobDto.TargetPrinter == "Default POS Printer"
                ? "Generic / Text Only"
                : jobDto.TargetPrinter;

            // 2. Status: printing
            if (jobDto.Id > 0)
            {
                _ = apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "printing");
            }

            bool success = printerService.SendStringToPrinter(printerName, rawText, out string errorMessage);

            if (success)
            {
                logger.LogInformation("✅ Anlık Doğrudan Yazdırma Başarılı [# {JobId}]", jobDto.Id);
                if (jobDto.Id > 0)
                {
                    _ = apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "completed");
                }

                return Results.Ok(new { success = true, message = "Fiş başarıyla yazdırıldı" });
            }
            else
            {
                logger.LogError("❌ Anlık Doğrudan Yazdırma Başarısız [# {JobId}]: {Error}", jobDto.Id, errorMessage);
                if (jobDto.Id > 0)
                {
                    _ = apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "failed", errorMessage);
                }

                return Results.Problem(detail: errorMessage, statusCode: 500);
            }
        });
    }
}

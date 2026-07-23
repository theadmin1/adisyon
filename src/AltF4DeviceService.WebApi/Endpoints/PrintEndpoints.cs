using AltF4DeviceService.Domain.DTOs;
using AltF4DeviceService.Domain.Interfaces;

namespace AltF4DeviceService.WebApi.Endpoints;

public static class PrintEndpoints
{
    public static void MapPrintEndpoints(this IEndpointRouteBuilder app)
    {
        var group = app.MapGroup("/api/v1/print").WithTags("Print Spooler");

        // Yerel ağdaki Web POS'tan doğrudan yazdırma isteği (Direct HTTP Push).
        // Bu uç yalnızca 127.0.0.1 üzerinden dinlenir (Program.cs > UseUrls).
        //
        // ÖNEMLİ: Arka plandaki polling döngüsü de aynı kuyruğu izliyor. Bu yüzden
        // baskıdan ÖNCE iş sunucuda atomik olarak kilitlenir; kilit alınamazsa
        // (409) işi zaten poller almıştır ve burada basılmaz. Aksi halde aynı fiş
        // iki kez çıkardı.
        group.MapPost("/job", async (
            PrintJobDto jobDto,
            IPrinterService printerService,
            ILaravelApiClient apiClient,
            ILogger<Program> logger,
            CancellationToken cancellationToken) =>
        {
            logger.LogInformation("Web POS'tan doğrudan yazdırma isteği alındı [#{JobId}]: {Title}", jobDto.Id, jobDto.Title);

            var rawText = jobDto.Payload?.RawText ?? string.Empty;

            if (string.IsNullOrWhiteSpace(rawText))
            {
                if (jobDto.Id > 0)
                {
                    await apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "failed", "Fiş metni boş", cancellationToken);
                }

                return Results.BadRequest(new { success = false, message = "Fiş metni boş" });
            }

            // Sunucudaki kuyruk kaydı varsa önce kilitle.
            if (jobDto.Id > 0 && !await apiClient.ClaimPrintJobAsync(jobDto.Id, cancellationToken))
            {
                return Results.Conflict(new
                {
                    success = false,
                    message = "Bu fiş zaten alınmış veya basılmış; mükerrer baskı engellendi.",
                });
            }

            if (jobDto.Id > 0)
            {
                await apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "printing", null, cancellationToken);
            }

            var codepage = string.IsNullOrWhiteSpace(jobDto.Codepage) ? "cp857" : jobDto.Codepage;
            bool success = printerService.SendStringToPrinter(jobDto.TargetPrinter, rawText, codepage, out string errorMessage);

            if (success)
            {
                logger.LogInformation("Anlık doğrudan yazdırma başarılı [#{JobId}]", jobDto.Id);

                if (jobDto.Id > 0)
                {
                    await apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "completed", null, cancellationToken);
                }

                return Results.Ok(new { success = true, message = "Fiş başarıyla yazdırıldı" });
            }

            logger.LogError("Anlık doğrudan yazdırma başarısız [#{JobId}]: {Error}", jobDto.Id, errorMessage);

            if (jobDto.Id > 0)
            {
                await apiClient.UpdatePrintJobStatusAsync(jobDto.Id, "failed", errorMessage, cancellationToken);
            }

            return Results.Problem(detail: errorMessage, statusCode: 500);
        });

        // Cihazda kurulu Windows yazıcılarını listeler.
        // Ayarlar ekranında yazıcı adının birebir yazılabilmesi için kullanılır.
        group.MapGet("/printers", (IPrinterService printerService) => Results.Ok(new
        {
            success = true,
            default_printer = printerService.GetDefaultPrinterName(),
            printers = printerService.GetInstalledPrinters(),
        }));
    }
}

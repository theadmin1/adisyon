namespace AltF4DeviceService.Domain.DTOs;

public class PrintJobDto
{
    public long Id { get; set; }
    public string JobType { get; set; } = string.Empty;
    public string PrinterType { get; set; } = string.Empty;
    public string Title { get; set; } = string.Empty;
    public string Status { get; set; } = "pending";
    public string TargetPrinter { get; set; } = string.Empty;
    public string ConnectionType { get; set; } = "windows_driver";
    public int PaperWidth { get; set; } = 80;
    public PrintJobPayloadDto? Payload { get; set; }
    public string CreatedAt { get; set; } = string.Empty;
}

public class PrintJobPayloadDto
{
    public string? RawText { get; set; }
}

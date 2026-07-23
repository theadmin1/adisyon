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

    /// <summary>
    /// Fiş satır genişliği (karakter). Metin yerleşimi sunucu tarafında buna göre yapılır.
    /// </summary>
    public int CharWidth { get; set; } = 48;

    /// <summary>
    /// Yazıcının ESC/POS kod sayfası. Türkçe için cp857 (ESC t 13).
    /// </summary>
    public string Codepage { get; set; } = "cp857";

    public PrintJobPayloadDto? Payload { get; set; }
    public string CreatedAt { get; set; } = string.Empty;
}

public class PrintJobPayloadDto
{
    public string? RawText { get; set; }
}

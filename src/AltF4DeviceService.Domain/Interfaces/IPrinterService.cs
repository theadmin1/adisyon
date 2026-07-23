namespace AltF4DeviceService.Domain.Interfaces;

public interface IPrinterService
{
    bool SendStringToPrinter(string printerName, string text, out string errorMessage);
    string GetDefaultPrinterName();
}

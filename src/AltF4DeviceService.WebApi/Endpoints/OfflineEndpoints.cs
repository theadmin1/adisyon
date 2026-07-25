using AltF4DeviceService.Application.Interfaces;
using AltF4DeviceService.Application.Options;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;

namespace AltF4DeviceService.WebApi.Endpoints;

public static class OfflineEndpoints
{
    public static void MapOfflineEndpoints(this WebApplication app)
    {
        app.MapGet("/offline", (
            IOptions<ServiceOptions> options,
            INetworkMonitoringService networkService,
            IDeviceService deviceService) =>
        {
            var port = options.Value.Port;
            var isOnline = networkService.IsOnline;
            var statusBadge = isOnline ? "🟢 ONLİNE MOD" : "🔴 ÇEVRİMDIŞI MOD (OFFLINE)";
            var statusColor = isOnline ? "#10b981" : "#ef4444";

            var html = $@"
<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>AltF4 Adisyon — Çevrimdışı (Offline) Kasa Modu</title>
    <style>
        * {{ box-sizing: border-box; margin: 0; padding: 0; }}
        body {{
            background-color: #0d0f17;
            color: #f3f4f6;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }}
        .container {{
            background: #151824;
            border: 1px solid #2d3248;
            border-radius: 20px;
            padding: 40px;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }}
        .badge {{
            display: inline-block;
            background: rgba(239, 68, 68, 0.15);
            color: {statusColor};
            border: 1px solid {statusColor};
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: 0.5px;
        }}
        .icon {{
            font-size: 64px;
            margin-bottom: 16px;
        }}
        h1 {{
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }}
        p {{
            color: #9ca3af;
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 28px;
        }}
        .info-box {{
            background: #1c2030;
            border: 1px solid #2d334d;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 28px;
            text-align: left;
            font-size: 13px;
        }}
        .info-row {{
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #282e44;
        }}
        .info-row:last-child {{ border-bottom: none; }}
        .info-label {{ color: #9ca3af; font-weight: 500; }}
        .info-value {{ color: #60a5fa; font-family: monospace; font-weight: 600; }}
        .btn {{
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }}
        .btn:hover {{
            background: #2563eb;
            transform: translateY(-1px);
        }}
    </style>
</head>
<body>
    <div class='container'>
        <div style='display:flex; justify-content:center; align-items:center; margin-bottom:16px;'>
            <img src='http://127.0.0.1:8000/assets/images/logo.png' alt='ADİSYON POS' style='height:54px; width:auto; object-fit:contain;'>
        </div>
        <div class='badge'>{statusBadge}</div>
        <h1>ÇEVRİMDIŞI (OFFLINE) KASA ÇALIŞIYOR</h1>
        <p>İnternet bağlantısı kesildi. Kasa servisi yerel modda kesintisiz hizmet vermektedir.<br>İnternet sağlandığında yerel adisyonlar otomatik olarak bulut merkeze senkronize edilecektir.</p>

        <div class='info-box'>
            <div class='info-row'>
                <span class='info-label'>🖥️ Yerel Servis Adresi:</span>
                <span class='info-value'>http://127.0.0.1:{port}</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>💾 Yerel Veritabanı:</span>
                <span class='info-value'>SQLite WAL Modu (Aktif)</span>
            </div>
            <div class='info-row'>
                <span class='info-label'>🔄 Otomatik Sync:</span>
                <span class='info-value'>Bağlantı Bekleniyor...</span>
            </div>
        </div>

        <div style='display:flex; gap:12px; justify-content:center; flex-wrap:wrap;'>
            <a class='btn' href='http://127.0.0.1:8000/login' style='background:#10b981;'>
                🖥️ Yerel Kasa Giriş Ekranına Git
            </a>
            <button class='btn' onclick='location.reload();' style='background:#3b82f6;'>
                🔄 Bağlantıyı Yeniden Dene
            </button>
        </div>
    </div>
</body>
</html>";

            return Results.Content(html, "text/html; charset=utf-8");
        });
    }
}

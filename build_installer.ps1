# =====================================================================
# AltF4 Adisyon Services - Automate Setup.exe Build Pipeline
# =====================================================================

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host " AltF4 Adisyon Setup.exe Derleme Islemi Baslatiliyor" -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan

# 1. Frontend Asset Derleme (Vite)
Write-Host "`n[1/3] Frontend statik varliklari derleniyor (npm run build)..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "[X] Frontend derlemesi basarisiz oldu!" -ForegroundColor Red
    exit 1
}

# 2. .NET 8 Cihaz Servisini Self-Contained Derleme
Write-Host "`n[2/3] .NET 8 Cihaz Servisi Self-Contained derleniyor (dotnet publish)..." -ForegroundColor Yellow
dotnet publish src/AltF4DeviceService.WebApi/AltF4DeviceService.WebApi.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
if ($LASTEXITCODE -ne 0) {
    Write-Host "[X] .NET 8 Servis derlemesi basarisiz oldu!" -ForegroundColor Red
    exit 1
}

# 3. Portable PHP Kopyalama (Eger yoksa)
if (-not (Test-Path "tools\php83\php.exe")) {
    if (Test-Path "C:\xampp\php\php.exe") {
        Write-Host "`n[+] Portable PHP kopyalaniyor (C:\xampp\php -> tools\php83)..." -ForegroundColor Yellow
        New-Item -ItemType Directory -Force -Path "tools\php83" | Out-Null
        Copy-Item -Path "C:\xampp\php\*" -Destination "tools\php83" -Recurse -Force
    }
}

# 4. Inno Setup Derleme (ISCC.exe)
Write-Host "`n[4/4] Inno Setup ile Setup.exe olusturuluyor..." -ForegroundColor Yellow

$ISCC_PATH = "C:\Program Files (x86)\Inno Setup 6\ISCC.exe"
if (-not (Test-Path $ISCC_PATH)) {
    $ISCC_PATH = "C:\Program Files\Inno Setup 6\ISCC.exe"
}

if (Test-Path $ISCC_PATH) {
    & "$ISCC_PATH" "setup_installer.iss"
    if ($LASTEXITCODE -eq 0) {
        Write-Host "`n====================================================" -ForegroundColor Green
        Write-Host " [OK] Setup.exe BASARIYLA OLUSTRULDU!" -ForegroundColor Green
        Write-Host " Cikti Konumu: dist/AltF4_Adisyon_Setup_v1.0.0.exe" -ForegroundColor Green
        Write-Host "====================================================" -ForegroundColor Green
    } else {
        Write-Host "[X] Inno Setup derlemesi sirasinda hata olustu." -ForegroundColor Red
    }
} else {
    Write-Host "[!] Inno Setup (ISCC.exe) sistemde bulunamadi." -ForegroundColor Yellow
    Write-Host "Lutfen Inno Setup 6'yi yukleyin veya setup_installer.iss dosyasini Inno Setup GUI ile acip derleyin." -ForegroundColor Gray
}

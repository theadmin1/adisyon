@echo off
title Adisyon Pos Otomasyon Başlatıcı
cd /d "%~dp0"

echo [1/3] Adisyon Pos Otomasyon Servisi (.NET 8) Başlatılıyor...

:: Cihaz Servisini (.NET 8 WebApi + System Tray) Masaüstü Oturumunda Arka Planda Başlat
if exist "service\AltF4DeviceService.WebApi.exe" (
    start /b "" "service\AltF4DeviceService.WebApi.exe" > nul 2>&1
)

echo [2/3] Adisyon Pos Otomasyon Web Servisi (PHP) Başlatılıyor...

:: Taşınabilir PHP veya Sistem PHP Yolunu Belirle
set PHP_BIN=runtime\php\php.exe
if exist "%PHP_BIN%" goto FOUND_PHP

set PHP_BIN=C:\xampp\php\php.exe
if exist "%PHP_BIN%" goto FOUND_PHP

set PHP_BIN=C:\php\php.exe
if exist "%PHP_BIN%" goto FOUND_PHP

set PHP_BIN=php.exe

:FOUND_PHP
echo PHP Yolu: %PHP_BIN%

:: Arka Planda Laravel Sunucusunu Başlat (Port: 8000)
start /b "" "%PHP_BIN%" artisan serve --host=127.0.0.1 --port=8000 > nul 2>&1

:: Sunucunun Açılması İçin 2 Saniye Bekle
timeout /t 2 /nobreak > nul

echo [3/3] Arayüz Tarayıcısı Başlatılıyor...

:: 1. Tercih: Yüklü Chrome (Chromium) App Modu
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --app=http://localhost:8000/login --no-first-run --disable-translate --disable-features=Translate
    goto END
)

:: 2. Tercih: Microsoft Edge App Modu
if exist "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" (
    start "" "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --app=http://localhost:8000/login --no-first-run
    goto END
)

:: 3. Tercih: Varsayılan Sistem Tarayıcısı
start http://localhost:8000/login

:END
echo Adisyon Pos Otomasyon Servisi ve Arayüzü Başarıyla Açıldı.

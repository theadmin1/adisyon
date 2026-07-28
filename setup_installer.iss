; =====================================================================
; AltF4 Adisyon Services - Inno Setup Installer Script
; Hedef: Windows Kasa / POS Bilgisayarları İçin Setup.exe Paketleyicisi
; =====================================================================

#define MyAppName "AltF4 Adisyon Otomasyonu"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "AltF4 Software"
#define MyAppURL "https://adisyon.synaptropic.com"
#define MyAppExeName "AdisyonKiosk.bat"

[Setup]
AppId={{D37D862C-1E4F-4B1A-9366-419842F5EBA1}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName=C:\AltF4Adisyon
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputDir=dist
OutputBaseFilename=AltF4_Adisyon_Setup_v{#MyAppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin

[Languages]
Name: "turkish"; MessagesFile: "compiler:Languages\Turkish.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"
Name: "autostart"; Description: "Bilgisayar açılışında adisyon servisini otomatik başlat"; GroupDescription: "Sistem Ayarları:"

[Files]
; 1. Laravel Ana Uygulama Dosyaları
Source: "app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "bootstrap\*"; DestDir: "{app}\bootstrap"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "config\*"; DestDir: "{app}\config"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "database\*"; DestDir: "{app}\database"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "public\*"; DestDir: "{app}\public"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "resources\*"; DestDir: "{app}\resources"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "routes\*"; DestDir: "{app}\routes"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "storage\*"; DestDir: "{app}\storage"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "artisan"; DestDir: "{app}"; Flags: ignoreversion
Source: "composer.json"; DestDir: "{app}"; Flags: ignoreversion
Source: "AdisyonKiosk.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: ".env"; DestDir: "{app}\.env"; Flags: ignoreversion

; 2. .NET 8 Windows Cihaz Servisi (Self-Contained Derlenmiş Binary'ler)
Source: "src\AltF4DeviceService.WebApi\bin\Release\net8.0-windows\win-x64\publish\*"; DestDir: "{app}\service"; Flags: ignoreversion recursesubdirs createallsubdirs

; 3. Taşınabilir PHP 8.3 Çalışma Zamanı (Varsa eklenir)
Source: "tools\php83\*"; DestDir: "{app}\runtime\php"; Flags: ignoreversion recursesubdirs createallsubdirs skipifsourcedoesntexist

[Dirs]
Name: "{app}\storage\logs"
Name: "{app}\storage\framework\views"
Name: "{app}\storage\framework\sessions"
Name: "{app}\storage\framework\cache"
Name: "{app}\database"

[Icons]
; Kiosk Başlatıcı ve Cihaz Servis Kısayolları
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\AdisyonKiosk.bat"; IconFilename: "{app}\public\favicon.ico"; Tasks: desktopicon
Name: "{autodesktop}\Cihaz Servisi & Admin Paneli"; Filename: "{app}\service\AltF4DeviceService.WebApi.exe"; IconFilename: "{app}\public\favicon.ico"; Tasks: desktopicon
Name: "{userstartup}\AltF4DeviceService"; Filename: "{app}\service\AltF4DeviceService.WebApi.exe"; Tasks: autostart
Name: "{group}\{#MyAppName}"; Filename: "{app}\AdisyonKiosk.bat"; IconFilename: "{app}\public\favicon.ico"
Name: "{group}\Cihaz Servisi & Admin Paneli"; Filename: "{app}\service\AltF4DeviceService.WebApi.exe"; IconFilename: "{app}\public\favicon.ico"

[Run]
; 1. Yazma Yetkisi Tanımla (SQLite veritabanı kilitleri ve loglar için kullanıcıya tam yetki verilir)
Filename: "icacls.exe"; Parameters: """{app}"" /grant Users:(OI)(CI)F /T"; Flags: runhidden; Description: "Dosya erişim izinleri yapılandırılıyor..."

; 2. Yerel SQLite Veritabanı Oluştur (Varsa dokunulmaz)
Filename: "cmd.exe"; Parameters: "/c if not exist ""{app}\database\database.sqlite"" copy nul ""{app}\database\database.sqlite"""; Flags: runhidden

; 3. Otomatik Migrasyon Çalıştır
Filename: "{app}\runtime\php\php.exe"; Parameters: "artisan migrate --force"; WorkingDir: "{app}"; Flags: runhidden; StatusMsg: "Veritabanı tabloları güncelleniyor..."; Check: FileExists(ExpandConstant('{app}\runtime\php\php.exe'))

; 4. Cihaz Servisini (.NET 8 System Tray + Form) Kullanıcı Masaüstü Oturumunda Başlat
Filename: "{app}\service\AltF4DeviceService.WebApi.exe"; Flags: nowait postinstall skipifsilent; Description: "AltF4 Cihaz Servisini ve System Tray Panelini Başlat"

[UninstallRun]
Filename: "sc.exe"; Parameters: "stop AltF4DeviceService"; Flags: runhidden
Filename: "sc.exe"; Parameters: "delete AltF4DeviceService"; Flags: runhidden

[Code]
function DirExists(Dir: String): Boolean;
begin
  Result := DirExists(Dir);
end;

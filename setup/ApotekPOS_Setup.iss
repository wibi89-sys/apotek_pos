; =====================================================================
; Apotek POS Pro - Inno Setup Script
; Standalone Windows Installer untuk Server & Client Apotek
; =====================================================================

#define MyAppName "Apotek POS Pro"
#define MyAppVersion "2.0.0"
#define MyAppPublisher "Apotek POS System"
#define MyAppURL "https://github.com/wibi89-sys/apotek_pos"
#define MyAppExeName "ApotekPOS.exe"

[Setup]
AppId={{D8F4E231-89B7-4A19-9B45-98762512F9A0}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName=C:\ApotekPOS
DefaultGroupName={#MyAppName}
AllowNoIcons=yes
OutputDir=..\dist
OutputBaseFilename=ApotekPOS_Setup_v2.0
SetupIconFile=..\ApotekPOS.ico
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64

[Languages]
Name: "indonesian"; MessagesFile: "compiler:Languages\Indonesian.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked
Name: "autostart"; Description: "Jalankan otomatis saat Windows dinyalakan (Auto-start on boot)"; GroupDescription: "Pengaturan Startup:"

[Files]
; Executable utama & Icon
Source: "..\ApotekPOS.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\ApotekPOS.ico"; DestDir: "{app}"; Flags: ignoreversion

; Modul Aplikasi POS Local (Laravel Backend)
Source: "..\apotek-pos-local\*"; DestDir: "{app}\apotek-pos-local"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "*.log,storage\framework\cache\*,storage\framework\sessions\*,storage\framework\views\*"

; Modul Hardware Bridge (U.are.U Fingerprint & Mini Printer)
Source: "..\hardware-bridge\*"; DestDir: "{app}\hardware-bridge"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "__pycache__\*,*.pyc"

; Portable Runtime (PHP & Database jika dibundle)
Source: "..\runtime\*"; DestDir: "{app}\runtime"; Flags: ignoreversion recursesubdirs createallsubdirs skipifsourcedoesntexist

; Skrip Bantuan & Stop/Start
Source: "..\scripts\*"; DestDir: "{app}\scripts"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; IconFilename: "{app}\ApotekPOS.ico"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; IconFilename: "{app}\ApotekPOS.ico"; Tasks: desktopicon

[Registry]
; Auto-start saat Windows Boot
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "ApotekPOS"; ValueData: """{app}\{#MyAppExeName}"""; Tasks: autostart; Flags: uninsdeletevalue

[Run]
; Tambahkan aturan Windows Firewall untuk port LAN (Kasir 8001 dan Bridge 8765)
Filename: "netsh.exe"; Parameters: "advfirewall firewall add rule name=""Apotek POS Local"" dir=in action=allow protocol=TCP localport=8001"; Flags: runhidden
Filename: "netsh.exe"; Parameters: "advfirewall firewall add rule name=""Apotek POS Bridge"" dir=in action=allow protocol=TCP localport=8765"; Flags: runhidden

; Jalankan aplikasi setelah instalasi selesai
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "netsh.exe"; Parameters: "advfirewall firewall delete rule name=""Apotek POS Local"""; Flags: runhidden
Filename: "netsh.exe"; Parameters: "advfirewall firewall delete rule name=""Apotek POS Bridge"""; Flags: runhidden

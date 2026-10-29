# EcoLactManual: comandos en Windows PowerShell

> Cambia `C:\CICLO VI\EcoLact` por la carpeta donde ya tienes EcoLactWeb.

## A. Si descargas el ZIP adjunto

```powershell
cd "C:\CICLO VI\EcoLact"
# Copia el ZIP descargado a esta carpeta y usa tu nombre real de archivo.
Expand-Archive -Path ".\EcoLact_Manual_Antigravity_Kit.zip" -DestinationPath "." -Force
Rename-Item ".\EcoLact_Manual_Antigravity_Kit" "EcoLactManual"
cd ".\EcoLactManual"
Get-ChildItem -Force
```

Si Windows descargó el ZIP a `Downloads`, usa la ruta completa del ZIP en `-Path`, por ejemplo `$env:USERPROFILE\Downloads\EcoLact_Manual_Antigravity_Kit.zip`.

## B. Si prefieres crear la estructura a mano (no requiere ZIP)

```powershell
cd "C:\CICLO VI\EcoLact"
New-Item -ItemType Directory -Path ".\EcoLactManual" -Force
cd ".\EcoLactManual"
$folders = @(
  ".agents\skills\descubrir-flujo-ecolact",
  ".agents\skills\capturar-pantallas",
  ".agents\skills\anotar-capturas",
  ".agents\skills\redactar-procedimientos",
  ".agents\skills\validar-evidencias",
  ".agents\skills\generar-manual-word-pdf",
  "flujos", "ejemplo\capturas-originales", "ejemplo\capturas-anotadas",
  "scripts", "salida"
)
$folders | ForEach-Object { New-Item -ItemType Directory -Force -Path $_ | Out-Null }
New-Item -ItemType File -Path ".\AGENTS.md" -Force
Get-ChildItem -Recurse -Force
```

**Importante:** el método B solo crea carpetas vacías; para obtener las skills, scripts y Word de ejemplo, utiliza el ZIP.

## C. Instalar las dependencias del kit

```powershell
cd "C:\CICLO VI\EcoLact\EcoLactManual"
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install --upgrade pip
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

No necesitas activar el entorno ni cambiar `ExecutionPolicy` para ejecutar Python usando su ruta completa.

## D. Obtener capturas anotadas, Word y PDF

```powershell
.\.venv\Scripts\python.exe .\scripts\anotar.py .\ejemplo\zonas.json
.\.venv\Scripts\python.exe .\scripts\generar_manual.py .\ejemplo\zonas.json --out .\salida
Get-ChildItem .\salida
Start-Process .\salida\Manual_EcoLact_Zonas_Demostracion.docx
```

Si el PDF no aparece: instala LibreOffice, reinicia PowerShell y vuelve a ejecutar; o abre el Word y exporta PDF desde Word.

## E. Colocar el archivo Word dentro de otra subcarpeta

```powershell
New-Item -ItemType Directory -Force -Path .\salida\manual-completo
Copy-Item .\salida\Manual_EcoLact_Zonas_Demostracion.docx .\salida\manual-completo\
```

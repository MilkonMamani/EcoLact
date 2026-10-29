# Ejecutar desde PowerShell dentro de la carpeta del kit o desde la raíz del proyecto una vez copiado.
$ErrorActionPreference = 'Stop'
if (-not (Get-Command py -ErrorAction SilentlyContinue)) {
  Write-Host 'Instala Python 3 desde https://www.python.org/downloads/windows/ y vuelve a abrir PowerShell.' -ForegroundColor Yellow
  exit 1
}
py -3 -m venv .venv
$python = Join-Path (Get-Location) '.venv\Scripts\python.exe'
& $python -m pip install --upgrade pip
& $python -m pip install -r requirements.txt
Write-Host 'Entorno listo. Para generar capturas anotadas:' -ForegroundColor Green
Write-Host '.\.venv\Scripts\python.exe scripts\anotar.py ejemplo\zonas.json'
Write-Host '.\.venv\Scripts\python.exe scripts\generar_manual.py ejemplo\zonas.json --out salida'
Write-Host 'Opcional: instalar Playwright aparte (revisar compatibilidad de Windows).' -ForegroundColor Yellow

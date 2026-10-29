# EcoLact Manual — Proyecto independiente de documentación

**Este proyecto NO va dentro de EcoLactWeb ni modifica Laravel/KMP.** Su función es observar el sistema, guardar evidencias, anotar imágenes y generar un manual Word/PDF.

## 1) Dónde ubicarlo

Ruta sugerida (ejemplo: cambia el disco o la carpeta si tu proyecto está en otro sitio):

```text
C:\CICLO VI\EcoLact\
  EcoLactWeb\                    ← código Laravel original (sin modificaciones)
  EcoLactMobile\                 ← app móvil, si existe
  EcoLactManual\                 ← ESTA CARPETA: proyecto documental aparte
    AGENTS.md
    .agents\skills\...\SKILL.md
    ejemplo\
    flujos\
    scripts\
    salida\
      Manual_EcoLact_Zonas_Demostracion.docx
      Manual_EcoLact_Zonas_Demostracion.pdf
```

Descarga el ZIP, extráelo en `C:\CICLO VI\EcoLact\` y **renombra la carpeta extraída `EcoLact_Manual_Antigravity_Kit` a `EcoLactManual`**. También puedes dejar el nombre original si prefieres.

## 2) Cómo permitir que Antigravity lea el código sin mezclar carpetas

En la Antigravity App: **New Project > Add Folder** y añade **DOS carpetas**: `EcoLactManual` (donde genera documentos) y `EcoLactWeb` (para consultar rutas, vistas y validaciones). Si usas también KMP, añade su carpeta como tercer origen. En el agente indica explícitamente que **solo puede escribir en EcoLactManual**. Que una carpeta figure en el proyecto no es un control técnico de solo lectura: revisa cada cambio propuesto por el agente y haz respaldo de EcoLactWeb.

Si tu versión de IDE no admite varios folders, abre `EcoLactManual` y proporciona el proyecto fuente mediante el explorador o un workspace padre, sin copiar el código a la documentación. No ejecutes operaciones sobre la BD real.

## 3) Instalación recomendada

1. Antigravity App o IDE: https://antigravity.google/download (Windows 10 64-bit o superior).
2. Python 3 de 64 bits: https://www.python.org/downloads/windows/; durante la instalación habilita `Add Python to PATH`, si se ofrece.
3. Microsoft Word para editar, o LibreOffice para abrir/exportar PDF: https://www.libreoffice.org/download/
4. PowerShell en la carpeta `EcoLactManual`:

```powershell
cd "C:\CICLO VI\EcoLact\EcoLactManual"
py -3 --version
powershell -ExecutionPolicy Bypass -File .\instalar_entorno.ps1
```

Si `py` no aparece, reinstala Python con el lanzador de Windows o usa `python -m venv .venv` manualmente.

## 4) Comprobar que funciona sin tocar EcoLactWeb

Desde PowerShell en `EcoLactManual`:

```powershell
.\.venv\Scripts\python.exe scripts\anotar.py ejemplo\zonas.json
.\.venv\Scripts\python.exe scripts\generar_manual.py ejemplo\zonas.json --out salida
explorer .\salida
```

Los archivos Word y PDF estarán en `EcoLactManual\salida\`. La conversión a PDF se intenta automáticamente cuando LibreOffice está disponible; también puedes exportar desde Word.

## 5) Skills: no requieren un instalador externo

Antigravity detecta las skills de `.agents\skills\<nombre>\SKILL.md` **cuando abres el workspace**. En Antigravity inicia con:

> Revisa `AGENTS.md`, usa la skill `descubrir-flujo-ecolact` y analiza EcoLactWeb sin modificarlo. Primero entrega un inventario de roles, rutas, pantallas, precondiciones y transiciones comprobables desde zonas, alta de acopiador/proveedor, entrega, calidad, liquidación, pago y consulta. Marca todo lo que no esté verificado. Después, crea un piloto de `Zonas > Crear zona` basado en pruebas seguras, guarda capturas y genera Word/PDF en `EcoLactManual/salida`.

Las skills pueden activarse automáticamente por su descripción. En la App o CLI también puedes invocarlas como `/<nombre>` si la versión lo admite.

## 6) Orden correcto del proceso

`E01 Configuración/zonas` → `E02 Alta de acopiador` → `E03 Alta de proveedor` → `E04 Entrega` → `E05 Calidad` → `E06 Valorización` → `E07 Liquidación` → `E08 Pago registrado` → `E09 Consulta/auditoría`.

Es un **mapa para investigar y verificar**, NO un conjunto de acciones ya demostradas. La caja, ventas y egresos se documentan como flujo adicional solo si el software contiene y verifica esas integraciones. Nunca ejecutar transferencias reales.

## 7) Qué significa el Word de demostración

`salida/Manual_EcoLact_Zonas_Demostracion.docx` contiene una portada, una ficha y cinco pasos ilustrados sobre tus capturas de Zonas. Es **un prototipo documental**, no una prueba de que se guardó una zona. Falta la captura del resultado posterior a «Crear zona».

Para insertar nuevos procedimientos: crea un JSON siguiendo `ejemplo/zonas.json`, añade las capturas en `capturas-originales`, fija coordenadas reales de cada control y ejecuta ambos scripts. Antigravity debe realizar la inspección, verificación y preparación del JSON.

## 8) Compatibilidad y herramientas opcionales

- El navegador de Antigravity puede bastar para capturas y pruebas en localhost: no instales Playwright por obligación.
- Playwright Python: `python -m pip install playwright` y `python -m playwright install chromium` solo si tu equipo resulta compatible; **su documentación vigente lista Windows 11+**, por lo que en un Windows 10 antiguo prioriza el navegador integrado o una actualización del sistema.
- `python-docx` crea el Word, `Pillow` anota las imágenes y LibreOffice/Word convierte a PDF.

## 9) Archivos principales

- `AGENTS.md`: reglas y límites permanentes del agente.
- `.agents/skills/`: seis skills especializadas.
- `flujos/mapa_objetivo.json`: mapa del proceso completo sujeto a comprobación.
- `ejemplo/zonas.json`: instrucciones, resultados y coordenadas del piloto.
- `ejemplo/capturas-originales/`: fotos de referencia intactas.
- `ejemplo/capturas-anotadas/`: resaltados derivados.
- `scripts/anotar.py`: genera evidencias con resaltado.
- `scripts/generar_manual.py`: genera el Word y exporta PDF si hay LibreOffice.
- `salida/`: **única carpeta de entrega de documentos**.

## Fuentes oficiales

- Antigravity inicio: https://antigravity.google/docs/getting-started
- Antigravity proyectos con varias carpetas: https://www.antigravity.google/docs/cli-getting-started
- Antigravity skills: https://www.antigravity.google/docs/skills
- Playwright Python: https://playwright.dev/python/docs/intro
- python-docx: https://pypi.org/project/python-docx/

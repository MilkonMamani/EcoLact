---
title: "Control de versiones con Git"
---

<div class="ec-desarrollo" markdown="1">

<div class="ec-dev-banner" markdown="1">

:material-git: 7. Git y convenciones de desarrollo

</div>

<div class="ec-dev-content" markdown="1">

<span class="ec-dev-kicker">EcoLact / Guía de desarrollo</span>

# Control de versiones con Git

Git permite comprender y revisar la evolución de EcoLact. El flujo siguiente es una propuesta colaborativa; no implica que el repositorio exija ramas protegidas, aprobaciones o una convención de commits específica.

<div class="ec-dev-flow five" markdown="1">

<div class="ec-dev-flow-item" markdown="1">

:material-source-repository:

Clonar repositorio

</div>

<div class="ec-dev-flow-item" markdown="1">

:material-source-branch:

Crear rama

</div>

<div class="ec-dev-flow-item" markdown="1">

:material-file-edit:

Realizar cambios

</div>

<div class="ec-dev-flow-item" markdown="1">

:material-source-commit:

Registrar commits

</div>

<div class="ec-dev-flow-item" markdown="1">

:material-source-pull:

Pull Request

</div>

</div>

## Procedimiento propuesto

Clona únicamente si aún no tienes una copia. Desde la carpeta padre:

<div class="ec-dev-command" markdown="1">

```powershell
git clone https://github.com/MilkonMamani/EcoLact.git
cd EcoLact
```

</div>

Desde la raíz del repositorio, comprueba el estado y crea una rama para una tarea concreta. La rama parte de la revisión en la que estés situado.

<div class="ec-dev-command" markdown="1">

```powershell
git status
git switch -c feature/nueva-funcionalidad
```

</div>

<div class="ec-dev-result" markdown="1">

**Resultado esperado:** trabajas en una rama identificable. **Comprobación:** git status muestra la nueva rama. Si había cambios previos, revisa a quién pertenecen y no los incluyas automáticamente en tu tarea.

</div>

Implementa el cambio, actualiza la documentación afectada y ejecuta las comprobaciones pertinentes. Después revisa las diferencias antes de prepararlas:

<div class="ec-dev-command" markdown="1">

```powershell
git diff
git status
git add .
git diff --cached
git commit -m "Descripción del cambio"
```

</div>

**Qué hace:** `git add .` prepara todos los cambios del directorio actual y sus descendientes, incluidas eliminaciones y archivos nuevos no ignorados. Si no todos pertenecen a la tarea, utiliza `git add ruta/del/archivo` en su lugar. `git diff --cached` permite revisar exactamente lo que entrará en el commit.

<div class="ec-dev-result" markdown="1">

**Resultado esperado:** el commit contiene un cambio coherente y sin secretos. **Comprobación:** usa git status y git log --oneline -5; revisa los archivos incluidos, no solo el mensaje.

</div>

Cuando tengas permiso de escritura en el repositorio, publica la rama:

<div class="ec-dev-command" markdown="1">

```powershell
git push -u origin feature/nueva-funcionalidad
```

</div>

El comando envía commits y configura el seguimiento de la rama remota. Si no tienes acceso de escritura, usa un fork y su remoto propio. En GitHub crea un Pull Request hacia la rama acordada por el equipo; explica problema, cambio y comprobaciones. **Resultado esperado:** el diff es revisable; comprueba la rama destino antes de solicitar revisión.

## Comandos principales

<div class="ec-dev-command-table" markdown="1">

| Comando | ¿Para qué sirve? |
| --- | --- |
| `git clone URL` | Descargar una nueva copia y su historial. |
| `git status` | Ver rama y archivos modificados o preparados. |
| `git switch -c feature/nueva-funcionalidad` | Crear una rama desde la revisión actual y cambiar a ella. |
| `git diff` | Revisar cambios todavía no preparados. |
| `git add .` | Preparar todos los cambios bajo el directorio actual; revisarlos antes. |
| `git diff --cached` | Revisar el contenido del próximo commit. |
| `git commit -m "Descripción del cambio"` | Registrar los cambios preparados en el historial local. |
| `git push -u origin feature/nueva-funcionalidad` | Publicar la rama con permisos y establecer seguimiento. |
| `git log --oneline -5` | Consultar los cinco commits recientes de la rama. |

</div>

## Convenciones sugeridas y reglas comprobadas

<div class="ec-dev-command-table" markdown="1">

| Ámbito | Convención o evidencia |
| --- | --- |
| Ramas · propuesta | feature/ para nuevas funciones, fix/ para correcciones y docs/ para documentación. Usa nombres breves con guiones. |
| Commits · propuesta | Describe una acción concreta y su propósito. Un commit debería agrupar cambios relacionados, sin imponer un formato que el repositorio no exige. |
| Revisión · propuesta | Revisa diff y staged; separa cambios ajenos y adjunta resultados reales de las comprobaciones. |
| Configuración · existente | EcoLactWeb/.gitignore excluye .env, vendor y node_modules. EcoLactMobile/.gitignore excluye local.properties y salidas build. Un archivo ya seguido no deja de estarlo solo por añadirlo a .gitignore. |
| Kotlin · existente | gradle.properties declara kotlin.code.style=official. Mantén las convenciones de los archivos vecinos. |
| Backend · existente | EcoLactWeb/AGENTS.md pide respetar la estructura y convenciones existentes. Estas pautas no acreditan una protección de ramas en GitHub. |
| Documentación · propuesta | Edita Markdown y recursos fuente; compila MkDocs. site/ contiene archivos seguidos en esta copia, así que revisa su diff y sigue el criterio del equipo para entregarlos. |
| Pull Request · propuesta | Explica el resultado para un revisor que no participó en el trabajo. Incluye capturas reales si cambió la interfaz y comunica limitaciones de prueba. |

</div>

## Comprobar la documentación antes del PR

En un entorno Python con MkDocs, Material y las extensiones configuradas instalados, desde la raíz:

<div class="ec-dev-command" markdown="1">

```powershell
cd docs/informeEcoLact
python -m mkdocs build --clean
```

</div>

**Resultado esperado:** la construcción finaliza sin errores; abre las páginas afectadas y revisa enlaces, capturas y móvil. La compilación documental no sustituye las pruebas del backend o Android cuando se modifican sus funcionalidades.




</div>

<div class="ec-dev-sources" markdown="1">

<details markdown="1">
<summary>Fuentes verificables del repositorio</summary>

- [EcoLactWeb/.gitignore](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/.gitignore)
- [EcoLactMobile/.gitignore](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/.gitignore)
- [EcoLactMobile/gradle.properties](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactMobile/gradle.properties)
- [EcoLactWeb/AGENTS.md](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/AGENTS.md)
- [EcoLactWeb/composer.json](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/EcoLactWeb/composer.json)
- [docs/informeEcoLact/mkdocs.yml](https://github.com/MilkonMamani/EcoLact/blob/75901337489dbe3c61bf5e95357ea365790ab565/docs/informeEcoLact/mkdocs.yml)

Revisión de referencia: `75901337489d`. Los procedimientos se contrastaron con estas fuentes; los comandos de instalación, migración y compilación del producto no se ejecutaron para elaborar esta guía.

</details>

</div>

<div class="ec-dev-nav" markdown="1">

[← Configuración del proyecto](configuracion.md)

</div>

</div>

</div>


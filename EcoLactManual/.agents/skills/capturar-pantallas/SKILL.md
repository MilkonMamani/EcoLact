---
name: capturar-pantallas
description: Obtiene capturas reales antes y después de cada acción de usuario en EcoLact, sin modificar datos de producción. Úsala para evidencias web o móviles.
---

# Capturar pantallas
1. Determinar si Antigravity App/CLI permite `/browser` o si la edición IDE tiene integración de navegador. Si falla, usar Playwright como herramienta opcional o capturas manuales.
2. Iniciar Laravel web local, verificar URL antes de acceder, utilizar usuario de PRUEBA. Asegurar que la DB es de prueba, nunca producción.
3. En cada paso: (a) capturar pantalla previa; (b) identificar botón/selector accesible; (c) anotar coordenadas o selector; (d) ejecutar una sola acción segura; (e) capturar resultado, toast/modal/listado/estado; (f) registrar texto exacto del mensaje y marca de tiempo.
4. Evitar credenciales, DNIs, información bancaria, boletas reales y nombres reales. No almacenar estado autenticado de Playwright dentro de repositorios ni archivos compartidos.
5. Preferir capturas PNG en resolución consistente (por ejemplo 1440x900) y escalado de navegador 100%. Si la acción implica pagos/creaciones, no ejecutarla hasta tener un entorno de prueba autorizado.
6. Para app KMP/Android, determinar entorno de emulador y ADB; captura de pantalla con método compatible confirmado, sin presuponer que Playwright controla Android nativo.
7. Guardar originales en `evidencias/originales/`, acciones e inspecciones en registro `evidencias/ledger.json`; identificar explícitamente los resultados no capturados.

---
name: validar-evidencias
description: Audita un manual de EcoLact contra código e interfaz reales, detecta resultados no verificados, capturas incompletas y posibles datos sensibles.
---

# Validar evidencias
Revisar en secuencia:
1. Cada paso apunta a un componente realmente visible y a la ruta correcta.
2. Texto exacto de controles, mensajes, rol, fecha y estado, comparado contra la aplicación.
3. Antes y después de acciones modificadoras; confirmar persistencia consultando UI (no usar produccion).
4. Conservar trazabilidad al código, pruebas y registros de ejemplo; detectar capturas duplicadas presentadas como diferentes acciones.
5. Censurar PII, contraseñas, tokens y datos de pago antes de exportar.
6. Revisar numeración, legibilidad al 100%, recuadros sin errores, márgenes, encabezados y tablas en DOCX/PDF.
7. Si falta prueba, marcar `PENDIENTE` y no cerrar el paso como correcto.

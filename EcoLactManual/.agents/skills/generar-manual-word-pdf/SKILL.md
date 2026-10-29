---
name: generar-manual-word-pdf
description: Crea y exporta manuales profesionales de EcoLact a Word y PDF usando un manifiesto de pasos y capturas anotadas.
---

# Generar Word/PDF
1. Reunir un manifiesto JSON con pasos y rutas relativas a imágenes anotadas, siguiendo `ejemplo/zonas.json`.
2. Ejecutar `python scripts/anotar.py ejemplo/zonas.json` y `python scripts/generar_manual.py ejemplo/zonas.json --out salida`.
3. Verificar DOCX editable. Convertir PDF mediante LibreOffice instalado si está disponible; si no, exportar desde Microsoft Word manualmente.
4. Publicar portada, identificación del proceso, prerequisitos, pantallas paso a paso, resultados y pendientes. Agregar tabla de contenido automática en manuales grandes; usar estilos de título apropiados.
5. Revisar visualmente todas las páginas de ambos archivos antes de finalizar. No ocultar que el ejemplo de Zonas carece de captura de confirmación final.

---
name: anotar-capturas
description: Dibuja recuadros, flechas, etiquetas y números sobre capturas de EcoLact, conservando la imagen original. Úsala para tutoriales visuales.
---

# Anotar capturas
- Verificar que las capturas son originales y que las coordenadas coinciden con su resolución real.
- Preferir resaltar **una sola acción** por imagen: oscurecimiento leve del fondo, rectángulo rojo de 4-8 píxeles, flecha que termine sobre el control, número y etiqueta de máximo 4 palabras.
- Dar prioridad a la legibilidad al imprimir en A4/A4 apaisado. Recortar a la región relevante sin perder el contexto del menú o del modal.
- Usar `python scripts/anotar.py ejemplo/zonas.json` como demostración; adaptar el JSON después de medir el control exacto. Ejecutar `python scripts/anotar.py --help` primero cuando se necesite.
- No modificar originales. Revisar visualmente etiquetas que se salgan del recorte o flechas desalineadas.

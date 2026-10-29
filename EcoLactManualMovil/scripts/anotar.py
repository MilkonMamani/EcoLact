"""Dibuja las marcas de paso (recuadro rojo + círculo numerado) sobre una captura.

Solo se marca la interfaz; los colores de la aplicación no se alteran.
"""
from PIL import Image, ImageDraw, ImageFont

ROJO = (217, 38, 38)


def _fuente(tam):
    for nombre in ("arialbd.ttf", "Arial Bold.ttf", "DejaVuSans-Bold.ttf"):
        try:
            return ImageFont.truetype(nombre, tam)
        except OSError:
            continue
    return ImageFont.load_default()


def anotar(origen, destino, marcas):
    """marcas: lista de (numero, (x1, y1, x2, y2)) en píxeles de la captura."""
    im = Image.open(origen).convert("RGB")
    dr = ImageDraw.Draw(im)
    escala = im.width / 912
    grosor = max(3, round(5 * escala))
    radio = round(27 * escala)
    fuente = _fuente(round(32 * escala))
    for numero, (x1, y1, x2, y2) in marcas:
        x1, y1 = max(x1, grosor), max(y1, grosor)
        x2, y2 = min(x2, im.width - grosor), min(y2, im.height - grosor)
        dr.rounded_rectangle((x1, y1, x2, y2), radius=round(10 * escala), outline=ROJO, width=grosor)
        cx = min(max(x1, radio + 2), im.width - radio - 2)
        cy = min(max(y1, radio + 2), im.height - radio - 2)
        dr.ellipse((cx - radio, cy - radio, cx + radio, cy + radio), fill=ROJO, outline=(255, 255, 255), width=max(2, grosor // 2))
        dr.text((cx, cy), str(numero), fill=(255, 255, 255), font=fuente, anchor="mm")
    im.save(destino)
    return destino

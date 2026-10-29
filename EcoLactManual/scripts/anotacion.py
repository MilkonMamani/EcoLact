#!/usr/bin/env python3
"""Anotación uniforme de capturas del manual EcoLact (Pillow).

Lenguaje visual: atenuación moderada fuera del objetivo, recuadro rojo exacto alrededor del
control (coordenadas medidas en el DOM por capturar.py), flecha corta y círculo con el número
del paso. La imagen original no se modifica; el resultado solo se recorta (sin reescalar).
"""
import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageStat

ROJO = (217, 38, 38)
BLANCO = (255, 255, 255)


def _fuente(tam):
    for ruta in ('C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf'):
        try:
            return ImageFont.truetype(ruta, tam)
        except OSError:
            pass
    return ImageFont.load_default()


def _recorte_auto(W, H, rect, min_w, min_h):
    """Ventana de contexto centrada en el objetivo, sin salir de la imagen."""
    x1, y1, x2, y2 = rect
    w = max(min_w, x2 - x1 + 260)
    h = max(min_h, y2 - y1 + 200)
    w, h = min(w, W), min(h, H)
    cx, cy = (x1 + x2) / 2, (y1 + y2) / 2
    left = int(min(max(0, cx - w / 2), W - w))
    top = int(min(max(0, cy - h / 2), H - h))
    return [left, top, left + int(w), top + int(h)]


def anotar(src, dst, rect, numero, recorte='auto', atenuar=True, etiqueta=None, posicion=None):
    """rect = [x1, y1, x2, y2] en píxeles de la captura. recorte: 'auto', 'completo' o [l, t, r, b]."""
    original = Image.open(src).convert('RGBA')
    W, H = original.size
    x1, y1, x2, y2 = rect
    pad = 6
    r = (max(0, x1 - pad), max(0, y1 - pad), min(W, x2 + pad), min(H, y2 + pad))

    img = original.copy()
    if atenuar:
        velo = Image.new('RGBA', (W, H), (15, 23, 30, 95))
        img = Image.alpha_composite(img, velo)
        img.paste(original.crop(r), r[:2])
    d = ImageDraw.Draw(img)
    d.rounded_rectangle(r, radius=7, outline=ROJO, width=4)

    # Posición del número: el lado más despejado (menor densidad de bordes, es decir, sin texto ni
    # controles, en la captura original) entre izquierda, derecha, arriba y abajo, para no tapar otros controles.
    rad = 21
    gap = 46
    cy = (r[1] + r[3]) / 2
    cx = (r[0] + r[2]) / 2
    candidatos = [
        ((r[0] - gap - rad, cy), (r[0] - 3, cy)),
        ((r[2] + gap + rad, cy), (r[2] + 3, cy)),
        ((cx, r[1] - gap - rad), (cx, r[1] - 3)),
        ((cx, r[3] + gap + rad), (cx, r[3] + 3)),
    ]
    gris = original.convert('L').filter(ImageFilter.FIND_EDGES)
    mejor = None
    for orden, (c, pnt) in enumerate(candidatos):
        caja_c = (min(c[0] - rad, pnt[0]) - 4, min(c[1] - rad, pnt[1]) - 4, max(c[0] + rad, pnt[0]) + 4, max(c[1] + rad, pnt[1]) + 4)
        if caja_c[0] < 4 or caja_c[1] < 4 or caja_c[2] > W - 4 or caja_c[3] > H - 4:
            continue
        zona = gris.crop(tuple(int(v) for v in caja_c))
        puntaje = ImageStat.Stat(zona).mean[0] + orden * 0.8
        if mejor is None or puntaje < mejor[0]:
            mejor = (puntaje, c, pnt)
    if posicion is not None:
        idx = {'izquierda': 0, 'derecha': 1, 'arriba': 2, 'abajo': 3}[posicion]
        mejor = (0, *candidatos[idx])
    _, centro, punta = mejor or (0, *candidatos[3])

    # Flecha corta del círculo al recuadro.
    ang = math.atan2(punta[1] - centro[1], punta[0] - centro[0])
    inicio = (centro[0] + rad * math.cos(ang), centro[1] + rad * math.sin(ang))
    base = (punta[0] - 13 * math.cos(ang), punta[1] - 13 * math.sin(ang))
    d.line([inicio, base], fill=ROJO, width=5)
    s = 15
    p1 = (punta[0] - s * math.cos(ang - 0.55), punta[1] - s * math.sin(ang - 0.55))
    p2 = (punta[0] - s * math.cos(ang + 0.55), punta[1] - s * math.sin(ang + 0.55))
    d.polygon([punta, p1, p2], fill=ROJO)

    d.ellipse((centro[0] - rad, centro[1] - rad, centro[0] + rad, centro[1] + rad), fill=ROJO, outline=BLANCO, width=3)
    f = _fuente(22)
    txt = str(numero)
    tb = d.textbbox((0, 0), txt, font=f)
    d.text((centro[0] - (tb[2] - tb[0]) / 2 - tb[0], centro[1] - (tb[3] - tb[1]) / 2 - tb[1]), txt, font=f, fill=BLANCO)

    if etiqueta:
        fe = _fuente(15)
        eb = d.textbbox((0, 0), etiqueta, font=fe)
        ew, eh = eb[2] - eb[0] + 16, eb[3] - eb[1] + 10
        ex = min(max(4, centro[0] - ew / 2), W - ew - 4)
        ey = centro[1] + rad + 4 if centro[1] + rad + eh + 8 < H else centro[1] - rad - eh - 4
        d.rounded_rectangle((ex, ey, ex + ew, ey + eh), radius=5, fill=ROJO)
        d.text((ex + 8 - eb[0], ey + 5 - eb[1]), etiqueta, font=fe, fill=BLANCO)

    if recorte == 'auto':
        caja = _recorte_auto(W, H, rect, 1100, 640)
    elif recorte == 'completo' or recorte is None:
        caja = [0, 0, W, H]
    else:
        caja = recorte
    # Garantiza que ni el recuadro ni el número queden cortados.
    l, t, rr, b = caja
    l = min(l, int(min(r[0], centro[0] - rad) - 10))
    t = min(t, int(min(r[1], centro[1] - rad) - 10))
    rr = max(rr, int(max(r[2], centro[0] + rad) + 10))
    b = max(b, int(max(r[3], centro[1] + rad) + 10))
    caja = (max(0, l), max(0, t), min(W, rr), min(H, b))

    Path(dst).parent.mkdir(parents=True, exist_ok=True)
    img.crop(caja).convert('RGB').save(dst, optimize=True)
    return caja

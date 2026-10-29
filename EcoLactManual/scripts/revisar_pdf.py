#!/usr/bin/env python3
"""Renderiza páginas del PDF a PNG para revisión visual y genera hojas de contacto.

Uso: python scripts/revisar_pdf.py [pdf] [--paginas 1-6] [--dpi 70]
Salida: evidencias/revision_pdf/ (no forma parte de la entrega).
"""
import argparse
from pathlib import Path

import fitz
from PIL import Image

BASE = Path(__file__).resolve().parents[1]

ap = argparse.ArgumentParser()
ap.add_argument('pdf', nargs='?', default=str(BASE / 'salida' / 'Manual_Usuario_EcoLact_Administrador.pdf'))
ap.add_argument('--paginas', default=None)
ap.add_argument('--dpi', type=int, default=60)
ap.add_argument('--por-hoja', type=int, default=6)
a = ap.parse_args()

out = BASE / 'evidencias' / 'revision_pdf'
out.mkdir(parents=True, exist_ok=True)
for f in out.glob('*.png'):
    f.unlink()
doc = fitz.open(a.pdf)
if a.paginas:
    i, j = (int(x) for x in a.paginas.split('-'))
    rango = range(i - 1, min(j, len(doc)))
else:
    rango = range(len(doc))
imgs = []
for n in rango:
    pix = doc[n].get_pixmap(dpi=a.dpi)
    imgs.append(Image.frombytes('RGB', (pix.width, pix.height), pix.samples))
print('Páginas totales:', len(doc))
cols = 3
for h in range(0, len(imgs), a.por_hoja):
    grupo = imgs[h:h + a.por_hoja]
    w, hh = grupo[0].size
    filas = (len(grupo) + cols - 1) // cols
    hoja = Image.new('RGB', (cols * (w + 10) + 10, filas * (hh + 10) + 10), (120, 120, 120))
    for k, im in enumerate(grupo):
        hoja.paste(im, (10 + (k % cols) * (w + 10), 10 + (k // cols) * (hh + 10)))
    destino = out / f'hoja_{rango[h] + 1:03d}.png'
    hoja.save(destino)
    print(destino)

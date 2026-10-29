"""Recupera las anotaciones vectoriales (recuadros rojos y su número) del manual anterior y las
convierte a coordenadas en píxeles de cada captura embebida.

Salida: trabajo/anotaciones_pdf.json  ->  {"p55_x221.png": [{"n": 1, "box": [x1, y1, x2, y2]}, ...]}
Las páginas de Encargado de planta (3-18) traen las marcas incrustadas en la imagen: no se procesan.
"""
import json
import os

import pymupdf

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PDF = os.path.join(RAIZ, "referencias", "Manual_Movil_Actual.pdf")
DIR_IMG = os.path.join(RAIZ, "evidencias", "referencia_pdf")


def es_rojo(c):
    return c is not None and c[0] > 0.6 and c[1] < 0.4 and c[2] < 0.4


def main():
    doc = pymupdf.open(PDF)
    salida = {}
    for pn in range(18, doc.page_count):
        page = doc[pn]
        imagenes = []
        for im in page.get_images(full=True):
            nombre = f"p{pn + 1:02d}_x{im[0]}.png"
            if not os.path.exists(os.path.join(DIR_IMG, nombre)):
                continue
            pix = pymupdf.Pixmap(doc, im[0])
            for r in page.get_image_rects(im[0]):
                imagenes.append((nombre, r, pix.width, pix.height))
        cajas = [d["rect"] for d in page.get_drawings() if d["type"] == "s" and es_rojo(d.get("color"))]
        marcas = [d["rect"] for d in page.get_drawings() if d["type"] == "f" and es_rojo(d.get("fill"))
                  and d["rect"].width < 20]
        palabras = page.get_text("words")
        for caja in cajas:
            centro = pymupdf.Point((caja.x0 + caja.x1) / 2, (caja.y0 + caja.y1) / 2)
            destino = next((i for i in imagenes if i[1].contains(centro)), None)
            if not destino:
                continue
            nombre, r, w, h = destino
            # Marca numerada: círculo rojo más cercano a la esquina superior izquierda del recuadro.
            esquina = pymupdf.Point(caja.x0, caja.y0)
            marca = min(marcas, key=lambda m: abs(m.x0 + m.width / 2 - esquina.x) + abs(m.y0 + m.height / 2 - esquina.y),
                        default=None)
            numero = None
            if marca:
                for p in palabras:
                    if pymupdf.Rect(p[:4]).intersects(marca) and p[4].isdigit():
                        numero = int(p[4])
            sx, sy = w / r.width, h / r.height
            box = [round((caja.x0 - r.x0) * sx), round((caja.y0 - r.y0) * sy),
                   round((caja.x1 - r.x0) * sx), round((caja.y1 - r.y0) * sy)]
            salida.setdefault(nombre, []).append({"n": numero, "box": box})
    for v in salida.values():
        v.sort(key=lambda a: (a["n"] or 0))
    with open(os.path.join(RAIZ, "trabajo", "anotaciones_pdf.json"), "w", encoding="utf-8") as f:
        json.dump(salida, f, ensure_ascii=False, indent=1)
    for k, v in salida.items():
        print(k, [(a["n"], a["box"]) for a in v])


if __name__ == "__main__":
    main()

"""Protege datos personales en las capturas antes de anotarlas.

- Pixela los códigos QR (detección con OpenCV y, si falla, regiones indicadas a mano).
- Pixela números de 7 o más dígitos (DNI, teléfonos, RUC) y nombres de usuario de prueba,
  localizados con Tesseract (salida TSV).

Entrada: evidencias/referencia_pdf/*.png   Salida: trabajo/protegidas/*.png + trabajo/privacidad.json
"""
import csv
import io
import json
import os
import re
import subprocess

import cv2
from PIL import Image

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ORIGEN = os.path.join(RAIZ, "evidencias", "referencia_pdf")
DESTINO = os.path.join(RAIZ, "trabajo", "protegidas")
TESS = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
USUARIO = re.compile(r"^[a-z]+\d{3,}$")  # brayan123, diana123, ...
DIGITOS = re.compile(r"\d{7,}")

# Capturas que realmente muestran un QR (fuera de ellas el detector da falsos positivos).
CON_QR = {"p29_x151.png", "p33_x167.png", "p40_x184.png", "p43_x184.png", "p60_x236.png"}

# QR que OpenCV no detecta (coordenadas en píxeles de la captura).
QR_MANUAL = {
    "p08_x94.png": [(110, 395, 520, 870)],
}

# Datos que el OCR no lee (p. ej. un DNI con el cursor de escritura pegado).
DATO_MANUAL = {
    "p32_x165.png": [(55, 745, 330, 815)],
}


def pixelar(im, caja, bloque=14):
    x1, y1, x2, y2 = [int(v) for v in caja]
    x1, y1 = max(x1, 0), max(y1, 0)
    x2, y2 = min(x2, im.width), min(y2, im.height)
    if x2 - x1 < 2 or y2 - y1 < 2:
        return
    zona = im.crop((x1, y1, x2, y2))
    peq = zona.resize((max(1, (x2 - x1) // bloque), max(1, (y2 - y1) // bloque)), Image.BILINEAR)
    im.paste(peq.resize(zona.size, Image.NEAREST), (x1, y1))


def palabras(ruta):
    tsv = subprocess.run([TESS, ruta, "stdout", "-l", "eng", "--psm", "11", "tsv"],
                         capture_output=True).stdout.decode("utf-8", "replace")
    for fila in csv.DictReader(io.StringIO(tsv), delimiter="\t", quoting=csv.QUOTE_NONE):
        t = (fila.get("text") or "").strip()
        if t:
            x, y, w, h = (int(fila[k]) for k in ("left", "top", "width", "height"))
            yield t, (x, y, x + w, y + h)


def main():
    os.makedirs(DESTINO, exist_ok=True)
    registro = {}
    det = cv2.QRCodeDetector()
    for nombre in sorted(os.listdir(ORIGEN)):
        ruta = os.path.join(ORIGEN, nombre)
        im = Image.open(ruta).convert("RGB")
        cajas = []
        ok, puntos = det.detect(cv2.imread(ruta)) if nombre in CON_QR else (False, None)
        if ok and puntos is not None:
            xs, ys = puntos[0][:, 0], puntos[0][:, 1]
            m = 0.06 * (xs.max() - xs.min())
            cajas.append(("QR", (xs.min() - m, ys.min() - m, xs.max() + m, ys.max() + m)))
        for c in QR_MANUAL.get(nombre, []):
            cajas.append(("QR", c))
        for c in DATO_MANUAL.get(nombre, []):
            cajas.append(("dato", c))
        for t, c in palabras(ruta):
            limpio = t.strip(".,:;()")
            if DIGITOS.search(limpio) or USUARIO.match(limpio):
                cajas.append((limpio, (c[0] - 4, c[1] - 4, c[2] + 4, c[3] + 4)))
        for _, c in cajas:
            pixelar(im, c, 22 if _ == "QR" else 8)
        im.save(os.path.join(DESTINO, nombre))
        registro[nombre] = [[t, [round(v) for v in c]] for t, c in cajas]
        print(nombre, [t for t, _ in cajas])
    with open(os.path.join(RAIZ, "trabajo", "privacidad.json"), "w", encoding="utf-8") as f:
        json.dump(registro, f, ensure_ascii=False, indent=1)


if __name__ == "__main__":
    main()

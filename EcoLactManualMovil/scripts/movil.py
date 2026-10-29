"""Captura de pantallas de EcoLactMobile en el emulador mediante ADB.

Uso (desde EcoLactManualMovil):
    python scripts/movil.py shot <rol> <nombre>      # captura PNG + jerarquía UI (XML)
    python scripts/movil.py tap <texto> [n]          # toca el n-ésimo elemento con ese texto/descripción
    python scripts/movil.py tapxy <x> <y>
    python scripts/movil.py back | swipe <x1> <y1> <x2> <y2> [ms] | text <cadena>
    python scripts/movil.py find <texto>             # muestra los límites del elemento

Solo lee la interfaz y envía toques; no modifica EcoLactMobile ni guarda credenciales.
"""
import os
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET

ADB = os.path.expandvars(r"%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe")
RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ORIG = os.path.join(RAIZ, "evidencias", "originales")
TMP = os.path.join(RAIZ, "trabajo", "ui_actual.xml")


def adb(*args, binario=False):
    r = subprocess.run([ADB, *args], capture_output=True)
    return r.stdout if binario else r.stdout.decode("utf-8", "replace")


def volcar():
    adb("shell", "uiautomator", "dump", "/sdcard/ui.xml")
    adb("pull", "/sdcard/ui.xml", TMP)
    return ET.parse(TMP).getroot()


def limites(nodo):
    x1, y1, x2, y2 = map(int, re.findall(r"\d+", nodo.get("bounds")))
    return x1, y1, x2, y2


def buscar(texto, n=1):
    raiz = volcar()
    hallados = [e for e in raiz.iter("node")
                if texto in (e.get("text") or "") or texto in (e.get("content-desc") or "")]
    exactos = [e for e in hallados if texto in (e.get("text"), e.get("content-desc"))]
    lista = exactos or hallados
    return limites(lista[n - 1]) if len(lista) >= n else None


def shot(rol, nombre):
    carpeta = os.path.join(ORIG, rol)
    os.makedirs(carpeta, exist_ok=True)
    time.sleep(1.2)
    png = adb("exec-out", "screencap", "-p", binario=True)
    with open(os.path.join(carpeta, nombre + ".png"), "wb") as f:
        f.write(png)
    volcar()
    with open(TMP, encoding="utf-8") as src, open(os.path.join(carpeta, nombre + ".xml"), "w", encoding="utf-8") as dst:
        dst.write(src.read())
    print("captura", rol, nombre)


def main(a):
    cmd = a[0]
    if cmd == "shot":
        shot(a[1], a[2])
    elif cmd == "tap":
        b = buscar(a[1], int(a[2]) if len(a) > 2 else 1)
        if not b:
            sys.exit(f"no encontrado: {a[1]}")
        adb("shell", "input", "tap", str((b[0] + b[2]) // 2), str((b[1] + b[3]) // 2))
        time.sleep(1.5)
        print("tap", a[1], b)
    elif cmd == "tapxy":
        adb("shell", "input", "tap", a[1], a[2])
        time.sleep(1.5)
    elif cmd == "back":
        adb("shell", "input", "keyevent", "4")
        time.sleep(1.2)
    elif cmd == "swipe":
        adb("shell", "input", "swipe", *a[1:5], a[5] if len(a) > 5 else "400")
        time.sleep(1.2)
    elif cmd == "text":
        adb("shell", "input", "text", a[1].replace(" ", "%s"))
    elif cmd == "find":
        print(buscar(a[1]))
    elif cmd == "textos":
        for e in volcar().iter("node"):
            t = e.get("text") or e.get("content-desc")
            if t:
                print(repr(t), e.get("bounds"), "clic" if e.get("clickable") == "true" else "")


if __name__ == "__main__":
    main(sys.argv[1:])

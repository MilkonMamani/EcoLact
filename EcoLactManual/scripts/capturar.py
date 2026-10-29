#!/usr/bin/env python3
"""Captura evidencias reales de EcoLactWeb con Playwright.

Uso:
  python scripts/capturar.py login                 # abre un navegador visible para iniciar sesión a mano
  python scripts/capturar.py run capturas/plan.json  # ejecuta un plan y guarda capturas + coordenadas

Reglas:
- Las credenciales nunca pasan por este script: el usuario inicia sesión manualmente.
- El perfil del navegador (cookies de sesión) se guarda FUERA del repositorio (ECOLACT_PROFILE o
  %LOCALAPPDATA%/EcoLactManualProfile).
- Cada captura registra el rectángulo real (bounding box) del control señalado, medido en el DOM.
  anotar.py usa esas coordenadas: no se escriben coordenadas a mano.
- Los selectores de "difuminar" ocultan datos personales (nombres, DNI, usuario) antes de capturar.
"""
import argparse
import json
import os
import secrets
import sys
from datetime import datetime
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[1]
APP = os.environ.get('ECOLACT_URL', 'http://127.0.0.1:8000')
PROFILE = Path(os.environ.get('ECOLACT_PROFILE', Path(os.environ.get('LOCALAPPDATA', Path.home())) / 'EcoLactManualProfile'))
VIEWPORT = {'width': 1440, 'height': 900}

# Datos personales que se difuminan siempre (nombre de usuario en la barra superior).
BLUR_SIEMPRE = ['.ecolact-sidebar-identity', '[data-dropdown-toggle="user-menu"] span.hidden']
# WebGL por software: sin esto Google Maps se queda en «Cargando mapa…» en modo headless.
ARGS_GL = ['--enable-unsafe-swiftshader', '--use-angle=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist']


def contexto(p, headless, viewport=None):
    return p.chromium.launch_persistent_context(
        str(PROFILE), headless=headless, viewport=viewport or VIEWPORT, device_scale_factor=1,
        locale='es-PE', args=ARGS_GL,
    )


def login():
    PROFILE.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        ctx = contexto(p, headless=False)
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        page.goto(APP + '/login')
        print('Inicia sesión como ADMINISTRADOR en la ventana abierta (sin límite de espera; cierra la ventana para cancelar)...', flush=True)
        page.wait_for_url('**/admin/**', timeout=0)
        print('Sesión de administrador detectada:', page.url, flush=True)
        ctx.close()


def aplicar_blur(page, selectores):
    css = ''.join(f'{s}{{filter:blur(6px)!important;}}' for s in selectores)
    if css:
        page.add_style_tag(content=css)


def bbox(page, selector):
    loc = page.locator(selector).first
    loc.wait_for(state='visible', timeout=10000)
    b = loc.bounding_box()
    return [round(b['x']), round(b['y']), round(b['x'] + b['width']), round(b['y'] + b['height'])]


def run(plan_path):
    plan_path = Path(plan_path).resolve()
    plan = json.loads(plan_path.read_text(encoding='utf-8'))
    out_dir = BASE / plan.get('salida', 'evidencias/originales')
    out_dir.mkdir(parents=True, exist_ok=True)
    registro = {'plan': plan_path.name, 'fecha': datetime.now().isoformat(timespec='seconds'), 'url_base': APP, 'capturas': []}
    blur_base = BLUR_SIEMPRE + plan.get('difuminar', [])
    reg_path = out_dir / f"{plan_path.stem}.registro.json"

    with sync_playwright() as p:
        ctx = contexto(p, headless=plan.get('headless', True), viewport=plan.get('viewport'))
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        dialogos = []
        estado = {'aceptar': False}
        # Los window.confirm nativos no aparecen en capturas: se registra su texto literal.
        page.on('dialog', lambda d: (dialogos.append(d.message), d.accept() if estado['aceptar'] else d.dismiss()))

        for paso in plan['pasos']:
            acc = paso['accion']
            if acc == 'ir':
                page.goto(APP + paso['url'], wait_until='load'); page.wait_for_timeout(paso.get('ms', 1500))
                if '/login' in page.url:
                    sys.exit('Sesión expirada: ejecuta "capturar.py login" e inicia sesión manualmente.')
            elif acc == 'clic':
                page.locator(paso['selector']).first.click()
            elif acc == 'clic_pos':
                # Clic en una posición relativa (0..1) dentro del elemento, p. ej. puntos sobre un mapa.
                b = page.locator(paso['selector']).first.bounding_box()
                page.mouse.click(b['x'] + b['width'] * paso['x'], b['y'] + b['height'] * paso['y'])
                page.wait_for_timeout(paso.get('ms', 500))
            elif acc == 'ir_atributo':
                # Navega a la URL guardada en un atributo (p. ej. data-url-comprobante de una fila).
                destino_url = page.locator(paso['selector']).first.get_attribute(paso['atributo'])
                page.goto(destino_url if destino_url.startswith('http') else APP + destino_url, wait_until='load')
                page.wait_for_timeout(paso.get('ms', 1500))
            elif acc == 'escribir_secreto':
                # Contraseña aleatoria de un solo uso para cuentas FICTICIAS: no se imprime ni se guarda.
                page.locator(paso['selector']).first.fill(secrets.token_urlsafe(12))
            elif acc == 'seleccionar':
                page.locator(paso['selector']).first.select_option(label=paso['etiqueta'])
            elif acc == 'escribir':
                page.locator(paso['selector']).first.fill(paso['texto'])
            elif acc == 'esperar':
                if 'selector' in paso:
                    page.locator(paso['selector']).first.wait_for(state=paso.get('estado', 'visible'), timeout=15000)
                else:
                    page.wait_for_timeout(paso.get('ms', 800))
            elif acc == 'dialogos':
                estado['aceptar'] = paso['aceptar']
            elif acc == 'js':
                page.evaluate(paso['codigo'])
            elif acc == 'captura':
                page.wait_for_timeout(paso.get('pausa', 600))
                aplicar_blur(page, blur_base + paso.get('difuminar', []))
                for texto in plan.get('difuminar_textos', []) + paso.get('difuminar_textos', []):
                    # Elementos hoja cuyo texto es exactamente un dato a ocultar (p. ej. el usuario conectado).
                    page.evaluate('''t => document.querySelectorAll('body *').forEach(e => {
                        if (!e.children.length && e.textContent.trim() === t) e.style.filter = 'blur(6px)'; })''', texto)
                conservar = paso.get('conservar_texto', plan.get('conservar_texto'))
                if conservar:
                    # Datos reales: se difumina toda fila de tabla/lista que no sea un registro ficticio.
                    page.evaluate('''t => document.querySelectorAll('tbody tr, [role=option], li[data-fila]').forEach(f => {
                        if (!f.innerText.includes(t)) f.style.filter = 'blur(6px)'; })''', conservar)
                destino = out_dir / f"{paso['nombre']}.png"
                objetivos = {}
                for clave, sel in paso.get('medir', {}).items():
                    objetivos[clave] = bbox(page, sel)
                page.screenshot(path=str(destino), full_page=paso.get('pagina_completa', False))
                registro['capturas'].append({
                    'nombre': paso['nombre'],
                    'archivo': str(destino.relative_to(BASE)).replace('\\', '/'),
                    'url': page.url.replace(APP, ''),
                    'descripcion': paso.get('descripcion', ''),
                    'objetivos': objetivos,
                    'textos_visibles': [page.locator(s).first.inner_text().strip() for s in paso.get('leer', [])],
                    'hora': datetime.now().isoformat(timespec='seconds'),
                })
                print('OK', destino.name, objetivos, flush=True)
                # Registro incremental: si el plan se interrumpe, lo ya capturado conserva sus medidas.
                reg_path.write_text(json.dumps(registro, ensure_ascii=False, indent=2), encoding='utf-8')
            else:
                sys.exit(f'Acción desconocida: {acc}')

        registro['dialogos'] = dialogos
        ctx.close()

    reg_path.write_text(json.dumps(registro, ensure_ascii=False, indent=2), encoding='utf-8')
    print('Registro:', reg_path)


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest='cmd', required=True)
    sub.add_parser('login')
    r = sub.add_parser('run')
    r.add_argument('plan')
    a = ap.parse_args()
    login() if a.cmd == 'login' else run(a.plan)

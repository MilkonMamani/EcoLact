"""Crea únicamente marcadores vacíos; nunca sobrescribe capturas aportadas."""
from pathlib import Path
import json
from PIL import Image, ImageDraw, ImageFont

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root/'tools/arquitectura-manifest.json').read_text(encoding='utf-8'))
dest = root/'docs/assets/images/arquitectura/codigo'
dest.mkdir(parents=True, exist_ok=True)
font = lambda size: ImageFont.truetype('C:/Windows/Fonts/arial.ttf', size)
guide = ['# Capturas pendientes de Arquitectura', '', 'Reemplazar cada PNG en `docs/assets/images/arquitectura/codigo/` conservando su nombre. No es necesario modificar Markdown. Los archivos actuales son marcadores sin código. No se han generado capturas falsas.', '', 'Los rangos sugeridos corresponden al código local revisado; ampliar el encuadre si hace falta para comprender el fragmento. No incluir credenciales ni datos de sesiones.', '', '| Archivo | Fuente del repositorio | Líneas sugeridas |', '| --- | --- | --- |']
for c in manifest['captures']:
    target = dest/c['file']
    if not target.exists():
        im = Image.new('RGB',(1200,440),'#f4f8ff')
        d = ImageDraw.Draw(im)
        d.rounded_rectangle((20,20,1180,420),radius=22,outline='#bdcfe8',width=3)
        d.text((600,120),'Captura de código pendiente',font=font(44),fill='#174b9a',anchor='mm')
        d.text((600,205),c['file'],font=font(29),fill='#334e70',anchor='mm')
        d.text((600,290),'Reemplazar este PNG por la captura real del proyecto.',font=font(26),fill='#536982',anchor='mm')
        d.text((600,340),'Marcador temporal · No contiene código ni evidencia ejecutada',font=font(23),fill='#64758b',anchor='mm')
        im.save(target)
    guide.append(f"| `{c['file']}` | `{c['source']}` | {c['start']}–{c['end']} |")
(root/'CAPTURAS-ARQUITECTURA.md').write_text('\n'.join(guide)+'\n',encoding='utf-8')
print(f"{len(manifest['captures'])} posiciones verificadas; guía guardada.")

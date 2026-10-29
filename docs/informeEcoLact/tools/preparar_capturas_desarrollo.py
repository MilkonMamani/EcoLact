"""Marcadores explícitos, sin código ni terminal ficticia. Conserva imágenes existentes."""
from pathlib import Path
import json
from PIL import Image, ImageDraw, ImageFont
root=Path(__file__).resolve().parents[1]
m=json.loads((root/'tools/desarrollo-manifest.json').read_text(encoding='utf8'))
folder=root/'docs/assets/images/desarrollo'
folder.mkdir(parents=True,exist_ok=True)
font=lambda s:ImageFont.truetype('C:/Windows/Fonts/arial.ttf',s)
lines=['# Capturas de Desarrollo','','Sustituye cada PNG en `docs/assets/images/desarrollo/` por tu captura real, conservando el nombre. Después ejecuta `python -m mkdocs build --clean` desde informeEcoLact (o deja que mkdocs serve regenere el sitio). No necesitas editar Markdown. Si mantienes abierto el navegador, recarga para evitar su caché.','','Los PNG actuales son marcadores de texto, no evidencias de herramientas ejecutadas. Oculta contraseñas, tokens, claves y datos personales. El script preparador no sobrescribe ninguna imagen existente.','','| Archivo | Qué debe mostrar | Herramienta |','| --- | --- | --- |']
for c in m['captures']:
 p=folder/c['file']
 if not p.exists():
  im=Image.new('RGB',(1200,330),'#f8fbff');d=ImageDraw.Draw(im)
  d.text((600,85),'Captura pendiente',font=font(44),fill='#1759c2',anchor='mm')
  d.text((600,160),c['file'],font=font(29),fill='#103b86',anchor='mm')
  d.text((600,235),'Espacio reservado para una imagen real del proyecto',font=font(26),fill='#526880',anchor='mm')
  im.save(p)
 lines.append(f"| `{c['file']}` | {c['description']} | {c['tool']} |")
(root/'CAPTURAS-DESARROLLO.md').write_text('\n'.join(lines)+'\n',encoding='utf8')
print(f"{len(m['captures'])} marcadores preparados.")

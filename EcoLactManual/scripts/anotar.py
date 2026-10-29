#!/usr/bin/env python3
"""Resalta controles reales en capturas; no altera originales. Uso: python scripts/anotar.py ejemplo/zonas.json"""
import argparse, json
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

BASE = Path(__file__).resolve().parents[1]

def font(size, bold=True):
    paths = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        'C:/Windows/Fonts/arialbd.ttf'
    ] if bold else ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf','C:/Windows/Fonts/arial.ttf']
    for path in paths:
        try: return ImageFont.truetype(path,size)
        except OSError: pass
    return ImageFont.load_default()

def annotate(datafile):
    datafile=Path(datafile).resolve()
    d=json.loads(datafile.read_text(encoding='utf-8'))
    for st in d['pasos']:
        src = datafile.parent/st['imagen_original']
        dst = datafile.parent/st['imagen_anotada']
        dst.parent.mkdir(parents=True,exist_ok=True)
        original=Image.open(src).convert('RGBA')
        W,H=original.size
        highlight=Image.new('RGBA',(W,H),(18,27,38,90))
        combined=Image.alpha_composite(original,highlight)
        x1,y1,x2,y2=st['rectangulo']; pad=8
        combined.paste(original.crop((max(0,x1-pad),max(0,y1-pad),min(W,x2+pad),min(H,y2+pad))), (max(0,x1-pad),max(0,y1-pad)))
        draw=ImageDraw.Draw(combined)
        red='#F44336'; draw.rounded_rectangle((x1-pad,y1-pad,x2+pad,y2+pad), radius=8,outline=red,width=7)
        lx,ly=st['etiqueta_xy']; label=st['texto_etiqueta']; f=font(24)
        label_w=int(draw.textbbox((0,0),label,font=f)[2])+38
        label_h=50
        lx=min(lx,W-label_w-12);ly=min(ly,H-label_h-12)
        draw.rounded_rectangle((lx,ly,lx+label_w,ly+label_h),radius=11,fill='#B91C1C',outline='#FFFFFF',width=2)
        draw.text((lx+19,ly+10),label,font=f,fill='white')
        # Flecha desde el borde más cercano de la etiqueta hacia el objetivo
        tx=(x1+x2)/2;ty=(y1+y2)/2
        start=(lx,ly+label_h/2) if lx>tx else (lx+label_w,ly+label_h/2)
        end=(x2+11,ty) if lx>tx else (x1-11,ty)
        draw.line((start,end),fill=red,width=6)
        import math
        angle=math.atan2(end[1]-start[1],end[0]-start[0]);s=16
        a=(end[0]-s*math.cos(angle-0.6),end[1]-s*math.sin(angle-0.6))
        b=(end[0]-s*math.cos(angle+0.6),end[1]-s*math.sin(angle+0.6))
        draw.polygon((end,a,b),fill=red)
        crop=st.get('recorte',[0,0,W,H]); combined=combined.crop(tuple(crop))
        combined.convert('RGB').save(dst,optimize=True)
        print(f'OK paso {st["numero"]} -> {dst}')

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('manifest',help='JSON con pasos, rectángulos y recortes');args=p.parse_args();annotate(args.manifest)

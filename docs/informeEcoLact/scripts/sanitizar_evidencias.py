"""Redacta secretos en copias publicables; preserva originales fuera del sitio."""
from pathlib import Path
import datetime as dt
import hashlib
import json
import os
import re
import tempfile

ROOT=Path(__file__).resolve().parents[3]
DOC=ROOT/'docs/informeEcoLact/docs'
BASE=DOC/'assets/evidencias/pruebas'
private=Path(tempfile.gettempdir())/'ecolact-qa-originales-20261029'
private.mkdir(exist_ok=True)
secrets=set()
for file in (ROOT/'EcoLactWeb').glob('.env*'):
    for line in file.read_text(encoding='utf-8',errors='replace').splitlines():
        if '=' not in line or line.startswith('#'): continue
        key,value=line.split('=',1)
        value=value.strip().strip('\"\'')
        if re.search(r'KEY|SECRET|PASSWORD|TOKEN',key,re.I) and len(value)>=8:
            secrets.add(value)
inventory=BASE/'resumen/20261029-qa02/casos.json'
if inventory.exists():
    for c in json.loads(inventory.read_text(encoding='utf-8')):
        secrets.update(re.findall(r'(?:name="csrf-token" content="|name="_token" value=")([^"<>]+)',c['detail']))
paths=[p for p in BASE.rglob('*') if p.suffix in ('.log','.xml','.json','.html','.csv') and p.name!='redacciones.json']
changes=[]
for p in paths:
    raw=p.read_bytes()
    # Replace as bytes so no original encoding/newline or trace is lost.
    sanitized=raw
    for secret in secrets:
        sanitized=sanitized.replace(secret.encode(),b'[REDACTADO]')
    sanitized=re.sub(rb'AIza[0-9A-Za-z_-]{30,}',b'[REDACTADO]',sanitized)
    if sanitized==raw: continue
    relative=p.relative_to(BASE)
    target=private/relative
    target.parent.mkdir(parents=True,exist_ok=True)
    if not target.exists(): target.write_bytes(raw)
    p.write_bytes(sanitized)
    changes.append(dict(file=relative.as_posix(),original_sha256=hashlib.sha256(raw).hexdigest(),public_sha256=hashlib.sha256(sanitized).hexdigest()))
for p in BASE.rglob('*.json'):
    if p.name=='redacciones.json': continue
    try: data=json.loads(p.read_text(encoding='utf-8'))
    except (ValueError,UnicodeError): continue
    if isinstance(data,dict) and 'log' in data and 'sha256' in data:
        log=p.parent/data['log']
        digest=hashlib.sha256(log.read_bytes()).hexdigest()
        if digest!=data['sha256']:
            data['original_sha256']=data['sha256']
            data['sha256']=digest
            data['redaction']='Secretos redactados; salida restante completa. Hash sha256 corresponde a copia publicable.'
            p.write_text(json.dumps(data,indent=2,ensure_ascii=False),encoding='utf-8')
report=BASE/'resumen/20261029-qa02/redacciones.json'
previous=json.loads(report.read_text(encoding='utf-8')) if report.exists() else []
report.write_text(json.dumps(previous+changes,indent=2),encoding='utf-8')
print(f'Archivos saneados: {len(changes)}; originales privados fuera del sitio: {private}')

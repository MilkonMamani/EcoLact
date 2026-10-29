"""Verifica integridad, deduplicación, privacidad y fuentes sin imprimir secretos."""
from pathlib import Path
import hashlib
import json
import re
import shutil
import datetime as dt

ROOT=Path(__file__).resolve().parents[3]
PROJECT=ROOT/'docs/informeEcoLact'
BASE=PROJECT/'docs/assets/evidencias/pruebas'
SUMMARY=BASE/'resumen/20261029-qa02'
hashes=json.loads((SUMMARY/'fuentes-sha256.json').read_text(encoding='utf-8'))
changed=[name for name,digest in hashes.items() if not (ROOT/name).exists() or hashlib.sha256((ROOT/name).read_bytes()).hexdigest()!=digest]
source_changes=[dict(file=name,current_sha256=hashlib.sha256((ROOT/name).read_bytes()).hexdigest(),modified_at=dt.datetime.fromtimestamp((ROOT/name).stat().st_mtime,dt.timezone(dt.timedelta(hours=-5))).isoformat()) for name in changed if (ROOT/name).exists()]
(SUMMARY/'cambios-posteriores.json').write_text(json.dumps(source_changes,indent=2),encoding='utf-8')
secrets=set()
for file in (ROOT/'EcoLactWeb').glob('.env*'):
    for line in file.read_text(encoding='utf-8',errors='replace').splitlines():
        if '=' not in line or line.startswith('#'):continue
        key,value=line.split('=',1);value=value.strip().strip('\"\'')
        if re.search(r'KEY|SECRET|PASSWORD|TOKEN',key,re.I) and len(value)>=8:secrets.add(value.encode())
for p in BASE.rglob('*'):
    if not p.is_file():continue
    raw=p.read_bytes()
    assert not any(s in raw for s in secrets), f'Secreto detectado en {p.name}'
    assert not re.search(rb'AIza[0-9A-Za-z_-]{30,}',raw), f'Clave detectada en {p.name}'
    if p.suffix=='.json':
        data=json.loads(raw)
        if isinstance(data,dict) and 'log' in data and 'sha256' in data:
            assert hashlib.sha256((p.parent/data['log']).read_bytes()).hexdigest()==data['sha256']
for name in ('unitarias','funcionales'):
    lines=(BASE/'backend/20261029-qa02'/f'{name}.log').read_text(encoding='utf-8').splitlines()
    record=next(json.loads(line) for line in lines if line.startswith('{"tool"'))
    stats=json.loads((SUMMARY/'resultados.json').read_text())[{'unitarias':'unit','funcionales':'feature'}[name]]
    assert record['tests']==stats['total'] and record['passed']==stats['passed']
validation=PROJECT/'validacion-pruebas/20261029-qa02/resultados.json'
shutil.copy2(validation,SUMMARY/'validacion-responsive.json')
views=json.loads(validation.read_text(encoding='utf-8'))
assert len(views)==15 and all(not v['overflow'] and not v['brokenLinks'] and not v['errors'] and all(i['ok'] for i in v['images']) for v in views)
result=dict(source_files_verified=len(hashes),source_files_changed=changed,views_verified=len(views),secrets_detected=0,log_hashes_verified=True,runner_and_junit_counts_match=True)
(SUMMARY/'auditoria-final.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
manifest={p.relative_to(BASE).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in BASE.rglob('*') if p.is_file() and p.name!='manifest-sha256.json'}
(SUMMARY/'manifest-sha256.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
# Los logs de build terminan después de que MkDocs copia assets: sincronizar su versión final.
shutil.copytree(BASE,PROJECT/'site/assets/evidencias/pruebas',dirs_exist_ok=True)
print(json.dumps(result,indent=2))

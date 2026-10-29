"""Captura comandos reales sin alterar código ni bases de datos del proyecto."""
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import platform
import subprocess
import sys
import tempfile
import time
import xml.etree.ElementTree as ET

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

ROOT = Path(__file__).resolve().parents[3]
DOC = ROOT / 'docs/informeEcoLact/docs'
TZ = dt.timezone(dt.timedelta(hours=-5))
RUN = sys.argv[1]
GROUP = sys.argv[2]
OUT = DOC / 'assets/evidencias/pruebas' / GROUP / RUN
OUT.mkdir(parents=True, exist_ok=True)
env = os.environ.copy()
if GROUP in ('backend', 'integracion'):
    xml = ET.parse(ROOT / 'EcoLactWeb/phpunit.xml')
    values = {e.attrib['name']: e.attrib['value'] for e in xml.findall('.//php/env')}
    assert values['DB_CONNECTION'] == 'sqlite' and values['DB_DATABASE'] == ':memory:'
    assert not (ROOT / 'EcoLactWeb/bootstrap/cache/config.php').exists(), 'Cached configuration: stop'
    env.update(values)
    storage = Path(tempfile.gettempdir()) / ('ecolact-qa-' + RUN + '-' + sys.argv[3])
    for folder in ('app/private', 'app/public', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'):
        (storage / folder).mkdir(parents=True, exist_ok=True)
    env.update(ECOLACT_MYSQL_SCHEMA_TEST='0', DB_URL='', DB_HOST='127.0.0.1', DB_PORT='1',
               DB_USERNAME='qa_disabled', DB_PASSWORD='', LARAVEL_STORAGE_PATH=str(storage),
               FILESYSTEM_DISK='local', LOG_CHANNEL='stderr', APP_URL='http://localhost')

def run(name, args, cwd):
    log = OUT / (name + '.log')
    assert not log.exists(), f'Historical execution exists: {log}'
    started = dt.datetime.now(TZ).isoformat()
    before = time.perf_counter()
    with log.open('wb') as output:
        p = subprocess.run(args, cwd=cwd, env=env, stdout=output, stderr=subprocess.STDOUT)
    metadata = dict(command=subprocess.list2cmdline(args), cwd=str(cwd), started=started,
                    ended=dt.datetime.now(TZ).isoformat(), duration_seconds=round(time.perf_counter()-before, 3),
                    exit_code=p.returncode, log=log.name, sha256=hashlib.sha256(log.read_bytes()).hexdigest())
    (OUT / (name + '.json')).write_text(json.dumps(metadata, indent=2, ensure_ascii=False), encoding='utf-8')
    print(json.dumps(metadata, ensure_ascii=False), flush=True)
    print(log.read_text(encoding='utf-8', errors='replace')[-2200:], flush=True)
    return p.returncode

web = ROOT / 'EcoLactWeb'
mobile = ROOT / 'EcoLactMobile'
mode = sys.argv[3]
if mode == 'entorno':
    for name, cmd, cwd in [('php', ['php','-v'], web), ('php-modules',['php','-m'],web),
        ('composer',['cmd','/c','composer','--version'],web), ('dependencias',['cmd','/c','composer','show','--direct'],web),
        ('java',['java','-version'],mobile), ('gradle',['cmd','/c','gradlew.bat','--version'],mobile),
        ('git-estado',['git','status','--short'],ROOT),('git-commit',['git','rev-parse','HEAD'],ROOT)]:
        run(name,cmd,cwd)
    (OUT/'sistema.json').write_text(json.dumps(dict(os=platform.platform(), python=platform.python_version(), timezone='America/Lima'),indent=2),encoding='utf-8')
elif mode == 'mkdocs':
    sys.exit(run('mkdocs-build',['mkdocs','build','--clean'],ROOT/'docs/informeEcoLact'))
elif mode == 'mobile':
    sys.exit(run('mobile-android-host',['cmd','/c','gradlew.bat',':shared:testAndroidHostTest','--console=plain','--rerun-tasks'],mobile))
else:
    selections = {
        'smoke':['tests/Feature/Application/Acopio/RegistrarEntregaTest.php'],
        'unitarias':['--testsuite=Unit'],
        'funcionales':['--testsuite=Feature'],
        'integracion':['--filter=EntregaAlimentaLecheTest|SincronizacionAutomaticaTest|CalidadNoBloqueaProduccionTest|IniciarProduccionTest|CajaTest|AcopiadorMovilApiTest|RoleMiddlewareTest'],
    }
    sys.exit(run(mode,['php','artisan','test','--colors=never',*selections[mode], '--log-junit='+str(OUT/(mode+'.xml'))],web))

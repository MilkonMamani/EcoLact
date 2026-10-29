/* Requiere servidor estático de informeEcoLact en http://127.0.0.1:8766. */
const fs=require('fs');
const path=require('path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const out=path.join(root,'validacion-desarrollo');
fs.mkdirSync(out,{recursive:true});
const manifest=JSON.parse(fs.readFileSync(path.join(__dirname,'desarrollo-manifest.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 const results=[];
 try {
  const page=await browser.newPage();
  for(const [name,width,height] of [['escritorio',1440,1000],['tablet',768,1024],['movil',390,844]]) {
   await page.setViewportSize({width,height});
   for(const entry of manifest.pages) {
    const url='http://127.0.0.1:8766/site/desarrollo/'+(entry.slug==='index'?'':entry.slug+'/');
    const errors=[];
    const logError=e=>errors.push(e.message);
    page.on('pageerror',logError);
    await page.goto(url,{waitUntil:'networkidle'});
    await page.locator('.ec-dev-capture img').first().waitFor();
    await page.locator('main img').evaluateAll(async images=>{for(const image of images)image.loading='eager';await Promise.all(images.map(image=>image.decode().catch(()=>{})));});
    const state=await page.evaluate(()=>{
     const scope=document.querySelector('.ec-desarrollo');
     const images=[...scope.querySelectorAll('img')].map(i=>({src:i.getAttribute('src'),ok:i.complete&&i.naturalWidth>0}));
     const rect=scope.getBoundingClientRect();
     const spill=[...scope.querySelectorAll('*')].filter(e=>{
      if(e.closest('details:not([open])')||e.closest('.ec-dev-command-table')||e.closest('pre'))return false;
      const r=e.getBoundingClientRect();return r.width>0&&(r.right>innerWidth+1||r.left< -1);
     }).map(e=>e.tagName+'.'+e.className);
     return {images,scopeWidth:rect.width,viewport:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1,spill,
      title:scope.querySelector('h1')?.textContent,icons:scope.querySelectorAll('.twemoji svg').length,
      links:[...scope.querySelectorAll('a[href]')].map(a=>a.href)};
    });
    const links=[];
    for(const url of [...new Set(state.links)]) {
     if(!url.startsWith('http://127.0.0.1:8766/'))continue;
     const response=await page.request.get(url);if(!response.ok())links.push({url,status:response.status()});
    }
    await page.screenshot({path:path.join(out,entry.slug+'-'+name+'.png'),fullPage:true});
    page.off('pageerror',logError);
    const result={page:entry.slug,viewportName:name,...state,brokenLinks:links,errors};
    results.push(result);
    console.log(entry.slug+' / '+name+': '+(state.images.every(i=>i.ok)&&!state.overflow&&!state.spill.length&&!links.length&&!errors.length?'OK':'REVISAR'));
   }
  }
  await page.setViewportSize({width:1440,height:1000});
  for(const route of ['', 'sistema/', 'sistema/problema-solucion/', 'sistema/actores-roles/', 'sistema/modulos/', 'sistema/reglas-negocio/', 'sistema/alcance/', 'flujos/', 'arquitectura/', 'pruebas/']) {
   const r=await page.goto('http://127.0.0.1:8766/site/'+route,{waitUntil:'networkidle'});
   await page.locator('main img').evaluateAll(async images=>{for(const image of images)image.loading='eager';await Promise.all(images.map(image=>image.decode().catch(()=>{})));});
   const broken=await page.locator('main img').evaluateAll(list=>list.filter(i=>!i.complete||i.naturalWidth===0).map(i=>i.src));
   results.push({regression:route||'Inicio',status:r.status(),brokenImages:broken});
   await page.screenshot({path:path.join(out,'regresion-'+(route.replaceAll('/','-')||'inicio')+'.png'),fullPage:true});
  }
  fs.writeFileSync(path.join(out,'resultados.json'),JSON.stringify(results,null,2));
  const bad=results.filter(r=>r.regression?r.status!==200||r.brokenImages.length:r.overflow||r.spill.length||r.brokenLinks.length||r.errors.length||r.images.some(i=>!i.ok));
  console.log('Resultados: '+results.length+'; fallos: '+bad.length);
  if(bad.length)process.exitCode=1;
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});





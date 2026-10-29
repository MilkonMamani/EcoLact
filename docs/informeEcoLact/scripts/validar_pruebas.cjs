/* Servidor local: python -m http.server 8767 --bind 127.0.0.1 (informeEcoLact). */
const fs=require('fs');
const path=require('path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const run='20261029-qa02';
const base='http://127.0.0.1:8767/';
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1120}});
  if(process.argv[2]==='capturas'){
   for(const [group,name] of [['backend','unitarias'],['backend','funcionales'],['mobile','mobile-android-host'],['integracion','integracion']]){
    const folder=path.join(root,'docs/assets/images/pruebas',group);
    fs.mkdirSync(folder,{recursive:true});
    const response=await page.goto(`${base}docs/assets/evidencias/pruebas/${group}/${run}/${name}.html`,{waitUntil:'networkidle'});
    if(!response.ok())throw Error('Reporte no disponible');
    await page.screenshot({path:path.join(folder,`${run}-${name}.png`)});
    console.log('Captura real de reporte HTML: '+name);
   }
  }else{
   const out=path.join(root,'validacion-pruebas',run);
   fs.mkdirSync(out,{recursive:true});
   const results=[];
   for(const [device,width,height] of [['escritorio',1440,1000],['tablet',768,1024],['movil',390,844]]){
    await page.setViewportSize({width,height});
    for(const slug of ['index','backend','mobile','integracion','evidencias']){
     const errors=[];const onerror=e=>errors.push(e.message);page.on('pageerror',onerror);
     const response=await page.goto(`${base}site/pruebas/${slug==='index'?'':slug+'/'}`,{waitUntil:'networkidle'});
     await page.locator('.ec-pruebas').waitFor();
     await page.locator('.ec-pruebas img').evaluateAll(async images=>{for(const i of images)i.loading='eager';await Promise.all(images.map(i=>i.decode().catch(()=>{})));});
     const state=await page.evaluate(()=>{
      const scope=document.querySelector('.ec-pruebas');
      return {title:scope.querySelector('h1')?.textContent,overflow:document.documentElement.scrollWidth>innerWidth+1,
       images:[...scope.querySelectorAll('img')].map(i=>({src:i.getAttribute('src'),ok:i.complete&&i.naturalWidth>0})),
       links:[...scope.querySelectorAll('a[href]')].map(a=>a.href),
       navigation:[...document.querySelectorAll('.md-tabs__link')].map(a=>a.textContent.trim()),
       rawMarkdown:scope.textContent.includes('|---|')};
     });
     const brokenLinks=[];
     for(const url of [...new Set(state.links)]){
      if(!url.startsWith(base))continue;
      const r=await page.request.get(url);if(!r.ok())brokenLinks.push({url,status:r.status()});
      const hash=new URL(url).hash;
      if(hash&&url.split('#')[0]===page.url().split('#')[0]){
       if(!await page.locator('[id="'+decodeURIComponent(hash.slice(1))+'"]').count())brokenLinks.push({url,status:'anchor missing'});
      }
     }
     await page.screenshot({path:path.join(out,`${slug}-${device}.png`),fullPage:true});
     page.off('pageerror',onerror);
     const result={slug,device,status:response.status(),...state,errors,brokenLinks};results.push(result);
     console.log(`${slug} / ${device}: ${!state.overflow&&!state.rawMarkdown&&!errors.length&&!brokenLinks.length&&state.images.every(i=>i.ok)?'OK':'REVISAR'}`);
    }
   }
   fs.writeFileSync(path.join(out,'resultados.json'),JSON.stringify(results,null,2));
   const failed=results.filter(r=>r.status!==200||r.overflow||r.rawMarkdown||r.errors.length||r.brokenLinks.length||r.images.some(i=>!i.ok)||r.navigation.length!==6);
   console.log(`${results.length} vistas; ${failed.length} fallos`);
   if(failed.length)process.exitCode=1;
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

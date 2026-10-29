const fs=require('fs'),path=require('path');
const {chromium}=require('playwright');
(async()=>{const browser=await chromium.launch({headless:true,channel:'msedge'});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const manifest=JSON.parse(fs.readFileSync('tools/arquitectura-manifest.json','utf8'));
 const results=[];
 for(const entry of manifest.pages){
  const r=await page.goto('http://127.0.0.1:8767/arquitectura/'+(entry.slug==='index'?'':entry.slug+'/'));
  await page.locator('.ec-arch-diagram img').first().waitFor();
  const count=await page.locator('.ec-arch-evidence-image img').count();
  results.push({page:entry.slug,status:r.status(),captures:count,expected:entry.captures.length});
 }
 await page.goto('http://127.0.0.1:8767/arquitectura/');
 await page.locator('.ec-arch-diagram').screenshot({path:'validacion-arquitectura/c4-detalle.png'});
 fs.writeFileSync('validacion-arquitectura/servidor-raiz.json',JSON.stringify(results,null,2));
 console.log(results);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

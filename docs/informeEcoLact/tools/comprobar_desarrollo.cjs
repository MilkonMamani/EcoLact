const fs=require('fs');
const {chromium}=require('playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},permissions:['clipboard-read','clipboard-write']});
  const page=await context.newPage();
  const manifest=JSON.parse(fs.readFileSync('tools/desarrollo-manifest.json','utf8'));
  const results=[];
  let count=0;
  for(const p of manifest.pages){
   const response=await page.goto('http://127.0.0.1:8767/desarrollo/'+(p.slug==='index'?'':p.slug+'/'),{waitUntil:'networkidle'});
   const captureCount=await page.locator('.ec-dev-capture img').count();count+=captureCount;
   const expected=await page.locator('.ec-dev-command pre code').first().innerText();
   await page.locator('.ec-dev-command [data-md-type="copy"]').first().click();
   const copied=await page.evaluate(()=>navigator.clipboard.readText());
   const sameRow=await page.locator('.ec-dev-flow-item').evaluateAll(items=>!items.length||items.every(i=>Math.abs(i.getBoundingClientRect().top-items[0].getBoundingClientRect().top)<2));
   const r={page:p.slug,status:response.status(),captureCount,copyCorrect:copied.replaceAll('\r\n','\n').trim()===expected.trim(),desktopFlowInOneRow:sameRow};results.push(r);
   console.log(r);
  }
  const summary={results,totalCaptures:count,expectedCaptures:manifest.captures.length};
  fs.writeFileSync('validacion-desarrollo/interaccion.json',JSON.stringify(summary,null,2));
  if(count!==manifest.captures.length||results.some(r=>r.status!==200||!r.copyCorrect||!r.desktopFlowInOneRow))process.exitCode=1;
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});


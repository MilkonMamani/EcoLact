/* Uso: NODE_PATH=<paquetes con playwright> node tools/renderizar_flujos.cjs
 * Mermaid 11.12.0 se utiliza solo al construir recursos; el sitio sirve SVG locales.
 */
const fs = require('fs');
const path = require('path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
(async () => {
  const browser = await chromium.launch({headless:true,channel:'msedge'});
  try {
    const page = await browser.newPage({viewport:{width:1600,height:1200}});
    await page.goto('http://127.0.0.1:8766/');
    await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/mermaid@11.12.0/dist/mermaid.min.js'});
    await page.evaluate(() => mermaid.initialize({startOnLoad:false,securityLevel:'strict',theme:'base',
      themeVariables:{fontFamily:'Arial, sans-serif',fontSize:'16px',primaryColor:'#eaf3ff',primaryTextColor:'#123d91',primaryBorderColor:'#7ca5df',lineColor:'#52749d',secondaryColor:'#e6f7ed',tertiaryColor:'#fff3df',clusterBkg:'#f8fbff',clusterBorder:'#cdddf0'},
      flowchart:{htmlLabels:false,curve:'basis',nodeSpacing:22,rankSpacing:35,padding:14},
      sequence:{useMaxWidth:true,wrap:true,actorMargin:35,messageMargin:35}}));
    const manifest=JSON.parse(fs.readFileSync(path.join(__dirname,'flujos-manifest.json'),'utf8'));
    for(let i=0;i<manifest.length;i++) {
      const slug=manifest[i].slug;
      const base=path.join(root,'docs/assets/diagrams/flujos',slug);
      const source=fs.readFileSync(base+'.mmd','utf8');
      const svg=await page.evaluate(async ({source,i})=>(await mermaid.render('ecflow'+i,source)).svg,{source,i});
      fs.writeFileSync(base+'.svg',svg);
      console.log('SVG OK: '+slug);
    }
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

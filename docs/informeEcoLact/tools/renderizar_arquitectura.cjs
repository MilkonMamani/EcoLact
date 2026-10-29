/* Uso: NODE_PATH=<paquetes con playwright> node tools/renderizar_arquitectura.cjs
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
      c4:{diagramMarginX:10,diagramMarginY:10,personFontFamily:'Arial',containerFontFamily:'Arial',container_dbFontFamily:'Arial',boundaryFontFamily:'Arial',messageFontFamily:'Arial',personFontSize:18,containerFontSize:18,container_dbFontSize:18,boundaryFontSize:18,messageFontSize:16,c4ShapeMargin:65},
      flowchart:{htmlLabels:false,curve:'basis',nodeSpacing:22,rankSpacing:35,padding:14},
      sequence:{useMaxWidth:true,wrap:true,actorMargin:35,messageMargin:35}}));
    const manifest=JSON.parse(fs.readFileSync(path.join(__dirname,'arquitectura-manifest.json'),'utf8'));
    for(let i=0;i<manifest.diagrams.length;i++) {
      const slug=manifest.diagrams[i];
      const base=path.join(root,'docs/assets/diagrams/arquitectura',slug);
      const source=fs.readFileSync(base+'.mmd','utf8');
      const svg=await page.evaluate(async ({source,i})=>(await mermaid.render('ecarch'+i,source)).svg,{source,i});
      // C4 emits xlink:href for person icons; standalone SVG needs its namespace.
      const standalone=svg.includes('xmlns:xlink=')?svg:svg.replace('<svg ', '<svg xmlns:xlink="http://www.w3.org/1999/xlink" ');
      fs.writeFileSync(base+'.svg',standalone);
      console.log('SVG OK: '+slug);
    }
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});


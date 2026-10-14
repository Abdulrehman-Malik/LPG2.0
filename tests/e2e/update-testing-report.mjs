import fs from 'node:fs';
const reportPath=process.env.PLAYWRIGHT_JSON_REPORT||'writable/runtime-qa/playwright-results.json';
const docPath=process.env.TESTING_MD_PATH||'TESTING.md';
const report=JSON.parse(fs.readFileSync(reportPath,'utf8'));
const tests=[];
for(const suite of report.suites||[]) for(const spec of suite.specs||[]){
  const result=spec.tests?.[0]?.results?.at(-1);
  tests.push({id:spec.title.match(/^(E2E-\d+)/)?.[1]||spec.title,title:spec.title.replace(/^E2E-\d+\s*/,''),status:result?.status==='passed'?'PASS':result?.status==='skipped'?'SKIP':'FAIL',duration:result?.duration??0,error:result?.error?.message||''});
}
tests.sort((a,b)=>a.id.localeCompare(b.id,undefined,{numeric:true}));
const passed=tests.filter(t=>t.status==='PASS').length,failed=tests.filter(t=>t.status==='FAIL').length,skipped=tests.filter(t=>t.status==='SKIP').length;
const rows=tests.map(t=>'| '+t.id+' | '+t.title+' | '+t.status+' | '+t.duration+' ms | '+t.error.replace(/\|/g,'/').replace(/\n/g,' ').slice(0,300)+' |').join('\n');
const section='## Automated Playwright Runtime QA — Live Status\n\n**Purpose:** Executable browser/E2E business-flow tests. Status is generated from the latest Runtime QA Playwright JSON report.\n\n**Last report:** '+new Date().toISOString()+'  \n**Summary:** **'+passed+' PASS / '+failed+' FAIL / '+skipped+' SKIP / '+tests.length+' TOTAL**\n\n| ID | Executable Test Case | QA Runtime Status | Duration | Failure Evidence |\n|---|---|---:|---:|---|\n'+(rows||'| - | No Playwright results found | NOT RUN | - | - |')+'\n\n### Business coverage\n- Login and dashboard smoke\n- POS gas-sale screen\n- **Actual gas sale posting**\n- Sale History and inventory movement\n- **Inventory gas-stock reduction after sale**\n- POS validation without page refresh/posting\n- Configuration navigation regression\n\n> **PASS means the browser actually executed the flow in the isolated Runtime QA database.**\n\n';
let doc=fs.readFileSync(docPath,'utf8');
const start='## Automated Playwright Runtime QA — Live Status';
const idx=doc.indexOf(start);
if(idx>=0){const next=doc.indexOf('\n## ',idx+start.length);doc=doc.slice(0,idx)+section+(next>=0?doc.slice(next+1):'');}
else doc+='\n\n'+section;
fs.writeFileSync(docPath,doc);


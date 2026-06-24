(function(){
const E=window.LABEL_PRINTER_ENDPOINT||'products/barcode_labels_generate.php';
const T=window.CSRF_TOKEN||'';
const $=id=>document.getElementById(id);
let P=[],S=new Set();

function load(){
  const L=$('productList');if(!L)return;
  L.innerHTML='<div class=p-8><div class="inline-flex items-center gap-2 text-slate-300"><div class="w-5 h-5 border-2 border-amber-500/50 border-t-amber-500 rounded-full animate-spin"></div><span>Loading...</span></div></div>';
  const F=new FormData();F.append('action','get_products');F.append('csrf_token',T);
  const s=$('search'),c=$('category');if(s&&s.value)F.append('search',s.value);if(c&&c.value)F.append('category',c.value);
  fetch(E,{method:'POST',body:F}).then(r=>r.json()).then(d=>{
    if(d.success&&Array.isArray(d.products)){P=d.products;render();}else{L.innerHTML='<div class=p-8><p class=text-red-400>'+(d.error||'Failed')+'</p></div>';}
  }).catch(e=>{console.error(e);L.innerHTML='<div class=p-8><p class=text-red-400>Network error</p></div>';});
}

function render(){
  const L=$('productList');if(!L)return;
  if(!P.length){L.innerHTML='<div class=p-8><p class=text-slate-400>No products</p></div>';upd();return;}
  let h='';P.forEach(p=>{h+='<div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-700/40"><input type=checkbox class="product-cb w-4 h-4 rounded bg-slate-800 border-slate-600 text-amber-500" data-id='+p.id+(S.has(+p.id)?' checked':'')+'><div class="flex-1 min-w-0"><div class="text-white text-sm font-medium truncate">'+esc(p.name)+'</div><div class="text-slate-400 text-xs">SKU:'+esc(p.sku||'N/A')+' | $'+(+p.price||0).toFixed(2)+'</div></div></div>';});
  L.innerHTML=h;L.querySelectorAll('.product-cb').forEach(cb=>cb.addEventListener('change',function(){const id=+this.dataset.id;if(this.checked)S.add(id);else S.delete(id);upd();}));
  upd();
}

function upd(){
  const n=S.size;const sp=$('statProducts'),sl=$('statLabels'),sa=$('statPages'),gb=$('generateBtn'),pb=$('pdfBtn');
  if(sp)sp.textContent=P.length;if(sl)sl.textContent=n;if(sa)sa.textContent=Math.ceil(n/30)||0;
  if(gb)gb.disabled=n===0;if(pb)pb.disabled=n===0;
}

function esc(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}

function gen(){
  if(!S.size)return;const sel=P.filter(p=>S.has(+p.id));if(!sel.length)return;
  const F=new FormData();F.append('action','generate_barcodes');F.append('csrf_token',T);F.append('products',JSON.stringify(sel));
  fetch(E,{method:'POST',body:F}).then(r=>r.json()).then(d=>{
    if(d.success&&d.barcodes){printLabels(d.barcodes);}else{alert(d.error||'Generation failed');}
  }).catch(e=>alert('Network error'));
}

function printLabels(barcodes){
  let h='<div id=labelPrintRoot style="display:flex;flex-wrap:wrap;gap:8px;padding:16px;background:#fff;">';const sz=$('label_size')?$('label_size').value:'k22';const cfg={k22:{w:51,h:25},k5:{w:38,h:16},KA2:{w:51,h:13},K38:{w:76,h:38},K11:{w:19,h:13},KA1:{w:25,h:19},K27:{w:51,h:38},k36:{w:76,h:51}};
  const c=cfg[sz]||cfg.k22;
  Object.entries(barcodes).forEach(([pid,b])=>{
    const prod=P.find(p=>p.id==pid)||{};
    const svg=atob(b.svg_base64||'');
    for(let i=0;i<(b.qty||1);i++){h+='<div class=label style="width:'+c.w+'mm;height:'+c.h+'mm;border:1px solid #ccc;padding:2mm;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:Arial,sans-serif;"><div style="font-size:8pt;font-weight:bold;text-align:center;margin-bottom:1mm;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">'+esc(prod.name||'')+'</div><div class=label-barcode>'+svg+'</div><div style="font-size:6pt;text-align:center;margin-top:1mm;">'+esc(b.sku||'')+' $'+(+prod.price||0).toFixed(2)+'</div></div>';}
  });
  h+='</div>';const w=window.open('','_blank');w.document.write('<html><head><title>Labels</title><style>@media print{body>*:not(#labelPrintRoot){display:none;}#labelPrintRoot{display:flex!important;}body.printing-labels{background:#fff;}}</style></head><body class=printing-labels>'+h+'<script>window.onload=function(){setTimeout(function(){window.print();},500);};</script></body></html>');w.document.close();
}

function pdf(){
  if(!S.size)return alert('Select products first');
  const sel=P.filter(p=>S.has(+p.id));const F=new FormData();F.append('action','download_pdf');F.append('csrf_token',T);F.append('products',JSON.stringify(sel));F.append('label_size',$('label_size')?$('label_size').value:'k22');
  fetch(E,{method:'POST',body:F}).then(r=>r.blob()).then(b=>{const u=URL.createObjectURL(b);const a=document.createElement('a');a.href=u;a.download='labels.pdf';a.click();URL.revokeObjectURL(u);}).catch(e=>alert('PDF error'));
}

if($('search'))$('search').addEventListener('input',function(){clearTimeout(this._t);this._t=setTimeout(load,300);});
if($('category'))$('category').addEventListener('change',load);
if($('selectAll'))$('selectAll').addEventListener('change',function(){const cbs=document.querySelectorAll('.product-cb');cbs.forEach(cb=>{cb.checked=this.checked;const id=+cb.dataset.id;if(this.checked)S.add(id);else S.delete(id);});upd();});
if($('generateBtn'))$('generateBtn').addEventListener('click',gen);
if($('pdfBtn'))$('pdfBtn').addEventListener('click',pdf);
load();
})();

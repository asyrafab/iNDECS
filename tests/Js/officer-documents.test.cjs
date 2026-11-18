const test=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const vm=require('node:vm');
class Node {
 constructor(){this.children={};this.events={};this.dataset={};this.attrs={};this.value='';this.textContent='';this.open=false;this.hidden=true;}
 querySelector(key){return this.children[key]||null;}
 querySelectorAll(key){return this.children[key]||[];}
 addEventListener(name,fn){(this.events[name]||=[]).push(fn);}
 emit(name,props={}){const e={preventDefault(){this.prevented=true;},...props};(this.events[name]||[]).forEach(fn=>fn(e));return e;}
 click(){return this.emit('click');}
 getAttribute(key){return this.attrs[key]??null;}
 setAttribute(key,value){this.attrs[key]=value;}
 focus(){this.focused=true;}
 closest(){return this.parent||null;}
}
function boot({historical=false,external=false}={}){
 const root=new Node(),panel=new Node(),selector=new Node(),frame=new Node(),page=new Node(),caption=new Node(),fallback=new Node(),go=new Node(),controls=new Node(),parent=historical?new Node():null;
 const options=['A','B'].map((id,index)=>{const n=new Node();n.value=id;n.textContent='Pelan '+id;n.dataset.documentUrl=external?'https://evil.example/plan.pdf':'https://indecs.test/kejuruteraan/applications/own/documents/'+id;return n;});
 selector.options=options;selector.value='A';
 const rows=options.map((option,index)=>{const row=new Node(),link=new Node();row.id='finding-'+index;row.open=index===0;link.dataset={reviewEvidence:option.value,reviewPage:String(index?5:2)};row.children['[data-review-evidence]']=[link];row.link=link;return row;});
 panel.children={'[data-review-document]':selector,'[data-review-document-frame]':frame,'[data-review-document-page]':page,'[data-review-document-caption]':caption,'[data-review-document-fallback]':fallback,'[data-review-go-page]':go,'[data-review-page-controls]':controls};
 root.children={'[data-review-viewer]':panel,'[data-review-finding]':rows,'[data-review-evidence]':rows.map(r=>r.link)};root.parent=parent;
 vm.runInNewContext(readFileSync(require.resolve('../../public/js/officer-documents.js'),'utf8'),{document:{querySelectorAll:()=>[root]},URL,location:{origin:'https://indecs.test',href:'https://indecs.test/kejuruteraan/applications/own',hash:''}});
 return {rows,selector,frame,page,caption,fallback,go,controls,parent};
}
test('evidence opens the referenced document/page inline; document selection resets the page',()=>{
 const ui=boot();assert.match(ui.frame.getAttribute('src'),/documents\/A#page=2/);assert.equal(ui.controls.hidden,false);
 const event=ui.rows[1].link.click();assert.equal(event.prevented,true);assert.match(ui.frame.getAttribute('src'),/documents\/B#page=5/);
 ui.selector.value='A';ui.selector.emit('change');assert.equal(ui.page.value,'1');assert.match(ui.fallback.href,/A#page=1/);
});
test('opening another finding updates the viewer and page input validates before navigation',()=>{
 const ui=boot();ui.rows[1].open=true;ui.rows[1].emit('toggle');assert.equal(ui.selector.value,'B');
 ui.page.value='3.5';ui.go.click();assert.match(ui.caption.textContent,/Masukkan/);assert.match(ui.frame.getAttribute('src'),/page=5/);
 ui.page.value='7';const event=ui.page.emit('keydown',{key:'Enter'});assert.equal(event.prevented,true);assert.match(ui.frame.getAttribute('src'),/page=7/);
});
test('historical frames load only when their check is opened',()=>{
 const ui=boot({historical:true});assert.equal(ui.frame.getAttribute('src'),null);ui.rows[0].emit('toggle');assert.equal(ui.frame.getAttribute('src'),null);
 ui.parent.open=true;ui.parent.emit('toggle');assert.match(ui.frame.getAttribute('src'),/documents\/A/);
});
test('external URLs are never placed in the frame and modified-clicks retain native behavior',()=>{
 const ui=boot({external:true});assert.equal(ui.frame.getAttribute('src'),null);assert.equal(ui.rows[0].link.click().prevented,undefined);
 const normal=boot();assert.equal(normal.rows[1].link.emit('click',{ctrlKey:true}).prevented,undefined);assert.equal(normal.selector.value,'A');
});

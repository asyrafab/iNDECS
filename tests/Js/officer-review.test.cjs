const test = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
class Node {
    constructor() { this.events={}; this.children={}; this.dataset={}; this.hidden=false; this.value=''; this.textContent=''; this.open=false; }
    addEventListener(name,fn) { (this.events[name] ||= []).push(fn); }
    emit(name) { const event={preventDefault(){this.prevented=true;}}; (this.events[name]||[]).forEach(fn=>fn(event)); return event; }
    querySelector(key) { const item=this.children[key]; return Array.isArray(item)?item[0]:item||null; }
    querySelectorAll(key) { const item=this.children[key]; return Array.isArray(item)?item:item?[item]:[]; }
    closest(key) { return this.parents?.[key] || null; }
    focus() { this.focused=true; }
    scrollIntoView() {}
}
function boot(hash='', focused=false, failedIndex=null) {
    const root=new Node(), window=new Node(), search=new Node(), filter=new Node(), section=new Node(), results=new Node(), empty=new Node(), tools=new Node(), next=new Node(), collapse=new Node(), reset=new Node(), parent=new Node();
    filter.value='all'; tools.hidden=true; root.dataset.reviewFocus=focused?'true':'false'; root.parents={'[data-review-check-history]':parent};
    const rows=['pelan jalan','fotometrik','lampu solar'].map((text,index)=>{
        const row=new Node(), summary=new Node(), form=new Node(), status=new Node(), comment=new Node(), dirty=new Node(), standard=new Node(), standardText=new Node(), rowNext=new Node();
        row.id='finding-'+index; row.dataset={reviewCorpus:text,reviewSectionValue:index===2?'Solar':'Umum',reviewReviewed:index===0?'true':'false',reviewAttention:index===0?'false':'true'};
        dirty.hidden=true; status.value=index===0?'DITUNAIKAN':''; comment.maxLength=2000; standardText.textContent='Sahkan bukti pada pelan.';
        form.parents={'[data-review-finding]':row}; rowNext.parents={'[data-review-finding]':row};
        form.children={'select, textarea':[status,comment],'[data-review-use-standard]':standard,'[data-review-standard-text]':standardText,'[data-review-comment]':comment};
        row.children={'[data-review-form]':[form],'[data-review-dirty]':dirty,'summary':summary};
        if(index===failedIndex){row.dataset.reviewFailed='true';comment.value='Teks gagal disimpan';}
        Object.assign(row,{form,status,comment,dirty,standard,rowNext}); return row;
    });
    root.children={'[data-review-finding]':rows,'[data-review-search]':search,'[data-review-filter]':filter,'[data-review-section]':section,'[data-review-results]':results,'[data-review-empty]':empty,'[data-review-tools]':tools,'[data-review-reset]':reset,'[data-review-collapse]':collapse,'[data-review-next]':[next,...rows.map(r=>r.rowNext)],'[data-review-form]':rows.map(r=>r.form)};
    const queue=new Node(),queueSelect=new Node(),prev=new Node(),queueNext=new Node(),position=new Node();queue.hidden=true;queueSelect.options=rows.map((row,index)=>({value:row.id,dataset:{queueLabel:'Dapatan '+index}}));Object.assign(root.children,{'[data-review-queue]':queue,'[data-review-queue-select]':queueSelect,'[data-review-queue-prev]':prev,'[data-review-queue-next]':queueNext,'[data-review-queue-prev], [data-review-queue-next]':[prev,queueNext],'[data-review-active-position]':position});
    const location={hash};
    vm.runInNewContext(readFileSync(require.resolve('../../public/js/officer-review.js'),'utf8'),{document:{querySelectorAll:()=>[root],querySelector:()=>null},window,location,history:{replaceState(a,b,value){location.hash=value;}}});
    return {root,rows,window,search,filter,section,results,empty,tools,next,collapse,reset,location,parent,queue,queueSelect,prev,queueNext,position};
}
test('search, review and section filters combine; reset restores all rows',()=>{
    const ui=boot(); assert.equal(ui.tools.hidden,false);
    ui.filter.value='pending';ui.filter.emit('change');assert.deepEqual(ui.rows.map(r=>r.hidden),[true,false,false]);
    ui.section.value='Solar';ui.section.emit('change');assert.deepEqual(ui.rows.map(r=>r.hidden),[true,true,false]);
    ui.search.value='tiada';ui.search.emit('input');assert.equal(ui.empty.hidden,false);
    ui.reset.emit('click');assert.ok(ui.rows.every(r=>!r.hidden));assert.equal(ui.search.focused,true);
});
test('filtering and collapse preserve unsaved edits and warn before navigation',()=>{
    const ui=boot();const row=ui.rows[1];row.open=true;row.comment.value='Ulasan belum disimpan';row.comment.emit('input');
    ui.search.value='solar';ui.search.emit('input');assert.equal(row.hidden,false);assert.equal(row.dirty.hidden,false);assert.match(ui.results.textContent,/dikekalkan/);
    ui.collapse.emit('click');assert.equal(row.open,true);assert.equal(ui.window.emit('beforeunload').prevented,true);
    row.comment.value='';row.comment.emit('input');assert.equal(row.dirty.hidden,true);assert.equal(row.hidden,true);assert.equal(ui.window.emit('beforeunload').prevented,undefined);
});
test('standard comment appends on explicit action without submitting and respects limit',()=>{
    const ui=boot();const row=ui.rows[1];row.comment.value='Nota pegawai';row.standard.emit('click');assert.equal(row.comment.value,'Nota pegawai\n\nSahkan bukti pada pelan.');assert.equal(row.dirty.hidden,false);
    row.comment.value='x'.repeat(1995);row.standard.emit('click');assert.equal(row.comment.value.length,1995);assert.match(ui.results.textContent,/2,000/);
});
test('saved finding hash opens historical parent and next skips reviewed findings',()=>{
    const ui=boot('#finding-1');assert.equal(ui.parent.open,true);assert.equal(ui.rows[1].open,true);
    ui.rows[1].rowNext.emit('click');assert.equal(ui.location.hash,'#finding-2');assert.equal(ui.rows[2].open,true);
});
test('a cancelled navigation retains all edited rows; a sole dirty form can submit',()=>{
    const ui=boot();for(const row of ui.rows.slice(1)){row.comment.value='Draf';row.comment.emit('input');}
    ui.rows[1].form.emit('submit');assert.equal(ui.window.emit('beforeunload').prevented,true);
    ui.search.value='tiada';ui.search.emit('input');assert.equal(ui.rows[1].hidden,false);assert.equal(ui.rows[2].hidden,false);
    const single=boot();single.rows[1].comment.value='Draf';single.rows[1].comment.emit('input');single.rows[1].form.emit('submit');assert.equal(single.window.emit('beforeunload').prevented,undefined);
});

test('focus shows exactly one pending finding and queue navigation retains unsaved input',()=>{
 const ui=boot('',true);assert.deepEqual(ui.rows.map(r=>r.hidden),[true,false,true]);assert.equal(ui.queue.hidden,false);
 ui.rows[1].comment.value='Ulasan dalam ingatan halaman';ui.rows[1].comment.emit('input');ui.queueNext.emit('click');assert.deepEqual(ui.rows.map(r=>r.hidden),[true,true,false]);
 assert.equal(ui.rows[1].comment.value,'Ulasan dalam ingatan halaman');assert.match(ui.queueSelect.options[1].textContent,/belum disimpan/);assert.equal(ui.window.emit('beforeunload').prevented,true);
 ui.prev.emit('click');assert.equal(ui.rows[1].hidden,false);assert.equal(ui.rows.filter(r=>r.open).length,1);
});
test('focus filters select a matching finding and empty results reset safely',()=>{
 const ui=boot('',true);ui.section.value='Solar';ui.section.emit('change');assert.equal(ui.rows[2].hidden,false);assert.equal(ui.queueSelect.options[1].disabled,true);
 ui.search.value='tidak sepadan';ui.search.emit('input');assert.ok(ui.rows.every(r=>r.hidden));assert.equal(ui.empty.hidden,false);assert.equal(ui.prev.disabled,true);
 ui.reset.emit('click');assert.equal(ui.rows[1].hidden,false);
});
test('failed validation has initial priority over stale hash and remains protected on later navigation',()=>{
 const ui=boot('#finding-0',true,2);assert.equal(ui.rows[2].hidden,false);assert.equal(ui.location.hash,'#finding-2');assert.equal(ui.rows[2].dirty.hidden,false);
 ui.queueSelect.value='finding-1';ui.queueSelect.emit('change');assert.equal(ui.rows[1].hidden,false);assert.equal(ui.rows[2].comment.value,'Teks gagal disimpan');
 ui.location.hash='#finding-0';ui.window.emit('hashchange');assert.equal(ui.rows[0].hidden,false);assert.equal(ui.window.emit('beforeunload').prevented,true);
});

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
function boot(hash='') {
    const root=new Node(), window=new Node(), search=new Node(), filter=new Node(), section=new Node(), results=new Node(), empty=new Node(), tools=new Node(), next=new Node(), collapse=new Node(), reset=new Node(), parent=new Node();
    filter.value='all'; tools.hidden=true; root.parents={'[data-review-check-history]':parent};
    const rows=['pelan jalan','fotometrik','lampu solar'].map((text,index)=>{
        const row=new Node(), summary=new Node(), form=new Node(), status=new Node(), comment=new Node(), dirty=new Node(), standard=new Node(), standardText=new Node(), rowNext=new Node();
        row.id='finding-'+index; row.dataset={reviewCorpus:text,reviewSectionValue:index===2?'Solar':'Umum',reviewReviewed:index===0?'true':'false',reviewAttention:index===0?'false':'true'};
        dirty.hidden=true; status.value=index===0?'DITUNAIKAN':''; comment.maxLength=2000; standardText.textContent='Sahkan bukti pada pelan.';
        form.parents={'[data-review-finding]':row}; rowNext.parents={'[data-review-finding]':row};
        form.children={'select, textarea':[status,comment],'[data-review-use-standard]':standard,'[data-review-standard-text]':standardText,'[data-review-comment]':comment};
        row.children={'[data-review-form]':[form],'[data-review-dirty]':dirty,'summary':summary};
        Object.assign(row,{form,status,comment,dirty,standard,rowNext}); return row;
    });
    root.children={'[data-review-finding]':rows,'[data-review-search]':search,'[data-review-filter]':filter,'[data-review-section]':section,'[data-review-results]':results,'[data-review-empty]':empty,'[data-review-tools]':tools,'[data-review-reset]':reset,'[data-review-collapse]':collapse,'[data-review-next]':[next,...rows.map(r=>r.rowNext)],'[data-review-form]':rows.map(r=>r.form)};
    const location={hash};
    vm.runInNewContext(readFileSync(require.resolve('../../public/js/officer-review.js'),'utf8'),{document:{querySelectorAll:()=>[root]},window,location,history:{replaceState(a,b,value){location.hash=value;}}});
    return {root,rows,window,search,filter,section,results,empty,tools,next,collapse,reset,location,parent};
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

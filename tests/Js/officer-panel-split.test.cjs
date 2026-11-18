const test=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const vm=require('node:vm');
class Node {
 constructor(){this.listeners={};this.dataset={};this.attrs={};this.hidden=true;this.width=1240;this.left=100;this.values={};this.classes=new Set();this.style={setProperty:(k,v)=>this.values[k]=v};this.classList={toggle:(k,v)=>v?this.classes.add(k):this.classes.delete(k),add:k=>this.classes.add(k),remove:k=>this.classes.delete(k)};}
 addEventListener(k,fn){(this.listeners[k]||=[]).push(fn);}
 emit(k,props={}){const e={preventDefault(){this.prevented=true;},...props};(this.listeners[k]||[]).forEach(fn=>fn(e));return e;}
 querySelector(key){return this.children?.[key]||null;}
 getBoundingClientRect(){return {left:this.left,width:this.width};}
 setAttribute(k,v){this.attrs[k]=v;}
 setPointerCapture(id){this.capture=id;}
 hasPointerCapture(id){return this.capture===id;}
 releasePointerCapture(){this.capture=null;}
 focus(){this.focused=true;}
}
function boot({saved=null,blocked=false,user='42',width=1240,desktop=true}={}){
 const desk=new Node(),divider=new Node(),reset=new Node(),window=new Node(),document=new Node(),media=new Node();let resize;
 desk.width=width;desk.dataset.reviewUser=user;desk.children={'[data-review-divider]':divider,'[data-review-split-reset]':reset};
 divider.width=24;divider.left=desk.left+(desk.width-40)*.55+8;
 media.matches=desktop;window.matchMedia=()=>media;document.querySelectorAll=()=>[desk];
 const savedValues=new Map();if(saved!==null)savedValues.set('indecs.technical-review.split.'+user,String(saved));
 const storage={getItem(key){if(blocked)throw Error('Storage disabled');return savedValues.get(key)??null;},setItem(k,v){if(blocked)throw Error('Storage disabled');savedValues.set(k,v);}};
 vm.runInNewContext(readFileSync(require.resolve('../../public/js/officer-panel-split.js'),'utf8'),{document,window,localStorage:storage,getComputedStyle:()=>({getPropertyValue:key=>({'--ratio-technical-plan-default':'.55','--size-technical-pane-min':'320px','--space-6':'24px','--space-2':'8px'})[key]}),ResizeObserver:class{constructor(fn){resize=fn;}observe(){}}});
 const ratio=()=>parseFloat(desk.values['--technical-plan-fr']);
 return {desk,divider,reset,window,document,media,savedValues,ratio,resize:()=>resize()};
}
test('keyboard resizing, min/max and reset update accessible values and user-scoped preference',()=>{
 const ui=boot();assert.equal(ui.divider.hidden,false);assert.equal(ui.ratio(),.55);
 ui.divider.emit('keydown',{key:'ArrowRight'});assert.equal(ui.ratio(),.57);assert.equal(ui.divider.attrs['aria-valuenow'],'57');
 assert.ok(Math.abs(Number(ui.savedValues.get('indecs.technical-review.split.42'))-.57)<.000001);
 ui.divider.emit('keydown',{key:'End'});assert.ok((1-ui.ratio())*1200>=319.999);
 ui.divider.emit('keydown',{key:'Home'});assert.ok(ui.ratio()*1200>=319.999);
 ui.divider.emit('keydown',{key:'Enter'});assert.equal(ui.ratio(),.55);
 ui.divider.emit('keydown',{key:'ArrowLeft'});ui.reset.emit('click');assert.equal(ui.ratio(),.55);
});
test('pointer drag clamps panels and persists only on release',()=>{
 const ui=boot();const x=ui.divider.left+12;ui.divider.emit('pointerdown',{button:0,pointerId:7,clientX:x});
 ui.divider.emit('pointermove',{pointerId:7,clientX:10000});assert.ok(ui.desk.classes.has('is-resizing'));assert.equal(ui.savedValues.size,0);
 assert.ok((1-ui.ratio())*1200>=319.999);ui.divider.emit('pointerup',{pointerId:7});assert.equal(ui.desk.classes.has('is-resizing'),false);assert.equal(ui.savedValues.size,1);assert.equal(ui.divider.capture,null);
});
test('Escape, pointer cancellation and window blur restore the previous split without storing changes',()=>{
 for(const action of ['Escape','pointercancel','blur']){
 const ui=boot();ui.divider.emit('pointerdown',{button:0,pointerId:1,clientX:ui.divider.left+12});ui.divider.emit('pointermove',{pointerId:1,clientX:10000});
 if(action==='Escape')ui.divider.emit('keydown',{key:action});else if(action==='blur')ui.window.emit('blur');else ui.divider.emit(action,{pointerId:1});
 assert.equal(ui.ratio(),.55);assert.equal(ui.savedValues.size,0);assert.equal(ui.desk.classes.has('is-resizing'),false);
 }
});
test('small layouts stack; resizing back restores the preference without replacing it with a clamp',()=>{
 const ui=boot({saved:.7});ui.desk.width=700;ui.resize();assert.ok(ui.ratio()<.52);assert.equal(ui.savedValues.get('indecs.technical-review.split.42'),'0.7');
 assert.ok(Number(ui.divider.attrs['aria-valuenow'])<=Number(ui.divider.attrs['aria-valuemax']));
 ui.desk.width=600;ui.resize();assert.equal(ui.divider.hidden,true);assert.ok(ui.desk.classes.has('is-narrow'));
 ui.desk.width=1240;ui.resize();assert.equal(ui.ratio(),.7);
 ui.media.matches=false;ui.media.emit('change');assert.equal(ui.divider.hidden,true);
});
test('invalid storage and blocked storage use a working default',()=>{
 for(const options of [{saved:'bad'},{saved:Infinity},{saved:-1},{blocked:true}]){
 const ui=boot(options);assert.equal(ui.ratio(),.55);ui.divider.emit('keydown',{key:'ArrowLeft'});assert.equal(ui.ratio(),.53);
 }
 const ui=boot({saved:.65,user:'99'});assert.equal(ui.ratio(),.65);ui.divider.emit('dblclick');assert.equal(ui.ratio(),.55);assert.equal(ui.savedValues.has('indecs.technical-review.split.42'),false);
});

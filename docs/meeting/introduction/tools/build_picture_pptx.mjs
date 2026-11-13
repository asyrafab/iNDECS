import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
const runtime='C:/Program Files/WindowsApps/OpenAI.CodexPrimaryRuntime.v26-1104-1290-0_26.1104.1290.0_x64__3k8sg7r9htsxt/dependencies';
process.env.RUNTIME_NODE_MODULES=runtime+'/node/node_modules';
process.env.RUNTIME_NODE=process.execPath;
const {Presentation,PresentationFile}=await import(pathToFileURL(runtime+'/node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs').href);
const skill='C:/Users/Asyra/.codex/plugins/cache/openai-primary-runtime/presentations/26.1104.12130/skills/presentations';
const {finalizePresentation}=await import(pathToFileURL(skill+'/container_tools/artifact_tool_utils.mjs').href);
const workspace=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const output=path.join(workspace,'presentation');
const build=path.join(workspace,'.build-picture-deck');
const slides=JSON.parse(await fs.readFile(path.join(output,'slides.json'),'utf8'));
const script=await fs.readFile(path.join(workspace,'02-SKRIP-PEMBENTANG.md'),'utf8');
const sections=script.split(/^## \d+\..*$/m).slice(1);
const deck=Presentation.create({slideSize:{width:1600,height:900}});
for (const [i,m] of slides.entries()) {
 const slide=deck.slides.add();
 const bytes=new Uint8Array(await fs.readFile(path.join(output,'png',m.file)));
 slide.images.add({blob:bytes,contentType:'image/png',alt:m.title,fit:'contain',position:{left:0,top:0,width:1600,height:900}});
 slide.speakerNotes.text=(sections[i]||'').trim()+'\n\nSUMBER: docs/meeting/introduction/01-SUMBER-UTAMA-SLAID.md; AGENTS.md; ADR-352; docs/integration/indecs-airs-contract.md. Fungsi sasaran, bukan pengesahan operasi.\nASET: public/img/departments/*-detailed.svg (P, K, B, L); public/img/airs-icon.svg (AIRS); public/img/esri-logo.svg. Esri logo © Esri; penggunaan identiti subsistem spatial, tanpa implikasi sokongan kepada iNDECS. Lambang MBSP dan tanda iNDECS daripada public/img/.';
}
await fs.mkdir(build,{recursive:true});
const candidate=path.join(build,'candidate.pptx');
await (await PresentationFile.exportPptx(deck)).save(candidate);
const finalPath=path.join(output,'iNDECS-pengenalan.pptx');
const result=await finalizePresentation({
 workspaceDir:workspace,candidatePath:candidate,finalPath,
 pythonExecutable:runtime+'/python/python.exe',
 integrityValidatorPath:skill+'/container_tools/inspect_presentation_package_integrity.py',
 layoutValidatorPath:skill+'/container_tools/inspect_presentation_layout_geometry.py',
 layoutArgs:['--expected-slide-size-emu','15240000,8572500'],
 explicitTotalSlideCount:7,requiredNativeTableOwnerSlides:[],requiredNativeChartOwnerSlides:[],
 verifyArtifactToolImport:true,
 receiptPath:path.join(build,'validation.json')
});
console.log(JSON.stringify({finalPath,result}));

"""Render seven picture slides using the approved local identity assets."""
from pathlib import Path
import re, json, shutil, html, zipfile, subprocess, xml.etree.ElementTree as ET
import pymupdf as fitz

ROOT=Path(__file__).resolve().parents[4]
BASE=ROOT/"docs/meeting/introduction"
DEST=BASE/"presentation"
BUILD=BASE/".build-picture-deck"
PNG=DEST/"png"
ASSETS=DEST/"assets"
for d in [DEST,BUILD,PNG,ASSETS]: d.mkdir(parents=True,exist_ok=True)
CSS=(ROOT/"public/css/tokens.css").read_text(encoding="utf-8").split('[data-theme')[0]
TOK=dict(re.findall(r"(--[\w-]+)\s*:\s*([^;]+);",CSS))
def resolve(v, values=TOK):
    for _ in range(8):
        v2=re.sub(r"var\((--[\w-]+)(?:,\s*([^)]+))?\)",lambda m:values.get(m[1],m[2] or "0"),v)
        if v2==v: break
        v=v2
    return v
def color(key): return tuple(int(resolve(TOK[key]).strip().lstrip("#")[i:i+2],16)/255 for i in (0,2,4))
NAV="--color-navy"; TXT="--color-text"; MUT="--color-text-muted"; TEAL="--color-accent-700"; BG="--color-bg"; SOFT="--color-bg-soft"; WHITE="--color-surface"; BORDER="--color-border"
FONTDIR=Path.home()/"AppData/Local/Microsoft/Windows/Fonts"
fonts={False:FONTDIR/"GoogleSans-Regular.ttf",True:FONTDIR/"GoogleSans-SemiBold.ttf"}
metric={k:fitz.Font(fontfile=str(v)) for k,v in fonts.items()}
W,H=1600,900
doc=fitz.open()
metadata=[]

def svgpng(source,target,size=1000):
    svg=Path(source).read_text(encoding="utf-8")
    values=TOK|dict(re.findall(r"(--[\w-]+)\s*:\s*([^;\"}]+)",svg))
    svg=resolve(svg,values).replace("currentColor",resolve(TOK[NAV]))
    resolved=BUILD/(Path(target).stem+"-resolved.svg")
    resolved.write_text(svg,encoding="utf-8")
    runtime=Path("C:/Program Files/WindowsApps/OpenAI.CodexPrimaryRuntime.v26-1104-1290-0_26.1104.1290.0_x64__3k8sg7r9htsxt/dependencies/node/bin/node.exe")
    subprocess.run([str(runtime),str(Path(__file__).with_name("render_svg.mjs")),str(resolved),str(target),str(size)],check=True,capture_output=True)

for dept in ["perancang","kejuruteraan","bangunan","landskap"]:
    src=ROOT/"public/img/departments"/(dept+"-detailed.svg")
    shutil.copy2(src,ASSETS/(dept+".svg"))
    svgpng(src,ASSETS/(dept+".png"))
for name in ["airs-icon","esri-logo","indecs-mark","indecs-mark-reversed","mbsp-logo"]:
    src=ROOT/"public/img"/(name+".svg")
    shutil.copy2(src,ASSETS/(name+".svg"))
    svgpng(src,ASSETS/(name+".png"))

# Reuse official Lucide paths, in token colours, for semantic role/process symbols.
sprite=ET.parse(ROOT/"public/img/lucide.svg").getroot()
for name in ["files","file-text","folder-open","search","messages-square","clock-3","map","circle-check","layers"]:
    sym=next(s for s in sprite if s.attrib.get("id")==name)
    svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="'+resolve(TOK[TEAL])+'" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">'+''.join(ET.tostring(c,encoding="unicode") for c in sym)+'</svg>'
    path=BUILD/(name+".svg");path.write_text(svg,encoding="utf-8")
    svgpng(path,ASSETS/("lucide-"+name+".png"),300)

class Slide:
    def __init__(self,num,title,subtitle=None,target=True):
        self.num=num;self.title=title;self.texts=[]
        self.p=doc.new_page(width=W,height=H)
        for k,v in fonts.items(): self.p.insert_font(fontname="GSB" if k else "GS",fontfile=str(v))
        self.rect(0,0,W,H,BG)
        self.image("mbsp-logo",64,38,45,49)
        self.line(129,42,129,83,BORDER,2)
        self.image("indecs-mark",148,42,40,40)
        self.text(203,74,"iNDECS",28,True,NAV)
        self.text(1027,70,"Integrated Development Control",22,False,MUT)
        self.text(64,164,title,46,True,NAV)
        if subtitle:self.text(64,212,subtitle,26,False,MUT)
        self.line(64,824,1536,824,BORDER,1.5)
        self.text(64,864,"Konsep sasaran — pelaksanaan berperingkat" if target else "Perbincangan awal bersama ketua jabatan MBSP",21,False,MUT)
        self.text(1452,864,f"{num:02} / 07",21,False,MUT)
    def rect(self,x,y,w,h,fill=WHITE,stroke=None):
        self.p.draw_rect(fitz.Rect(x,y,x+w,y+h),fill=color(fill),color=color(stroke) if stroke else None,width=1.4)
    def text(self,x,y,s,size=30,bold=False,c=TXT,maxw=None):
        width=metric[bold].text_length(s,fontsize=size)
        assert x>=0 and x+width<=W-40,(self.num,s,x,width)
        assert 0<y<H-10,(self.num,s,y)
        if maxw: assert width<=maxw,(self.num,s,width,maxw)
        self.p.insert_text((x,y),s,fontname="GSB" if bold else "GS",fontsize=size,color=color(c))
        self.texts.append(s)
    def lines(self,x,y,ss,size=30,bold=False,c=TXT,leading=None,maxw=None):
        for i,s in enumerate(ss):self.text(x,y+i*(leading or size*1.45),s,size,bold,c,maxw)
    def line(self,x1,y1,x2,y2,c=BORDER,width=2):
        self.p.draw_line((x1,y1),(x2,y2),color=color(c),width=width)
    def arrow(self,x1,y1,x2,y2):
        self.line(x1,y1,x2,y2,TEAL,2.5)
        self.line(x2-10,y2-7,x2,y2,TEAL,2.5);self.line(x2-10,y2+7,x2,y2,TEAL,2.5)
    def image(self,name,x,y,w,h):
        self.p.insert_image(fitz.Rect(x,y,x+w,y+h),filename=str(ASSETS/(name+".png")),keep_proportion=True)
    def finish(self):
        filename=f"{self.num:02}-"+["keperluan-mbsp","dua-skop-penghantaran","konsep-indecs","skop-jabatan","fungsi-utama","peranan-sistem","aliran-dan-susulan"][self.num-1]+".png"
        self.p.get_pixmap(matrix=fitz.Matrix(2.4,2.4),alpha=False).save(PNG/filename)
        metadata.append({"number":self.num,"title":self.title,"file":filename,"text":self.texts})

# 1: context, not a standalone cover.
s=Slide(1,"Situasi semasa dan keperluan MBSP",target=False)
s.lines(64,302,["Satu pembangunan.","Pelbagai urusan jabatan."],49,True,NAV,leading=66)
s.lines(64,493,["MBSP belum mempunyai platform","kawalan pemajuan bersepadu","merentas empat jabatan."],31,leading=45)
s.lines(64,685,["Sistem silo masih memerlukan","penyelarasan rekod, dokumen dan status."],28,c=MUT,leading=42)
for i,(name,label) in enumerate([("perancang","Perancang"),("kejuruteraan","Kejuruteraan"),("bangunan","Bangunan"),("landskap","Landskap")]):
    x=860+(i%2)*355; y=255+(i//2)*215
    s.image(name,x,y,126,126)
    s.text(x,y+164,label,28,True)
s.rect(822,704,714,82,SOFT)
s.text(850,740,"Keperluan: satu platform bersama,",29,True,TEAL)
s.text(850,775,"dengan proses khusus setiap jabatan",28,c=TEAL)
s.finish()

# 2: resolve e-submission confusion with actual system identities.
s=Slide(2,"Penghantaran dalam talian dan data spatial","Istilah “e-submission” sering merujuk kepada dua skop berbeza")
s.line(810,260,810,698,BORDER,2)
s.image("indecs-mark",64,269,77,77);s.text(163,320,"iNDECS",34,True,NAV)
s.text(315,320,"+",30,c=MUT);s.image("airs-icon",363,265,83,83);s.text(466,320,"AIRS",34,True,NAV)
s.text(64,407,"Penghantaran permohonan dalam talian",31,True,NAV,maxw=710)
s.lines(64,476,["Borang, dokumen dan pelan","Bayaran, pembetulan dan status","Keputusan dan surat rasmi"],30,leading=58)
s.text(64,676,"iNDECS mengurus rekod; AIRS menyokong semakan",25,c=MUT,maxw=710)
s.image("esri-logo",877,275,235,85)
s.text(877,407,"E-Plan Submission (Esri)",32,True,NAV)
s.lines(877,476,["Penghantaran CAD dan Excel","Pemprosesan kepada data GIS","Kegunaan peta dan analisis"],30,leading=58)
s.rect(64,734,1472,62,SOFT)
s.text(88,775,"Data spatial ialah satu komponen dalam keseluruhan urusan permohonan",31,True,TEAL)
s.finish()

# 3: a system of record, with the meeting function inside its boundary.
s=Slide(3,"Apakah iNDECS?","Platform bersepadu pengurusan permohonan kawalan pemajuan MBSP")
s.lines(64,319,["Portal utama.","Rekod rasmi.","Proses dihubungkan."],47,True,NAV,leading=65)
s.lines(64,572,["Menghubungkan pemohon,","jabatan teknikal dan OSC."],31,leading=47)
s.lines(64,705,["Versi dokumen dan sejarah tindakan","disimpan bersama rekod permohonan."],27,c=MUT,leading=41)
s.rect(774,266,762,520,NAV)
s.image("indecs-mark-reversed",802,295,68,68);s.text(894,345,"iNDECS",40,True,WHITE)
for i,(name,label) in enumerate([("perancang","Perancang"),("kejuruteraan","Kejuruteraan"),("bangunan","Bangunan"),("landskap","Landskap")]):
    x=803+i*180
    s.image(name,x,411,106,106)
    s.text(x,554,label,22,True,WHITE)
s.line(803,592,1505,592,"--color-slate",2)
s.text(803,642,"eOSC / NADI",33,True,WHITE)
s.text(803,691,"iNDECS daftar kertas; NADI untuk mesyuarat",27,c=WHITE)
s.text(803,747,"Akses mengikut peranan dan kebenaran",24,c=WHITE)
s.finish()

# 4: preserve each department's native icon and colour.
s=Slide(4,"Skop jabatan dan permohonan","Perkhidmatan teras dikongsi; proses teknikal kekal mengikut jabatan")
depts=[("perancang",["Jabatan Perancang","Bandar"],["PKM"]),("kejuruteraan",["Jabatan","Kejuruteraan"],["PKT, PKP"]),("bangunan",["Jabatan","Bangunan"],["PB, PBK, PBS","PMT, TEL"]),("landskap",["Jabatan","Landskap"],["PL"])]
for i,(name,label,codes) in enumerate(depts):
    x=64+i*375
    if i:s.line(x-28,278,x-28,675,BORDER,1.5)
    s.image(name,x,280,195,195)
    s.lines(x,535,label,30,True,leading=42,maxw=330)
    s.lines(x,642,codes,30,c=TEAL,leading=41)
s.rect(64,737,1472,60,NAV)
s.text(88,777,"Teras bersama: akses pengguna, dokumen, aliran kerja dan rekod tindakan",30,True,WHITE)
s.finish()

# 5: flat editorial columns, semantic icons rather than simulated UI cards.
s=Slide(5,"Fungsi utama yang disasarkan","Daripada persediaan pemohon kepada pemprosesan dan pemantauan")
groups=[
("folder-open","Pemohon / PSP",[["Panduan dan","persediaan dokumen"],["Penghantaran","dan bayaran"],["Status dan","maklum balas"]]),
("file-text","Pegawai jabatan",[["Semakan dokumen","dan teknikal"],["Tugasan, ulasan","dan pembetulan"],["Rekod tindakan dan","versi dokumen"]]),
("map","Pengurusan / OSC",[["Pemantauan status","dan tempoh"],["Agenda, kertas","dan peta"],["Pertimbangan dan","rekod keputusan"]])]
for i,(icon,title,items) in enumerate(groups):
    x=64+i*505
    s.image("lucide-"+icon,x,270,60,60)
    s.text(x,381,title,35,True,NAV)
    s.line(x,411,x+430,411,TEAL,3)
    for j,lines in enumerate(items):s.lines(x,478+j*111,lines,30,leading=41,maxw=440)
s.finish()

# 6: the connected ecosystem, with no invented assistant identity.
s=Slide(6,"Komponen sistem dan peranannya","Setiap komponen mempunyai tanggungjawab yang berbeza")
# Connection lines first, all outside text.
s.line(515,364,585,364,TEAL,3);s.line(515,649,585,649,TEAL,3)
s.line(1025,455,1104,455,TEAL,3)
s.image("airs-icon",64,282,87,87)
s.text(177,326,"AIRS",37,True,NAV)
s.lines(64,410,["Bantuan semakan teknikal","Bukti dan dapatan semakan"],28,leading=44,maxw=445)
s.text(64,599,"AMIRA",37,True,NAV)
s.lines(64,660,["Panduan dan bantuan pengguna","Mengikut kebenaran akses"],28,leading=44,maxw=445)
s.rect(585,273,440,480,NAV)
s.image("indecs-mark-reversed",610,302,61,61)
s.text(691,347,"iNDECS",35,True,WHITE)
s.lines(610,422,["Portal utama","Rekod rasmi","Aliran kerja"],31,c=WHITE,leading=47)
s.rect(610,583,390,140,SOFT)
s.text(633,631,"NADI > eOSC",33,True,NAV)
s.text(633,674,"iNDECS daftar; NADI bersidang",25,c=NAV)
s.image("esri-logo",1104,318,220,80)
s.text(1104,467,"E-Plan Submission",30,True,NAV)
s.lines(1104,525,["CAD / Excel kepada GIS","Data spatial"],28,leading=45,maxw=425)
s.text(64,797,"AI membantu; keputusan kekal pada pegawai dan pihak berkuasa berkenaan",29,True,TEAL)
s.finish()

# 7: six steps, concise gate order and a specific next action.
s=Slide(7,"Aliran keseluruhan dan langkah seterusnya","Perjalanan sasaran permohonan kawalan pemajuan")
steps=[("folder-open",["Persediaan"]),("search",["Pra-semak","percuma"]),("circle-check",["Bayaran dan","penerimaan","rasmi"]),("file-text",["Semakan dan","pembetulan"]),("messages-square",["Pertimbangan"]),("files",["Keputusan","dan surat"])]
for i,(icon,labels) in enumerate(steps):
    x=64+i*248
    s.text(x,294,f"{i+1:02}",26,True,TEAL)
    s.image("lucide-"+icon,x,322,59,59)
    s.lines(x,448,labels,28,True,NAV,leading=40,maxw=224)
    if i<5:s.arrow(x+177,353,x+225,353)
s.lines(64,624,["Pra-semak: kelengkapan dan kebolehgunaan fail, bukan pematuhan teknikal.",
"Bayaran disahkan sebelum penerimaan rasmi; AIRS formal menyusul selepasnya."],26,c=MUT,leading=43)
s.rect(64,724,1472,77,NAV)
s.text(88,772,"Seterusnya: sesi setiap jabatan untuk proses, keperluan dan keutamaan",31,True,WHITE)
s.finish()

# Retain editable vector/text source PDF privately; requested public PDF uses the exact picture slides.
doc.set_metadata({"title":"Integrated Development Control — Konsep iNDECS","author":"MBSP","subject":"Pembentangan pengenalan; konsep sasaran"})
doc.subset_fonts()
doc.save(BUILD/"source-vector.pdf",garbage=4,deflate=True)
doc.close()
flat=fitz.open()
for m in metadata:
    p=flat.new_page(width=W,height=H)
    p.insert_image(p.rect,filename=str(PNG/m["file"]))
flat.set_metadata({"title":"Integrated Development Control — Konsep iNDECS","author":"MBSP","subject":"7 slaid gambar"})
flat.save(DEST/"iNDECS-pengenalan.pdf",garbage=4,deflate=True)
flat.close()
(DEST/"slides.json").write_text(json.dumps(metadata,ensure_ascii=False,indent=2),encoding="utf-8")

# Large previews for final human review.
sheet=fitz.open();p=sheet.new_page(width=1600,height=1950)
p.draw_rect(p.rect,color=None,fill=color(BG))
for i,m in enumerate(metadata):
    x=20+(i%2)*800;y=20+(i//2)*480
    p.insert_image(fitz.Rect(x,y,x+760,y+427.5),filename=str(PNG/m["file"]))
p.get_pixmap(alpha=False).save(DEST/"preview.png");sheet.close()
print(json.dumps({"slides":len(metadata),"resolution":"3840 x 2160","output":str(DEST)},ensure_ascii=False))

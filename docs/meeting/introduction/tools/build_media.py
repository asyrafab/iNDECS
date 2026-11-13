"""Rebuild introduction diagrams. Run: python docs/meeting/introduction/tools/build_media.py
Requires PyMuPDF (fitz). Uses installed Google Sans; never downloads assets.
"""
from pathlib import Path
import re, html, shutil, json
import fitz

ROOT = Path(__file__).resolve().parents[4]
OUT = ROOT / "docs/meeting/introduction/media"
OUT.mkdir(parents=True, exist_ok=True)
css = (ROOT / "public/css/tokens.css").read_text(encoding="utf-8").split('[data-theme')[0]
tokens = dict(re.findall(r"(--[\w-]+)\s*:\s*([^;]+);", css))
def val(key):
    v = tokens[key].strip()
    if v.startswith("var("): return val(v[4:-1])
    return v
FONTDIR = Path.home() / "AppData/Local/Microsoft/Windows/Fonts"
fonts = {False: FONTDIR / "GoogleSans-Regular.ttf", True: FONTDIR / "GoogleSans-SemiBold.ttf"}
assert all(p.exists() for p in fonts.values()), "Install Google Sans before rebuilding"
metrics = {k: fitz.Font(fontfile=str(v)) for k,v in fonts.items()}
W,H = 1600,900

def rgb(key):
    s = val(key).lstrip("#")
    return tuple(int(s[i:i+2],16)/255 for i in (0,2,4))

class Figure:
    def __init__(self, name, title, subtitle):
        self.name=name
        self.doc=fitz.open()
        self.p=self.doc.new_page(width=W,height=H)
        self.p.insert_font(fontname="GS",fontfile=str(fonts[False]))
        self.p.insert_font(fontname="GSS",fontfile=str(fonts[True]))
        self.svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" role="img" aria-labelledby="title desc">',
            f'<title id="title">{html.escape(title)}</title><desc id="desc">{html.escape(subtitle)}</desc>',
            '<style>:root {' + "".join(f"{k}:{val(k)};" for k in ["--color-navy","--color-text","--color-text-muted","--color-accent","--color-accent-700","--color-bg","--color-bg-soft","--color-surface","--color-border"]) + '} text {font-family:"Google Sans","Segoe UI",sans-serif;}</style>']
        self.rect(0,0,W,H,"--color-bg")
        self.text(64,90,title,42,True)
        self.text(64,139,subtitle,25,color="--color-text-muted")
        self.line(64,172,1536,172)
    def rect(self,x,y,w,h,fill="--color-surface",stroke=None):
        self.p.draw_rect(fitz.Rect(x,y,x+w,y+h),color=rgb(stroke) if stroke else None,fill=rgb(fill),width=2)
        self.svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="var({fill})" stroke="{f"var({stroke})" if stroke else "none"}" stroke-width="2"/>')
    def text(self,x,y,s,size=28,bold=False,color="--color-text",maxwidth=None):
        width=metrics[bold].text_length(s,fontsize=size)
        assert x>=0 and x+width<=W-35, (self.name,s,width)
        if maxwidth: assert width<=maxwidth,(self.name,s,width,maxwidth)
        assert y < H-10
        self.p.insert_text((x,y),s,fontname="GSS" if bold else "GS",fontsize=size,color=rgb(color))
        self.svg.append(f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{600 if bold else 400}" fill="var({color})">{html.escape(s)}</text>')
    def line(self,x1,y1,x2,y2,color="--color-border",width=2):
        self.p.draw_line((x1,y1),(x2,y2),color=rgb(color),width=width)
        self.svg.append(f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="var({color})" stroke-width="{width}"/>')
    def arrow(self,x1,y1,x2,y2):
        self.line(x1,y1,x2,y2,"--color-accent",3)
        # All arrows used here are horizontal.
        self.line(x2-12,y2-8,x2,y2,"--color-accent",3)
        self.line(x2-12,y2+8,x2,y2,"--color-accent",3)
    def footer(self,s="Konsep sasaran — pelaksanaan berperingkat"):
        self.line(64,803,1536,803)
        self.text(64,849,s,23,color="--color-text-muted")
    def save(self):
        (OUT/(self.name+".svg")).write_text("\n".join(self.svg)+"\n</svg>\n",encoding="utf-8")
        self.p.get_pixmap(matrix=fitz.Matrix(1.5,1.5),alpha=False).save(str(OUT/(self.name+".png")))
        self.doc.close()

f=Figure("01-penyelarasan","Keperluan penyelarasan merentas jabatan","Satu pembangunan boleh melibatkan beberapa permohonan dan jabatan")
f.text(72,232,"Risiko sistem berasingan",32,True)
f.text(865,232,"Konsep platform bersama",32,True)
for i,name in enumerate(["Perancang","Bangunan","Kejuruteraan","Landskap"]):
    x=72+(i%2)*315; y=276+(i//2)*150
    f.rect(x,y,280,116,stroke="--color-border")
    f.text(x+20,y+44,name,29,True)
    f.text(x+20,y+84,"Rekod tersendiri",25,color="--color-text-muted")
f.arrow(710,416,824,416)
f.rect(865,276,670,112,"--color-navy")
f.text(890,321,"iNDECS",37,True,"--color-surface")
f.text(890,359,"Rekod dan proses dihubungkan",26,color="--color-surface")
for i,name in enumerate(["Perancang","Bangunan","Kejuruteraan","Landskap"]):
    x=865+(i%2)*345; y=434+(i//2)*107
    f.line(x+160,388 if i<2 else 516,x+160,y,"--color-accent",3)
    f.rect(x,y,325,82,"--color-bg-soft")
    f.text(x+20,y+52,name,28,True)
f.text(72,662,"Rekod terpisah",28)
f.text(72,709,"Penyelarasan masih diperlukan",28)
f.text(865,709,"Proses khusus jabatan dikekalkan",29,True,"--color-accent-700")
f.footer("Risiko silo dan konsep sasaran; bukan audit seni bina semasa")
f.save()

f=Figure("02-dua-skop","Dua skop yang sering disebut e-submission","Penghantaran permohonan dalam talian dan penghantaran data spatial saling melengkapi")
for x,title in [(64,"Permohonan dalam talian"),(838,"E-Plan Submission (Esri)")]:
    f.rect(x,221,698,66,"--color-navy")
    f.text(x+24,265,title,32,True,"--color-surface",650)
left=["Borang, dokumen dan pelan","Bayaran, pembetulan dan status","Keputusan dan surat rasmi"]
right=["Penghantaran CAD dan Excel","Pemprosesan kepada data GIS","Kegunaan peta dan analisis"]
for x,lines in [(64,left),(838,right)]:
    for i,s in enumerate(lines):
        f.text(x+24,350+i*73,s,29,maxwidth=650)
        f.line(x+24,373+i*73,x+674,373+i*73)
f.text(88,615,"iNDECS: rekod dan aliran kerja",28,True)
f.text(88,658,"AIRS: bantuan semakan teknikal",28)
f.text(862,615,"Subsistem spatial",28,True)
f.text(862,658,"Dihubungkan dengan iNDECS",28)
f.rect(64,707,1472,62,"--color-bg-soft")
f.text(88,748,"Data spatial ialah satu komponen dalam keseluruhan urusan permohonan",30,True,"--color-accent-700")
f.footer(); f.save()

f=Figure("03-konsep-indecs","iNDECS — platform kawalan pemajuan","Portal utama dan pemegang rekod rasmi")
f.rect(64,334,300,160,stroke="--color-border")
f.text(88,395,"Pemohon / PSP",32,True)
f.text(88,442,"Persediaan dan urusan",24)
f.arrow(380,414,505,414)
f.rect(525,273,540,462,"--color-navy")
f.text(555,337,"iNDECS",48,True,"--color-surface")
for i,s in enumerate(["Rekod rasmi","Versi dokumen","Aliran kerja dan sejarah"]):
    f.text(555,404+i*53,s,31,color="--color-surface")
f.arrow(1082,414,1188,414)
f.rect(1205,273,331,300,"--color-bg-soft")
f.text(1229,330,"Jabatan teknikal",31,True)
for i,s in enumerate(["Perancang","Bangunan","Kejuruteraan","Landskap"]):
    f.text(1229,386+i*47,s,28)
f.rect(550,596,490,112,"--color-bg-soft")
f.text(575,640,"eOSC / NADI",31,True)
f.text(575,684,"iNDECS daftar; NADI bersidang",26)
f.footer(); f.save()

f=Figure("04-skop-jabatan","Empat jabatan, satu platform bersama","Skop sasaran aplikasi jabatan dan jenis permohonan")
data=[("Perancang","PKM"),("Bangunan","PB, PBK, PBS, PMT, TEL"),("Kejuruteraan","PKT, PKP"),("Landskap","PL")]
for i,(name,code) in enumerate(data):
    x=64+i*375
    f.rect(x,275,347,255,stroke="--color-border")
    f.rect(x,275,347,8,"--color-accent")
    f.text(x+20,347,name,31,True,maxwidth=310)
    # Bangunan codes split for legibility
    if i==1:
        f.text(x+20,423,"PB, PBK, PBS",30)
        f.text(x+20,467,"PMT, TEL",30)
    else: f.text(x+20,423,code,32)
    f.line(x+173,530,x+173,600,"--color-accent",3)
f.rect(64,600,1472,130,"--color-navy")
f.text(88,649,"Perkhidmatan teras dikongsi",32,True,"--color-surface")
f.text(88,699,"Akses pengguna   |   Dokumen   |   Aliran kerja   |   Rekod tindakan",29,color="--color-surface")
f.footer("Skop sasaran — proses teknikal kekal mengikut jabatan"); f.save()

f=Figure("05-fungsi-pengguna","Fungsi utama mengikut pengguna","Gambaran sasaran; perincian proses disahkan dalam sesi jabatan")
groups=[
("Pemohon / PSP",["Panduan dan persediaan","dokumen","Penghantaran dan bayaran","Status dan maklum balas"]),
("Pegawai jabatan",["Semakan dokumen","dan teknikal","Tugasan, ulasan, pembetulan","Rekod dan versi dokumen"]),
("Pengurusan / OSC",["Pemantauan status","dan tempoh","Agenda, kertas dan peta","Pertimbangan dan keputusan"])]
for i,(title,lines) in enumerate(groups):
    x=64+i*500
    f.text(x+15,293,title,32,True)
    f.line(x+15,320,x+455,320,"--color-accent",4)
    for j,s in enumerate(lines):
        f.text(x+15,395+j*75,s,27,maxwidth=448)
f.text(79,751,"Fungsi bersama menyokong proses khusus setiap jabatan",31,True,"--color-accent-700")
f.footer(); f.save()

f=Figure("06-ekosistem","Komponen sistem dan peranannya","iNDECS memegang rekod; komponen lain menyokong fungsi khusus")
# Draw connections first, so labels and surfaces remain clear.
f.line(488,345,570,345,"--color-accent",3)
f.line(488,624,570,624,"--color-accent",3)
f.line(1040,446,1110,446,"--color-accent",3)
f.rect(64,245,424,200,stroke="--color-border")
f.text(88,303,"AIRS",37,True)
f.text(88,354,"Bantuan semakan teknikal",27)
f.text(88,399,"Bukti dan dapatan",27)
f.rect(64,524,424,200,stroke="--color-border")
f.text(88,582,"AMIRA",37,True)
f.text(88,633,"Panduan dan bantuan",27)
f.text(88,678,"Mengikut akses pengguna",27)
f.rect(570,245,470,479,"--color-navy")
f.text(598,309,"iNDECS",42,True,"--color-surface")
f.text(598,363,"Portal utama",29,color="--color-surface")
f.text(598,413,"Rekod dan aliran kerja",29,color="--color-surface")
f.rect(598,497,414,195,"--color-bg-soft")
f.text(620,551,"NADI > eOSC",35,True)
f.text(620,600,"Modul mesyuarat NADI",28)
f.text(620,647,"Dalam iNDECS",27)
f.rect(1110,313,426,265,stroke="--color-border")
f.text(1134,373,"E-Plan Submission",31,True,maxwidth=380)
f.text(1134,419,"(Esri)",28)
f.text(1134,477,"CAD / Excel kepada GIS",27)
f.text(1134,527,"Data spatial",28)
f.footer("Konsep sasaran — AI membantu; keputusan pada pihak berkuasa berkenaan"); f.save()

f=Figure("07-aliran-sasaran","Aliran keseluruhan dan langkah seterusnya","Konsep sasaran — pelaksanaan berperingkat")
steps=[["Persediaan"],["Pra-semak","percuma"],["Bayaran dan","penerimaan","rasmi"],["Semakan dan","pembetulan"],["Pertimbangan"],["Keputusan","dan surat"]]
for i,lines in enumerate(steps):
    x=64+i*248
    f.text(x,267,f"{i+1:02}",35,True,"--color-accent-700")
    f.rect(x,304,220,190,"--color-bg-soft" if i!=2 else "--color-navy")
    for j,s in enumerate(lines):
        f.text(x+15,356+j*48,s,25,True,"--color-surface" if i==2 else "--color-navy",maxwidth=192)
    if i<5: f.arrow(x+223,401,x+242,401)
f.text(64,559,"Pra-semak: kelengkapan dan kebolehgunaan fail",28,True)
f.text(64,605,"Bayaran disahkan sebelum penerimaan rasmi; pengecualian dasar perlu diluluskan",26)
f.text(64,651,"AIRS formal selepas penerimaan. Pembetulan boleh berulang; laluan OSC mengikut jenis.",26)
f.rect(64,699,1472,73,"--color-navy")
f.text(88,746,"Seterusnya: sesi setiap jabatan untuk proses, keperluan dan keutamaan",30,True,"--color-surface")
f.footer("Semakan teknikal dan keputusan berasingan daripada pra-semak percuma"); f.save()

# Preserve owner-supplied identity assets unchanged.
for name in ["mbsp-logo.svg","mbsp.png","indecs-mark.svg","indecs-mark-reversed.svg"]:
    source=ROOT/"public/img"/name
    if source.exists(): shutil.copy2(source,OUT/name)

# Render original SVG assets into high-resolution transparent PNG equivalents.
for name in ["mbsp-logo","indecs-mark","indecs-mark-reversed"]:
    source=OUT/(name+".svg")
    d=fitz.open(source)
    page=d[0]
    scale=1200/max(page.rect.width,page.rect.height)
    page.get_pixmap(matrix=fitz.Matrix(scale,scale),alpha=True).save(str(OUT/(name+".png")))
    d.close()

# Contact sheet for visual review; it is not an eighth presentation slide.
sheet=fitz.open(); p=sheet.new_page(width=1600,height=1500)
p.draw_rect(p.rect,color=None,fill=rgb("--color-bg"))
for i,path in enumerate(sorted(OUT.glob("0[1-7]-*.png"))):
    col=i%2; row=i//2
    r=fitz.Rect(20+col*800,20+row*370,780+col*800,20+row*370+427.5)
    # Keep each preview inside its row.
    r=fitz.Rect(r.x0,r.y0,r.x1,r.y0+342)
    p.insert_image(r,filename=str(path),keep_proportion=True)
p.get_pixmap(alpha=False).save(str(OUT/"00-apercu-grafik.png"))
sheet.close()
print(json.dumps({"diagrams":7,"png_resolution":"2400 x 1350","output":str(OUT)},ensure_ascii=False))

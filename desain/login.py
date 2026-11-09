#!/usr/bin/env python3
"""Login screen for Sistem Akreditasi LAM-PTKes — built over FigCraft MCP."""
import json, sys
from mcp_client import FigMCP

IND, IND_D, IND_T = "#2F2A95", "#211D6B", "#EEEDF8"
ORN, ORN_T = "#FF8200", "#FFF3E6"
BG, SURF, BD, DV = "#F5F6F9", "#FFFFFF", "#E4E7EC", "#F2F4F7"
T1, T2, T3 = "#101828", "#667085", "#98A2B3"
UI, MONO = "Helvetica Neue", "Menlo"

ops = []
def op(cmd, **p):
    ops.append({"command": cmd, "as": f"s{len(ops)}", "params": p})
    return {"$ref": f"s{len(ops)-1}"}

def frame(x, y, w, h, name, fill=SURF):
    return op("frame.create", x=x, y=y, width=w, height=h, name=name, props={"fill": fill, "clip": True})

def rect(parent, x, y, w, h, fill=None, radius=0, stroke=None, sw=1, name="rect"):
    p = {"name": name, "cornerRadius": radius}
    if fill: p["fill"] = fill
    if stroke: p["stroke"] = stroke; p["strokeWeight"] = sw
    return op("shape.rectangle", parent=parent, x=x, y=y, width=w, height=h, props=p)

def ell(parent, x, y, w, h, fill, name="ell"):
    return op("shape.ellipse", parent=parent, x=x, y=y, width=w, height=h, props={"name": name, "fill": fill})

def txt(parent, x, y, s, size=13, color=T1, weight="Regular", width=None, align=None,
        family=UI, name=None, lh=None):
    p = {"fontSize": size, "fontFamily": family, "fontStyle": weight, "fill": color}
    if width: p["width"] = width
    if align: p["textAlign"] = align
    if lh: p["lineHeight"] = lh
    return op("text.create", parent=parent, x=x, y=y, text=s, name=name or s[:32], props=p)

def hline(parent, x, y, w, color=BD):
    return rect(parent, x, y, w, 1, fill=color, name="hr")

# ============ FRAME A — Login, split brand ============
f = frame(0, 0, 1440, 900, "A · Login", fill=SURF)

# --- left brand panel ---
rect(f, 0, 0, 620, 900, fill=IND, name="brand-panel")
# subtle motif: concentric arcs top-right
ell(f, 470, -180, 340, 340, IND_D, "arc-1")
ell(f, 520, -130, 240, 240, "#37319F", "arc-2")
ell(f, 560, -90, 160, 160, "#3F38AC", "arc-3")
ell(f, -160, 620, 260, 260, IND_D, "arc-4")

# logo mark (recreated from UBBG logo: two indigo arcs + orange nib + orange book)
rect(f, 64, 56, 44, 44, fill="#FFFFFF", radius=10, name="logo-plate")
ell(f, 74, 66, 12, 12, IND, "logo-arc-l")
ell(f, 86, 66, 12, 12, IND, "logo-arc-r")
rect(f, 78, 78, 16, 8, fill=ORN, radius=2, name="logo-nib")
rect(f, 74, 88, 24, 5, fill=ORN, radius=2, name="logo-book")

txt(f, 122, 62, "UBBG", 17, "#FFFFFF", "Bold")
txt(f, 122, 82, "Universitas Bina Bangsa Getsempena", 10, "#C9C6EC")

txt(f, 64, 300, "Sistem Akreditasi\nLAM-PTKes", 44, "#FFFFFF", "Bold", width=500, lh=52, name="hero")
rect(f, 64, 440, 56, 4, fill=ORN, radius=2, name="hero-accent")
txt(f, 64, 468, "Satu tempat isi, banyak tempat pakai. Data dukung 38 tabel,\n79 butir dokumen, dan ekspor siap cetak sesuai instrumen.",
    14, "#D5D2F0", width=470, lh=22, name="hero-sub")

# fakta panel
facts = [("38", "tabel data dukung"), ("79", "butir dokumen"), ("8", "kriteria penilaian")]
for i, (num, lab) in enumerate(facts):
    x = 64 + i * 170
    txt(f, x, 600, num, 30, "#FFFFFF", "Bold", name=f"fact-{num}")
    txt(f, x, 642, lab, 11, "#B8B4E4", width=150, name=f"factlab-{num}")
rect(f, 64, 590, 470, 1, fill="#4640A8", name="hr-facts")

txt(f, 64, 800, "Fakultas Ilmu Kesehatan · UPPS FIK", 11, "#B8B4E4")
txt(f, 64, 818, "Jl. Tanggul Krueng Lamnyong No. 34, Banda Aceh", 10, "#8F8ACB")

# --- right form panel ---
FX = 620
PAD = 150
IX = FX + PAD
IW = 520
rect(f, FX, 0, 820, 900, fill=SURF, name="form-panel")
txt(f, IX, 150, "Masuk ke sistem", 27, T1, "Bold", name="form-title")
txt(f, IX, 188, "Gunakan akun kampus. Hak akses ditentukan dari peran yang diberikan admin UPPS.",
    12, T2, width=520, lh=18, name="form-sub")

# field: email
txt(f, IX, 246, "Email kampus", 11, "#344054", "Medium")
rect(f, IX, 268, 520, 46, fill=SURF, radius=7, stroke=BD, sw=1, name="input-email")
txt(f, FX + 136, 284, "nama@ubbg.ac.id", 13, T3, name="placeholder-email")

# field: password (focused state)
txt(f, IX, 334, "Kata sandi", 11, "#344054", "Medium")
txt(f, 620 + PAD + IW - 204, 334, "Lupa sandi?", 11, IND, "Medium", width=204, align="right")
rect(f, IX, 356, 520, 46, fill=SURF, radius=7, stroke=IND, sw=2, name="input-pass")
txt(f, FX + 136, 372, "••••••••••••", 13, T1, name="pass-value")
ell(f, FX + 596, 371, 16, 16, IND_T, "eye-bg")
txt(f, FX + 596, 373, "○", 10, IND, width=16, align="center", name="eye")
# caret + hint
rect(f, FX + 136, 372, 1, 15, fill=IND, name="caret")

# prodi context (server-side determined, shown read-only)
txt(f, IX, 424, "Konteks kerja setelah masuk", 11, "#344054", "Medium")
rect(f, IX, 446, 520, 54, fill=BG, radius=7, stroke=None, name="ctx")
txt(f, FX + 136, 456, "Fak. Ilmu Kesehatan (UPPS FIK)", 12, T1, "Medium", name="ctx-upps")
txt(f, FX + 136, 474, "Kebidanan (D-III) · Keperawatan (S1 + Ners)", 11, T2, name="ctx-prodi")
rect(f, FX + 596, 460, 28, 26, fill=IND_T, radius=5, name="ctx-lock")
txt(f, FX + 596, 466, "🔒", 10, IND, width=28, align="center", name="ctx-lock-ico")

# remember + submit
rect(f, IX, 525, 18, 18, fill=SURF, radius=4, stroke="#98A2B3", name="check")
txt(f, FX + 144, 527, "Ingat perangkat ini 30 hari", 11, "#344054", name="check-label")
rect(f, IX, 562, 520, 48, fill=IND, radius=7, name="btn-masuk")
txt(f, IX, 577, "Masuk", 14, "#FFFFFF", "Bold", width=520, align="center", name="btn-label")

hline(f, IX, 644, 520, DV)
txt(f, IX, 664, "Belum punya akses? Hubungi admin UPPS — akun dibuat per peran.",
    11, T2, width=520, lh=17, name="foot-help")
txt(f, IX, 720, "Versi 1.0 · MVP", 10, T3)
txt(f, 620, 720, "", 1, T3)
txt(f, 1140, 720, "LAM-PTKes · TS 2025/2026", 10, T3, width=180, align="right")

# ============ FRAME B — Login error / validasi ============
f2 = frame(1520, 0, 1440, 900, "B · Login (error)", fill=SURF)
rect(f2, 0, 0, 620, 900, fill=IND, name="brand-panel")
ell(f2, 470, -180, 340, 340, IND_D, "arc-1")
ell(f2, 520, -130, 240, 240, "#37319F", "arc-2")
rect(f2, 64, 56, 44, 44, fill="#FFFFFF", radius=10, name="logo-plate")
ell(f2, 74, 66, 12, 12, IND, "logo-arc-l")
ell(f2, 86, 66, 12, 12, IND, "logo-arc-r")
rect(f2, 78, 78, 16, 8, fill=ORN, radius=2, name="logo-nib")
rect(f2, 74, 88, 24, 5, fill=ORN, radius=2, name="logo-book")
txt(f2, 122, 62, "UBBG", 17, "#FFFFFF", "Bold")
txt(f2, 122, 82, "Universitas Bina Bangsa Getsempena", 10, "#C9C6EC")
txt(f2, 64, 300, "Sistem Akreditasi\nLAM-PTKes", 44, "#FFFFFF", "Bold", width=500, lh=52, name="hero")
rect(f2, 64, 440, 56, 4, fill=ORN, radius=2, name="hero-accent")
txt(f2, 64, 468, "Satu tempat isi, banyak tempat pakai.", 14, "#D5D2F0", width=470, name="hero-sub")
txt(f2, 64, 800, "Fakultas Ilmu Kesehatan · UPPS FIK", 11, "#B8B4E4")

FX2 = 620
PAD2 = 150
IX2 = FX2 + PAD2
IW2 = 520
rect(f2, FX2, 0, 820, 900, fill=SURF, name="form-panel")
txt(f2, IX2, 150, "Masuk ke sistem", 27, T1, "Bold")
txt(f2, IX2, 188, "Gunakan akun kampus. Hak akses ditentukan dari peran yang diberikan admin UPPS.",
    12, T2, width=520, lh=18)

# error banner
rect(f2, IX2, 236, 520, 56, fill="#FEF3F2", radius=7, stroke="#FDA29B", name="alert")
rect(f2, IX2, 236, 3, 56, fill="#B42318", radius=2, name="alert-bar")
txt(f2, FX2 + 136, 248, "Email atau kata sandi salah.", 12, "#B42318", "Bold", name="alert-t")
txt(f2, FX2 + 136, 266, "Sisa 4 percobaan sebelum akun dikunci 15 menit.", 11, "#912018", name="alert-s")

txt(f2, IX2, 312, "Email kampus", 11, "#344054", "Medium")
rect(f2, IX2, 334, 520, 46, fill=SURF, radius=7, stroke=BD, name="input-email")
txt(f2, FX2 + 136, 350, "operator@ubbg.ac.id", 13, T1, name="val-email")

txt(f2, IX2, 400, "Kata sandi", 11, "#344054", "Medium")
txt(f2, 620 + PAD2 + IW2 - 204, 400, "Lupa sandi?", 11, IND, "Medium", width=204, align="right")
rect(f2, IX2, 422, 520, 46, fill=SURF, radius=7, stroke="#B42318", sw=2, name="input-pass-err")
txt(f2, FX2 + 136, 438, "••••••••", 13, T1)
txt(f2, FX2 + 136, 474, "Kata sandi minimal 8 karakter.", 10, "#B42318", name="field-err")

rect(f2, IX2, 505, 18, 18, fill=SURF, radius=4, stroke="#98A2B3", name="check")
txt(f2, FX2 + 144, 507, "Ingat perangkat ini 30 hari", 11, "#344054")
rect(f2, IX2, 542, 520, 48, fill=IND, radius=7, name="btn-masuk")
txt(f2, IX2, 557, "Masuk", 14, "#FFFFFF", "Bold", width=520, align="center")
txt(f2, IX2, 624, "Butuh bantuan akses? hubungi admin.upps@ubbg.ac.id", 11, T2)
txt(f2, IX2, 720, "Versi 1.0 · MVP", 10, T3)

if __name__ == "__main__":
    m = FigMCP(attach=None)
    try:
        r = m.call("batch", **{"commands": ops, "onError": "abort", "label": "Login screens"})
        print("batch ok:", str(r)[:300])
        doc = m.call("inspect_document", **{"depth": 1})
        frames = [c for c in doc.get("page", doc).get("children", []) if c.get("type") == "FRAME"]
        if not frames:
            frames = [c for c in doc.get("children", []) if c.get("type") == "FRAME"]
        print("frames:", [(f["id"], f["name"]) for f in frames])
        print(m.call("save_file", **{"path": "login.figcraft"}))
        for f in frames:
            out = m.call("export", **{"format": "png", "scale": 2,
                                      "path": f"/tmp/login_{f['name'][0]}.png",
                                      "ids": [f["id"]]})
            print(str(out)[:160])
    finally:
        m.close()

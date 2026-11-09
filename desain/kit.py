"""Shared drawing kit: builds FigCraft command ops (for `batch` over MCP)."""
from icons import icon_ops

IND, IND_D, IND_T = "#2F2A95", "#211D6B", "#EEEDF8"
ORN, ORN_T = "#FF8200", "#FFF3E6"
BG, SURF, BD, DV = "#F5F6F9", "#FFFFFF", "#E4E7EC", "#F2F4F7"
T1, T2, T3 = "#101828", "#667085", "#98A2B3"
UI, MONO = "Helvetica Neue", "Menlo"

ST = {
    "kosong":   ("#98A2B3", "#F2F4F7"),
    "sebagian": ("#B54708", "#FFFAEB"),
    "lengkap":  ("#067647", "#ECFDF3"),
    "terkunci": ("#175CD3", "#EFF8FF"),
    "na":       ("#667085", "#F9FAFB"),
    "gagal":    ("#B42318", "#FEF3F2"),
    "ok":       ("#067647", "#ECFDF3"),
}
LAB = {"kosong": "Kosong", "sebagian": "Sebagian", "lengkap": "Lengkap",
       "terkunci": "Terkunci", "na": "Tak berlaku", "gagal": "Gagal", "ok": "Lolos"}


class Kit:
    def __init__(self):
        self.ops = []

    def op(self, cmd, **p):
        self.ops.append({"command": cmd, "as": f"s{len(self.ops)}", "params": p})
        return {"$ref": f"s{len(self.ops)-1}"}

    def frame(self, x, y, w, h, name, fill=SURF):
        return self.op("frame.create", x=x, y=y, width=w, height=h, name=name,
                       props={"fill": fill, "clip": True})

    def rect(self, parent, x, y, w, h, fill=None, radius=0, stroke=None, sw=1, name="rect"):
        p = {"name": name, "cornerRadius": radius}
        if fill:
            p["fill"] = fill
        if stroke:
            p["stroke"] = stroke
            p["strokeWeight"] = sw
        return self.op("shape.rectangle", parent=parent, x=x, y=y, width=w, height=h, props=p)

    def ell(self, parent, x, y, w, h, fill, name="ell"):
        return self.op("shape.ellipse", parent=parent, x=x, y=y, width=w, height=h,
                       props={"name": name, "fill": fill})

    def txt(self, parent, x, y, s, size=13, color=T1, weight="Regular", width=None,
            align=None, family=UI, name=None, lh=None):
        p = {"fontSize": size, "fontFamily": family, "fontStyle": weight, "fill": color}
        if width:
            p["width"] = width
        if align:
            p["textAlign"] = align
        if lh:
            p["lineHeight"] = lh
        return self.op("text.create", parent=parent, x=x, y=y, text=s,
                       name=name or s[:32].replace("\n", " "), props=p)

    def hline(self, parent, x, y, w, color=BD):
        return self.rect(parent, x, y, w, 1, fill=color, name="hr")

    def vline(self, parent, x, y, h, color=BD):
        return self.rect(parent, x, y, 1, h, fill=color, name="vr")

    def badge(self, parent, x, y, kind, label=None, size=9):
        fg, bg = ST[kind]
        lab = label or LAB[kind]
        w = 16 + len(lab) * (size * 0.58)
        self.rect(parent, x, y, w, 20, fill=bg, radius=5,
                  stroke=fg if kind == "na" else None, name=f"badge {lab}")
        self.txt(parent, x, y + 4, lab, size, fg, "Medium", width=w, align="center")
        return w

    # ---------- chrome ----------
    def topbar(self, f):
        r = self.rect
        r(f, 0, 0, 1440, 56, fill=SURF, name="topbar")
        self.hline(f, 0, 55, 1440)
        r(f, 20, 15, 26, 26, fill=IND, radius=7, name="logo-bg")
        r(f, 26, 21, 14, 14, fill=ORN, radius=4, name="logo-acc")
        self.txt(f, 58, 19, "Sistem Akreditasi LAM-PTKes", 14, T1, "Bold")
        self.txt(f, 58, 34, "Universitas Bina Bangsa Getsempena", 10, T2)
        self.txt(f, 900, 20, "Fakultas Ilmu Kesehatan  ·  UPPS FIK", 11, T2, width=380,
                 align="right")
        r(f, 1308, 14, 28, 28, fill=IND_T, radius=14, name="avatar")
        self.txt(f, 1308, 20, "YP", 10, IND, "Bold", width=28, align="center")

    def sidebar(self, f, prodi="Kebidanan", accent=ORN, jenjang="D-III",
                aktif="Dashboard", y0=56, h=968, tabel_wajib=32):
        r = self.rect
        r(f, 0, y0, 248, h, fill=SURF, name="sidebar")
        self.vline(f, 248, y0, h)
        r(f, 16, y0 + 20, 216, 34, fill="#F9FAFB", radius=8, stroke=BD, name="switch")
        r(f, 17, y0 + 21, 107, 32, fill=accent, radius=7, name="switch-active")
        self.txt(f, 17, y0 + 29, prodi, 11, "#FFFFFF", "Bold", width=107, align="center")
        other = "Keperawatan" if prodi == "Kebidanan" else "Kebidanan"
        self.txt(f, 124, y0 + 29, other, 11, T2, "Medium", width=107, align="center")
        r(f, 16, y0 + 64, 216, 30, fill=SURF, radius=6, stroke=BD, name="periode")
        self.txt(f, 26, y0 + 71, "TS 2025/2026", 11, T1, "Medium")
        self.txt(f, 100, y0 + 71, f"· {jenjang}", 11, T2)
        self.txt(f, 200, y0 + 71, "v", 10, T2, width=24, align="center")

        y = [y0 + 112]

        def label(s, color=T3):
            self.txt(f, 20, y[0], s, 9, color, "Bold")
            y[0] += 20

        def row(s, code=None, active=False, dot=None, badge_kind=None, badge_lab=None,
                ico=None):
            if active:
                r(f, 8, y[0] - 5, 232, 28, fill=IND_T, radius=6, name=f"nav-active {s}")
                r(f, 8, y[0] - 2, 3, 22, fill=IND, radius=2, name="nav-bar")
            icolor = IND if active else (accent if dot else "#475467")
            if ico:
                icon_ops(self.op, f, 20, y[0] + 1, ico, icolor)
            tx = 44 if ico else (34 if dot else 22)
            if dot and not ico:
                r(f, 22, y[0] + 3, 6, 6, fill=dot, radius=3, name=f"dot {s}")
            self.txt(f, tx, y[0], s, 11, IND if active else "#344054",
                     "Semibold" if active else "Regular", width=150)
            if badge_kind:
                self.badge(f, 160, y[0] - 1, badge_kind, badge_lab)
            elif code:
                self.txt(f, 200, y[0] + 1, code, 9, T3, family=MONO, width=32,
                         align="right")
            y[0] += 30

        label("MENU")
        row("Dashboard", active=(aktif == "Dashboard"), ico="dashboard")
        row("Ringkasan 8 Kriteria", active=(aktif == "Ringkasan"), ico="summary")
        y[0] += 8
        label("BERSAMA UPPS")
        row("Identitas UPPS & Prodi", "F-01", ico="identity")
        row("Periode Akreditasi", "F-02", ico="period")
        row("Dosen & Tendik", "F-06", ico="lecturer")
        row("Keuangan", "F-17", ico="finance")
        row("Kerja Sama", "F-19", ico="partner")
        row("Pengguna & Hak Akses", "F-22", ico="access")
        y[0] += 8
        label(f"PRODI: {prodi.upper()}", accent)
        row("Mahasiswa", "F-05", dot=accent, ico="student")
        row("Kurikulum & MK", "F-07", dot=accent, ico="curriculum",
            active=(aktif == "Kurikulum"))
        row("Sarana & Wahana", "F-15", dot=accent, ico="facility",
            active=(aktif == "Sarana"))
        row("Pustaka & Jurnal", "F-16", dot=accent, ico="library",
            active=(aktif == "Pustaka"))
        row("Uji Kompetensi", "F-09", dot=accent, ico="competency",
            active=(aktif == "Uji"))
        row("Survei & Tracer", "F-10", dot=accent, ico="survey",
            active=(aktif == "Survei"))
        y[0] += 6
        self.hline(f, 16, y[0], 216, DV)
        y[0] += 14
        row("Data Dukung 38 Tabel", badge_kind="sebagian",
            badge_lab=f"{tabel_wajib}/38", ico="archive")
        row("Dokumen Pendukung", badge_kind="sebagian", badge_lab="41/79", ico="document")
        row("Konsistensi", badge_kind="gagal", badge_lab="2 gagal", ico="consistency")
        y[0] += 10
        self.txt(f, 20, y[0], "EKSPOR PRODI INI", 9, T3, "Bold")
        y[0] += 20
        r(f, 16, y[0], 104, 30, fill=ORN, radius=6, name="btn-pdf")
        self.txt(f, 16, y[0] + 8, "PDF", 11, "#FFFFFF", "Bold", width=104, align="center")
        r(f, 128, y[0], 104, 30, fill=SURF, radius=6, stroke=BD, name="btn-xlsx")
        self.txt(f, 128, y[0] + 8, "XLSX", 11, "#344054", "Medium", width=104,
                 align="center")

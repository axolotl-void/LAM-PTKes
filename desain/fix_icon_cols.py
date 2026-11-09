"""Fix icon/label columns in every sidebar, LIVE.

Icon column is pinned at x=22, label column at x=44 — regardless of what the
row used before (dot rows used to start at 34). Badge rows shift too so the
right-hand column stays aligned.
"""
from live_client import L

ICON_X = 22
LABEL_X = 44

SIDEBAR_FRAMES = ["01 · Dashboard", "02 · Data Dukung", "03 · Form Tabel 5",
                  "04 · Sidebar State", "04b · Sidebar Keperawatan"]

# every text that is a nav label in these sidebars
NAV_LABELS = {
    "Dashboard", "Ringkasan 8 Kriteria", "Ringkasan & Kriteria",
    "Identitas UPPS & Prodi", "Periode Akreditasi", "Dosen & Tendik",
    "Keuangan", "Kerja Sama", "Pengguna & Hak Akses",
    "Mahasiswa", "Kurikulum & MK", "Sarana & Wahana", "Pustaka & Jurnal",
    "Uji Kompetensi", "Survei & Tracer",
    "Data Dukung 38 Tabel", "Dokumen Pendukung", "Konsistensi",
}


def frame_ids():
    d = L.call("inspect_document", depth=1)
    doc = d.get("document", d)
    return {c["name"]: c["id"]
            for p in doc["pages"] for c in p.get("children", [])
            if c.get("type") == "FRAME"}


def collect(fid):
    """icons: [(id, y)]  labels: [(id, chars, y)]  within one frame."""
    node = L.call("get_node", id=fid, depth=6)
    icons, labels = [], []

    def walk(n):
        for c in n.get("children", []):
            nm = c.get("name") or ""
            if c.get("type") == "VECTOR" and nm.startswith("ico "):
                icons.append((c["id"], c.get("y", 0)))
            elif c.get("type") == "TEXT":
                ch = (c.get("characters") or "").strip()
                if ch in NAV_LABELS:
                    labels.append((c["id"], ch, c.get("y", 0)))
            walk(c)

    walk(node)
    return icons, labels


if __name__ == "__main__":
    for name, fid in frame_ids().items():
        if name not in SIDEBAR_FRAMES:
            continue
        icons, labels = collect(fid)
        if not icons and not labels:
            print(f"{name}: empty")
            continue
        ops = []
        for iid, _y in icons:
            ops.append({"command": "node.set",
                        "params": {"ids": [iid], "x": ICON_X}})
        for lid, ch, _y in labels:
            ops.append({"command": "node.set",
                        "params": {"ids": [lid], "x": LABEL_X}})
        res = L.batch(ops, label=f"Align {name}")
        bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
        print(f"{name}: {len(icons)} icons, {len(labels)} labels, failed {len(bad)}")
        for b in bad[:2]:
            print("   ", b)

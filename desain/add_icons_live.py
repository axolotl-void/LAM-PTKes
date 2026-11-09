"""Add stroke icons to every sidebar nav row — LIVE in the FigCraft app.

Works on whatever sidebar frames exist right now (04, 04b, and the sidebars
inside 01/02/03), by finding the nav/anchor rects by name.
"""
import json
from icons import ICONS
from live_client import L

IND, ORN, GREY = "#2F2A95", "#FF8200", "#475467"

# label -> icon key
ICON_FOR = {
    "Dashboard": "dashboard",
    "Ringkasan 8 Kriteria": "summary",
    "Ringkasan & Kriteria": "summary",
    "Identitas UPPS & Prodi": "identity",
    "Periode Akreditasi": "period",
    "Dosen & Tendik": "lecturer",
    "Keuangan": "finance",
    "Kerja Sama": "partner",
    "Pengguna & Hak Akses": "access",
    "Mahasiswa": "student",
    "Kurikulum & MK": "curriculum",
    "Sarana & Wahana": "facility",
    "Pustaka & Jurnal": "library",
    "Uji Kompetensi": "competency",
    "Survei & Tracer": "survey",
    "Data Dukung 38 Tabel": "archive",
    "Dokumen Pendukung": "document",
    "Konsistensi": "consistency",
}

SIDEBAR_FRAMES = ["01 · Dashboard", "02 · Data Dukung", "03 · Form Tabel 5",
                  "04 · Sidebar State", "04b · Sidebar Keperawatan"]


def frame_ids():
    d = L.call("inspect_document", depth=1)
    doc = d.get("document", d)
    out = {}
    for p in doc["pages"]:
        for c in p.get("children", []):
            if c.get("type") == "FRAME":
                out[c["name"]] = c["id"]
    return out


def label_nodes(fid):
    """Return [(id, name, x, y)] for sidebar label texts inside a frame."""
    node = L.call("get_node", id=fid, depth=4)
    found = []

    def walk(n):
        for c in n.get("children", []):
            if c.get("type") == "TEXT":
                ch = (c.get("characters") or "").strip()
                if ch in ICON_FOR:
                    found.append((c["id"], ch))   # chars, not layer name
            walk(c)

    walk(node)
    return found


def build(fid, nodes, prodi_accent):
    ops = []
    for i, (nid, label) in enumerate(nodes):
        key = ICON_FOR[label]
        n = L.call("get_node", id=nid, depth=0)
        x, y = n.get("x", 0), n.get("y", 0)
        # active row? check the text colour
        fills = n.get("fills") or [{}]
        color = (fills[0].get("color") or "").upper()
        active = color.startswith("#2F2A95")
        zone_prodi = None
        icol = IND if active else GREY
        # prodi inertia: rows whose y falls under the orange/accent section header
        ops.append({"command": "vector.create", "as": f"i{i}", "params": {
            "parent": fid, "x": max(0, x - 22), "y": y + 2,
            "path": ICONS[key],
            "name": f"ico {key}",
            "props": {"stroke": icol, "strokeWeight": 1.6,
                      "strokeCap": "round", "strokeJoin": "round", "fill": "none"},
        }})
        # shift the label right by 22 so the icon column is clean
        ops.append({"command": "node.set", "params": {
            "ids": [nid], "x": x + 22, "width": max(100, 150),
        }})
    return ops


if __name__ == "__main__":
    ids = frame_ids()
    total = 0
    for name, fid in ids.items():
        if name not in SIDEBAR_FRAMES:
            continue
        nodes = label_nodes(fid)
        if not nodes:
            print(f"{name}: no sidebar rows")
            continue
        ops = build(fid, nodes, ORN)
        res = L.batch(ops, label=f"Icons {name}")
        bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
        print(f"{name}: {len(nodes)} rows, {len(ops)} ops, failed {len(bad)}")
        for b in bad[:3]:
            print("  ", b)
        total += len(nodes)
    print("total rows iconified:", total)

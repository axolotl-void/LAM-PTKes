"""Centre each sidebar icon on its label's real bounding box.

`vector.create` places the layer at (x + pathBox.x, y + pathBox.y), so the
path's own offset has to be subtracted or every icon sits low. Here the path
bbox is measured and the icon is aligned so its visible centre matches the
label's optical centre (label box centre, corrected for cap-height).
"""
import re
from icons import ICONS
from live_client import L

ICON_FOR = {
    "Dashboard": "dashboard", "Ringkasan 8 Kriteria": "summary",
    "Ringkasan & Kriteria": "summary", "Identitas UPPS & Prodi": "identity",
    "Periode Akreditasi": "period", "Dosen & Tendik": "lecturer",
    "Keuangan": "finance", "Kerja Sama": "partner",
    "Pengguna & Hak Akses": "access", "Mahasiswa": "student",
    "Kurikulum & MK": "curriculum", "Sarana & Wahana": "facility",
    "Pustaka & Jurnal": "library", "Uji Kompetensi": "competency",
    "Survei & Tracer": "survey", "Data Dukung 38 Tabel": "archive",
    "Dokumen Pendukung": "document", "Konsistensi": "consistency",
}
SIDEBAR_FRAMES = {"01 · Dashboard": 2, "02 · Data Dukung": 228,
                  "03 · Form Tabel 5": 423, "04 · Sidebar State": 679,
                  "04b · Sidebar Keperawatan": 758}


def path_bbox(d):
    """Rough integer bbox of an SVG path: min/max of every coordinate pair."""
    nums = [float(n) for n in re.findall(r"-?\d+(?:\.\d+)?", d)]
    xs, ys = nums[0::2], nums[1::2]
    return min(xs), min(ys), max(xs), max(ys)


def scan(fid):
    node = L.call("get_node", id=fid, depth=7)
    icons, labels = [], []

    def walk(n):
        for c in n.get("children", []):
            t, nm = c.get("type"), (c.get("name") or "")
            ch = c.get("characters") or ""
            y, x = c.get("y") or 0, c.get("x") or 0
            if t == "VECTOR" and nm.startswith("ico ") and x < 60 and 150 < y < 800:
                icons.append({"id": c["id"], "key": nm[4:], "y": y})
            elif (t == "TEXT" and ch.strip() in ICON_FOR and x < 60
                  and (c.get("width") or 0) >= 140):
                labels.append({"id": c["id"], "text": ch.strip(),
                               "y": y, "h": c.get("height") or 13,
                               "size": c.get("fontSize") or 11})
            walk(c)

    walk(node)
    return icons, labels


if __name__ == "__main__":
    for name, fid in SIDEBAR_FRAMES.items():
        icons, labels = scan(fid)
        ops = []
        for ic in icons:
            # nearest label on the same row
            near = min(labels, key=lambda l: abs(l["y"] - ic["y"]), default=None)
            if not near or abs(near["y"] - ic["y"]) > 20:
                continue
            key = ic["key"] if ic["key"] in ICONS else ICON_FOR.get(near["text"])
            if not key:
                continue
            _, miny, _, maxy = path_bbox(ICONS[key])
            cy = (miny + maxy) / 2
            # optical centre of the label: box centre, nudged up by the
            # descent that sits under an 11px cap-height line
            target = near["y"] + near["h"] / 2 - 1
            new_y = round(target - cy, 1)
            ops.append({"command": "node.set",
                        "params": {"ids": [ic["id"]], "y": new_y}})
        if not ops:
            print(f"{name}: nothing")
            continue
        res = L.batch(ops, label=f"Center icons {name}")
        bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
        print(f"{name}: {len(ops)} icons centred, failed {len(bad)}")
        for b in bad[:2]:
            print("   ", b)

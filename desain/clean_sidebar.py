"""Final sidebar cleanup, LIVE.

1. delete stray duplicate "Mahasiswa" label + its icon (sits above the PRODI header)
2. delete the empty 1px dot placeholders (replaced by icons)
3. optically centre each icon on its label row
"""
from live_client import L

SIDEBAR_FRAMES = {"01 · Dashboard": 2, "02 · Data Dukung": 228,
                  "03 · Form Tabel 5": 423, "04 · Sidebar State": 679,
                  "04b · Sidebar Keperawatan": 758}

NAV_LABELS = {
    "Dashboard", "Ringkasan 8 Kriteria", "Ringkasan & Kriteria",
    "Identitas UPPS & Prodi", "Periode Akreditasi", "Dosen & Tendik",
    "Keuangan", "Kerja Sama", "Pengguna & Hak Akses",
    "Mahasiswa", "Kurikulum & MK", "Sarana & Wahana", "Pustaka & Jurnal",
    "Uji Kompetensi", "Survei & Tracer",
    "Data Dukung 38 Tabel", "Dokumen Pendukung", "Konsistensi",
}
ICON_OFFSET = -2   # icon top relative to label top


def scan(fid):
    node = L.call("get_node", id=fid, depth=7)
    icons, labels, dots, headers = [], [], [], []

    def walk(n):
        for c in n.get("children", []):
            t = c.get("type")
            nm = c.get("name") or ""
            ch = (c.get("characters") or "")
            y = c.get("y") or 0
            x = c.get("x") or 0
            w = c.get("width") or 0
            if t == "VECTOR" and nm.startswith("ico ") and x < 60 and 150 < y < 800:
                icons.append({"id": c["id"], "y": y})
            elif t == "TEXT" and ch.strip() in NAV_LABELS and x < 60 and w == 150:
                labels.append({"id": c["id"], "text": ch.strip(), "y": y})
            elif t == "TEXT" and not ch.strip() and w <= 2:
                dots.append(c["id"])
            elif t == "TEXT" and ch.strip().startswith("PRODI:"):
                headers.append({"id": c["id"], "text": ch.strip(), "y": y})
        # recurse
        for c in n.get("children", []):
            walk(c)

    walk(node)
    return icons, labels, dots, headers


def cleanup(fid, name):
    icons, labels, dots, headers = scan(fid)
    ops = []
    drop_icons = set()

    # 1. stray duplicate: a nav label ABOVE the PRODI header that duplicates a
    #    label already present BELOW it.
    if headers:
        hdr_y = headers[0]["y"]
        below = {(l["text"]) for l in labels if l["y"] >= hdr_y}
        for l in labels:
            if l["y"] < hdr_y and l["text"] in below:
                ops.append({"command": "edit.delete", "params": {"ids": [l["id"]]}})
                # its icon is the closest one
                near = min(icons, key=lambda i: abs(i["y"] - l["y"]), default=None)
                if near and abs(near["y"] - l["y"]) < 24:
                    drop_icons.add(near["id"])
                    ops.append({"command": "edit.delete", "params": {"ids": [near["id"]]}})

    # 2. dot placeholders
    for d in dots:
        ops.append({"command": "edit.delete", "params": {"ids": [d]}})

    # 3. centre icons on their labels
    live_icons = [i for i in icons if i["id"] not in drop_icons]
    live_labels = [l for l in labels
                   if not any(o["params"].get("ids") == [l["id"]] for o in ops)]
    for ic in live_icons:
        near = min(live_labels, key=lambda l: abs(l["y"] - ic["y"]), default=None)
        if near and abs(near["y"] - ic["y"]) < 26:
            ops.append({"command": "node.set",
                        "params": {"ids": [ic["id"]], "y": near["y"] + ICON_OFFSET}})

    if not ops:
        print(f"{name}: nothing to do")
        return
    res = L.batch(ops, label=f"Clean {name}")
    bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
    print(f"{name}: {len(ops)} ops (icons {len(icons)}, labels {len(labels)}, "
          f"dots {len(dots)}), failed {len(bad)}")
    for b in bad[:3]:
        print("   ", b)


if __name__ == "__main__":
    for name, fid in SIDEBAR_FRAMES.items():
        cleanup(fid, name)

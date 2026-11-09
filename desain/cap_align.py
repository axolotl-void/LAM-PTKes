"""Align icon centres to cap-height centres (not text bbox centres).

Text bbox includes descenders (g, p, y) and ascenders, so centring on it makes
icons look low. Cap height for 11 px Helvetica Neue is ~7.67 px; the icon's
optical centre is matched to capTop + capHeight/2.
"""
import numpy as np
from PIL import Image

from live_client import L

SIDEBAR_FRAMES = {"01 · Dashboard": 2, "02 · Data Dukung": 228,
                  "03 · Form Tabel 5": 423, "04 · Sidebar State": 679,
                  "04b · Sidebar Keperawatan": 758}
SCALE = 3
CAP_H = 7.67      # measured: 11px Helvetica Neue cap-height
TOL = 0.35


def scan(fid):
    node = L.call("get_node", id=fid, depth=7)
    icons, labels = [], []

    def walk(n):
        for c in n.get("children", []):
            t, nm = c.get("type"), (c.get("name") or "")
            ch = c.get("characters") or ""
            x, y = c.get("x") or 0, c.get("y") or 0
            if t == "VECTOR" and nm.startswith("ico ") and x < 60 and 150 < y < 800:
                icons.append({"id": c["id"], "y": y})
            elif (t == "TEXT" and ch.strip() and x < 60
                  and (c.get("width") or 0) >= 140 and 150 < y < 800):
                labels.append({"id": c["id"], "text": ch.strip(), "y": y})
            walk(c)

    walk(node)
    return sorted(icons, key=lambda i: i["y"]), sorted(labels, key=lambda l: l["y"])


def ink_pairs(png):
    a = np.asarray(Image.open(png).convert("L"))
    def band(x0, x1):
        return (a[:, x0 * SCALE:x1 * SCALE] < 190).sum(axis=1)
    lb, ic = band(44, 210), band(22, 40)
    on, out, y, H = lb > 0, [], 150 * SCALE, 800 * SCALE
    while y < H:
        if on[y]:
            s = y
            while y < H and on[y]:
                y += 1
            if y - s >= 3:
                yt = np.nonzero(lb[s:y])[0]
                yi = np.nonzero(ic[s:y])[0]
                if len(yt) and len(yi):
                    out.append(((s + yt.min()) / SCALE,          # cap top
                                (s + (yi.min() + yi.max()) / 2) / SCALE))
        y += 1
    return out


def fix(fid):
    png = f"/tmp/cap_{fid}.png"
    L.call("export", format="png", scale=SCALE, ids=[fid], path=png)
    pairs = ink_pairs(png)
    icons, labels = scan(fid)
    # keep only render lines that line up with a nav label AND an icon
    keep = []
    for cap_top, ico_c in pairs:
        lab = min(labels, key=lambda l: abs(l["y"] - cap_top), default=None)
        ic = min(icons, key=lambda i: abs(i["y"] + 2 - ico_c), default=None)
        if lab and ic and abs(lab["y"] - cap_top) < 6 and abs(ic["y"] - ico_c) < 10:
            keep.append((cap_top, ico_c, ic, lab))
    if len(keep) != len(icons):
        print(f"  skip (matched {len(keep)} of {len(icons)} icons)")
        return [], []
    ops, report = [], []
    for cap_top, ico_c, ic, lab in keep:
        target = cap_top + CAP_H / 2
        d = target - ico_c
        if abs(d) >= TOL:
            ops.append({"command": "node.set",
                        "params": {"ids": [ic["id"]], "y": round(ic["y"] + d, 1)}})
            report.append((lab["text"], round(d, 2)))
    return ops, report


if __name__ == "__main__":
    for name, fid in SIDEBAR_FRAMES.items():
        ops, report = fix(fid)
        if not ops:
            print(f"{name}: aligned")
            continue
        res = L.batch(ops, label=f"Cap-align {name}")
        bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
        print(f"{name}: {len(ops)} nudged, failed {len(bad)}")
        for t, d in report:
            print(f"    {t[:28]:30s} {d:+.2f}")

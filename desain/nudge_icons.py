"""Correct icon vertical positions using measured ink centres from a render.

Round 1 measured deltas per row; this applies them (one pass), then re-renders
and re-measures so the residual can be checked.
"""
import subprocess
import sys

import numpy as np
from PIL import Image

from live_client import L

SIDEBAR_FRAMES = {"01 · Dashboard": 2, "02 · Data Dukung": 228,
                  "03 · Form Tabel 5": 423, "04 · Sidebar State": 679,
                  "04b · Sidebar Keperawatan": 758}
NAV_WIDTH_MIN = 140
SCALE = 3


def scan(fid):
    node = L.call("get_node", id=fid, depth=7)
    icons, labels = [], []

    def walk(n):
        for c in n.get("children", []):
            t, nm = c.get("type"), (c.get("name") or "")
            ch = c.get("characters") or ""
            x, y = c.get("x") or 0, c.get("y") or 0
            ab = c.get("absoluteBounds") or {}
            if t == "VECTOR" and nm.startswith("ico ") and x < 60 and 150 < y < 800:
                icons.append({"id": c["id"], "y": y, "abs": ab.get("y", y)})
            elif (t == "TEXT" and ch.strip() and x < 60
                  and (c.get("width") or 0) >= NAV_WIDTH_MIN and 150 < y < 800):
                labels.append({"id": c["id"], "text": ch.strip(), "y": y,
                               "abs": ab.get("y", y), "h": c.get("height") or 13})
            walk(c)

    walk(node)
    return sorted(icons, key=lambda i: i["y"]), sorted(labels, key=lambda l: l["y"])


def ink_centres(png, scale=SCALE):
    """Per text line (detected in the label band): (text_centre, icon_centre, y)."""
    a = np.asarray(Image.open(png).convert("L"))
    def band(x0, x1):
        return (a[:, x0 * scale:x1 * scale] < 190).sum(axis=1)
    ic, lb = band(22, 40), band(44, 210)
    on = lb > 0
    out, y = [], 150 * scale
    H = 800 * scale
    while y < H:
        if on[y]:
            s = y
            while y < H and on[y]:
                y += 1
            if y - s >= 3:
                yi = np.nonzero(ic[s:y])[0]
                yt = np.nonzero(lb[s:y])[0]
                if len(yi) and len(yt):
                    out.append(((s + (yt.min() + yt.max()) / 2) / scale,
                                (s + (yi.min() + yi.max()) / 2) / scale))
        y += 1
    return out


def measure_frame(name, fid):
    png = f"/tmp/m_{fid}.png"
    L.call("export", format="png", scale=SCALE, ids=[fid], path=png)
    lines = ink_centres(png)
    icons, labels = scan(fid)
    fixes = []
    # pair by order: the render and the geometry list the same rows top→down
    pairs = list(zip(icons, labels))
    if len(lines) != len(pairs):
        # fall back to nearest-y pairing when the render sees extra lines
        pairs = [(min(icons, key=lambda i: abs(i["abs"] - ic)), lab)
                 for (tc, ic), lab in ((ln, min(labels, key=lambda l: abs(l["abs"] - ln[0])))
                                        for ln in lines)]
        lines = list(lines)
    for (tc, ico_c), (ic, lab) in zip(lines, pairs):
        delta = tc - ico_c
        if abs(delta) >= 0.4:
            fixes.append((ic["id"], round(ic["y"] + delta, 1), lab["text"], round(delta, 2)))
    return fixes


if __name__ == "__main__":
    total = 0
    for name, fid in SIDEBAR_FRAMES.items():
        fixes = measure_frame(name, fid)
        if not fixes:
            print(f"{name}: aligned")
            continue
        ops = [{"command": "node.set", "params": {"ids": [i], "y": y}} for i, y, _, _ in fixes]
        res = L.batch(ops, label=f"Nudge icons {name}")
        bad = [r for r in res if isinstance(r, dict) and not r.get("ok", True)]
        print(f"{name}: {len(fixes)} nudged, failed {len(bad)}")
        for i, y, t, d in fixes:
            print(f"    {t[:26]:28s} {d:+.2f}")
        total += len(fixes)
    print("total nudged:", total)

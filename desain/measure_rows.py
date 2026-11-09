"""Measure icon ink vs text ink centres from a real render, per sidebar row."""
import sys
from PIL import Image

import numpy as np


def ink_bbox(img, x0, x1, y0, y1, thresh=200):
    a = np.asarray(img.convert("L"))[y0:y1, x0:x1]
    mask = a < thresh
    ys, xs = np.nonzero(mask)
    if len(ys) == 0:
        return None
    return (x0 + xs.min(), y0 + ys.min(), x0 + xs.max(), y0 + ys.max())


def report(path, scale=3):
    img = Image.open(path)
    W, H = img.size
    # icon column: frame x 22..38 ; label column 44..200 (page units)
    rows = []
    for y0 in range(0, H, 1):
        pass  # placeholder

    return img


def row_centres(path, scale=3, icon_x=(22, 40), label_x=(44, 210), y_start=150, y_end=800,
                step_px=8):
    img = Image.open(path).convert("L")
    a = np.asarray(img)
    out = []
    for y in range(y_start * scale, y_end * scale, step_px):
        pass
    return out


if __name__ == "__main__":
    path = sys.argv[1]
    scale = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    img = Image.open(path).convert("L")
    a = np.asarray(img)
    H, W = a.shape

    # ink presence per row inside each column band
    def band(x0, x1):
        sub = a[:, x0 * scale:x1 * scale]
        return (sub < 190).sum(axis=1)

    ic = band(22, 40)
    lb = band(44, 210)

    # segment into text lines using the label band
    on = lb > 0
    lines = []
    y = 150 * scale
    while y < 800 * scale:
        if on[y]:
            s = y
            while y < 800 * scale and on[y]:
                y += 1
            lines.append((s, y))
        y += 1

    print(f"{'row':3s} {'icon c':>8s} {'text c':>8s} {'delta':>7s}  text")
    for s, e in lines:
        if e - s < 3:
            continue
        ys_i = np.nonzero(ic[s:e])[0]
        ys_t = np.nonzero(lb[s:e])[0]
        if not len(ys_i) or not len(ys_t):
            continue
        ci = s + (ys_i.min() + ys_i.max()) / 2
        ct = s + (ys_t.min() + ys_t.max()) / 2
        print(f"{s/scale:7.1f} {ci/scale:8.2f} {ct/scale:8.2f} {(ci-ct)/scale:+7.2f}")

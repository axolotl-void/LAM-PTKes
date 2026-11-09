"""Ikon stroke 16x16 untuk sidebar. Digambar sebagai vector FigCraft (stroke only)."""

ICONS = {
    # grid 2x2
    "dashboard":  "M2 2H7V7H2ZM9 2H14V7H9ZM2 9H7V14H2ZM9 9H14V14H9Z",
    # list + centang (dipakai sekali saja, bukan grid)
    "summary":    "M5.2 3H14.5M5.2 8H14.5M5.2 13H10.5M1.6 2.2L2.7 3.3L4.3 1.5M1.6 7.2L2.7 8.3L4.3 6.5",
    # gedung + pintu
    "identity":   "M3 14V3.5L8 1.5L13 3.5V14M6 14V9.5H10V14M6 5H7M9 5H10M6 7H7M9 7H10",
    # kalender
    "period":     "M2 4.5H14V14H2ZM2 8H14M5 2V5M11 2V5",
    # orang + bahu
    "lecturer":   "M8 7.5A2.5 2.5 0 1 0 8 2.5A2.5 2.5 0 1 0 8 7.5ZM2.5 14C2.5 10.8 4.5 9 8 9C11.5 9 13.5 10.8 13.5 14",
    # dompet: badan + flap + kancing
    "finance":    "M2 5.5H12.5A1.5 1.5 0 0 1 14 7V12.5A1.5 1.5 0 0 1 12.5 14H3.5A1.5 1.5 0 0 1 2 12.5ZM2 5.5V3.5H10.5M11 9.6H13.2",
    # dua rantai bertaut (kerja sama)
    "partner":    "M6.5 9.5L9.5 6.5M6 4.5H4.5A3 3 0 0 0 4.5 10.5H6M10 4.5H11.5A3 3 0 0 1 11.5 10.5H10",
    # gembok
    "access":     "M4 7V5A4 4 0 0 1 12 5V7M2.5 7H13.5V14H2.5ZM8 9.5V11.5",
    # topi wisuda
    "student":    "M1 5.5L8 2L15 5.5L8 9ZM4 7.3V10.5C4 11.9 5.8 13 8 13C10.2 13 12 11.9 12 10.5V7.3",
    # buku bergaris (kurikulum = daftar MK), beda siluet dari grid
    "curriculum": "M3 1.5H13V14.5H3ZM3 1.5V14.5M5.5 5H11M5.5 8H11M5.5 11H9",
    # labu erlenmeyer
    "facility":   "M6.5 1.5H9.5M7 1.5V6.5L3 13H13L9 6.5V1.5M4.6 10H11.4",
    # buku terbuka
    "library":    "M8 4.5C6.5 3.2 4.5 3 2 3.2V12.5C4.5 12.3 6.5 12.5 8 13.8C9.5 12.5 11.5 12.3 14 12.5V3.2C11.5 3 9.5 3.2 8 4.5M8 4.5V13.8",
    # clipboard + centang (uji kompetensi)
    "competency": "M4 3.5H3A1.5 1.5 0 0 0 1.5 5V13A1.5 1.5 0 0 0 3 14.5H13A1.5 1.5 0 0 0 14.5 13V5A1.5 1.5 0 0 0 13 3.5H12M4 3.5V2A1 1 0 0 1 5 1H11A1 1 0 0 1 12 2V3.5H4ZM5.2 9L7 10.8L11 6.8",
    # grafik batang
    "survey":     "M2.5 14V9M8 14V4M13.5 14V7M1 14H15",
    # arsip bertumpuk (dipakai untuk Data Dukung, bukan grid)
    "archive":    "M1.5 3H14.5V6H1.5ZM2.8 6H13.2V13.5H2.8ZM6.5 8.5H9.5",
    # dokumen
    "document":   "M3.5 1.5H9.5L12.5 4.5V14.5H3.5ZM9.5 1.5V4.5H12.5M6 7.5H10M6 10.5H10",
    # perisai centang
    "consistency": "M8 1.5L13.5 3.5V8C13.5 11 11 13 8 14.5C5 13 2.5 11 2.5 8V3.5ZM5.8 7.8L7.4 9.4L10.6 6.2",
}


def icon_ops(op, parent, x, y, key, color, sw=1.6):
    """op = callable(command, **params) -> id. Draws one icon vector at (x, y)."""
    return op("vector.create", parent=parent, x=x, y=y, path=ICONS[key],
              name=f"ico {key}",
              props={"stroke": color, "strokeWeight": sw, "strokeCap": "round",
                     "strokeJoin": "round", "fill": "none"})

"""Tarik spesifikasi field dari docx jadi JSON.

Docx ini sumber kebenaran tipe & asal field. Urutan elemen docx harus dibaca
berurutan (paragraf + tabel) supaya tiap tabel tahu judul induknya — tabel
docx tidak menyimpan nama induknya sendiri.
"""
import json
import re
from docx import Document
from docx.table import Table
from docx.text.paragraph import Paragraph

SRC = "/Volumes/SSD GIK/07-ARSIP/LAM-PTKES/Spesifikasi Isi Sistem Akreditasi LAM-PTKes.docx"
OUT = "/Users/yogiprasetyasadewa/Documents/03_Proyek/LAM-PTKES/db/field-spec.json"


def iter_block(doc):
    body = doc.element.body
    for child in body.iterchildren():
        if child.tag.endswith("}p"):
            yield Paragraph(child, doc)
        elif child.tag.endswith("}tbl"):
            yield Table(child, doc)


doc = Document(SRC)
hasil = []
judul = ""
for blok in iter_block(doc):
    if isinstance(blok, Paragraph):
        t = blok.text.strip()
        if t:
            judul = t
        continue
    rows = [[c.text.strip() for c in r.cells] for r in blok.rows]
    if not rows:
        continue
    hasil.append({"judul": judul, "header": rows[0], "rows": rows[1:]})

json.dump(hasil, open(OUT, "w"), ensure_ascii=False, indent=1)

# ringkas supaya bisa diperiksa tanpa membuka 55 tabel
for i, h in enumerate(hasil):
    jenis = "MASTER" if h["header"][0].lower() == "field" else ("FIELD-ISIAN" if "field isian" in h["header"][0].lower() else "?")
    print(f"{i:2} {jenis:12} {len(h['rows']):3} baris  | {h['judul'][:70]}")

# FLOW-11 PDF renderer probe

The prior Dompdf artifact was independently rendered and visibly showed reversed/broken Persian/Dari text, including the title and table labels.

A minimal mPDF 8.3.1 probe using the built-in DejaVu Sans font with `lang="fa"`, `dir="rtl"`, `autoScriptToLang`, and `autoLangToFont` was rendered to `flow11_mpdf_probe.png`. The visual result showed connected Persian glyphs, natural right-to-left word order, readable mixed Latin/number content (`FLOW-11`, `DEC-001`, meeting number 11), and a readable ISO timestamp. No manual character reversal was used.

This supports replacing only the final Minutes PDF renderer with mPDF while preserving the Minutes domain and canonical payload.

"""Finalize owner review styling and rebuild the client-ready public archive."""

from __future__ import annotations

from pathlib import Path
import re
import runpy
import zipfile


ROOT = Path(__file__).parent
ASSETS = ROOT / "assets"

runpy.run_path(str(ROOT / "build_review.py"), run_name="__main__")

FAQ_OVERRIDE_CSS = """/* Fresh owner-review override; versioned to avoid stale preview caches. */
.faq details > summary h2 {
  color: #c9a063 !important;
  transition: color .18s ease;
}
.faq details > summary:hover h2,
.faq details[open] > summary h2 {
  color: #e0b578 !important;
}
"""

(ASSETS / "owner-review-v2.css").write_text(FAQ_OVERRIDE_CSS, encoding="utf-8")

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:
        continue
    marker = '<link rel="stylesheet" href="/assets/review-notes.css">'
    addition = marker + '<link rel="stylesheet" href="/assets/owner-review-v2.css">'
    if marker not in html:
        raise RuntimeError(f"Review stylesheet marker not found in {page}")
    html = html.replace(marker, addition, 1)
    page.write_text(html, encoding="utf-8")

public_root_files = {
    ".htaccess",
    "404.html",
    "favicon.ico",
    "index.html",
    "llms.txt",
    "robots.txt",
    "sitemap.xml",
}
public_page_directories = {
    "about",
    "contact",
    "faq",
    "growth-audit",
    "growth-package",
    "how-it-works",
    "privacy",
    "services",
    "terms",
    "thank-you",
}
excluded_assets = {"config.js", "site.js", "site.css", "site-dark.js"}

public_files: list[Path] = []
for file in sorted(ROOT.rglob("*")):
    if not file.is_file():
        continue
    relative = file.relative_to(ROOT)
    if relative.as_posix() in public_root_files:
        public_files.append(file)
    elif relative.parts[0] in public_page_directories:
        public_files.append(file)
    elif relative.parts[0] == "assets" and file.name not in excluded_assets:
        public_files.append(file)

vendor_pattern = re.compile(r"(?:GoHighLevel|HighLevel|\bGHL\b)", re.IGNORECASE)
for file in public_files:
    if file.suffix.lower() not in {".html", ".css", ".js", ".txt", ".xml", ".htaccess"}:
        continue
    content = file.read_text(encoding="utf-8", errors="ignore")
    if vendor_pattern.search(content):
        raise RuntimeError(f"Public vendor reference found in {file.relative_to(ROOT)}")

archive = ROOT / "TopShelfBuild-client-ready.zip"
with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in public_files:
        bundle.write(file, file.relative_to(ROOT).as_posix())

print(f"owner review fix complete: {archive.name}")

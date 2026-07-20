"""Production build entry point for the contractor-focused branded site."""
from pathlib import Path
import runpy

ROOT = Path(__file__).parent
runpy.run_path(str(ROOT / "build_site.py"), run_name="__main__")

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:
        continue
    html = html.replace(
        '<link rel="stylesheet" href="/assets/site-dark.css">',
        '<link rel="stylesheet" href="/assets/site-dark.css"><link rel="stylesheet" href="/assets/brand-fixes.css">',
        1,
    )
    page.write_text(html, encoding="utf-8")

print("final build complete")

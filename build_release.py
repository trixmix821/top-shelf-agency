"""Current release build: branded site plus approved CTA copy."""
from pathlib import Path
import runpy

ROOT = Path(__file__).parent
runpy.run_path(str(ROOT / "build_final.py"), run_name="__main__")

old_cta = "Get My Free Growth Audit"
new_cta = "Get A Free Audit"

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:
        continue
    page.write_text(html.replace(old_cta, new_cta), encoding="utf-8")

print("release copy complete")

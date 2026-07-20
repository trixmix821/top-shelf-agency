"""Generate the site, then apply the verified Top Shelf brand and naming layer."""
from pathlib import Path
import runpy

ROOT = Path(__file__).parent
runpy.run_path(str(ROOT / "build_pages.py"), run_name="__main__")

theme_init = "<script>(function(){try{var t=localStorage.getItem('theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t)}catch(e){}})()</script>"

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:  # legacy redirect documents
        continue
    html = html.replace('lang="en"', 'lang="en" data-theme="dark"', 1)
    html = html.replace('<head><meta charset="utf-8">', '<head><meta charset="utf-8">' + theme_init, 1)
    html = html.replace('<link rel="stylesheet" href="/assets/site.css">', '<link rel="stylesheet" href="/assets/brand.css"><link rel="stylesheet" href="/assets/site-dark.css">', 1)
    html = html.replace('<script src="/assets/site.js"></script>', '<script src="/assets/site-dark.js"></script>', 1)
    html = html.replace("Steve", "Stephen")
    page.write_text(html, encoding="utf-8")

llms = ROOT / "llms.txt"
llms.write_text(llms.read_text(encoding="utf-8").replace("Steve", "Stephen"), encoding="utf-8")
print("brand layer complete")

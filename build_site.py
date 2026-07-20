"""Generate the site, then apply the verified Top Shelf brand and naming layer."""
from pathlib import Path
import runpy

ROOT = Path(__file__).parent
runpy.run_path(str(ROOT / "build_pages.py"), run_name="__main__")

theme_button = '''<button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to light mode">
<svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
<svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
</button>'''

theme_init = "<script>(function(){try{var t=localStorage.getItem('theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t)}catch(e){}})()</script>"

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:  # legacy redirect documents
        continue
    html = html.replace('lang="en"', 'lang="en" data-theme="dark"', 1)
    html = html.replace('<head><meta charset="utf-8">', '<head><meta charset="utf-8">' + theme_init, 1)
    html = html.replace('<link rel="stylesheet" href="/assets/site.css">', '<link rel="stylesheet" href="/assets/brand.css"><link rel="stylesheet" href="/assets/site-dark.css">', 1)
    html = html.replace('<a class="btn" data-event="audit_cta_click" href="/growth-audit/">Get My Free Growth Audit</a><button id="navToggle"', theme_button + '<a class="btn nav-cta" data-event="audit_cta_click" href="/growth-audit/">Get My Free Growth Audit</a><button class="nav-toggle" id="navToggle"', 1)
    html = html.replace('<script src="/assets/site.js"></script>', '<script src="/assets/site-dark.js"></script>', 1)
    html = html.replace("Steve", "Stephen")
    page.write_text(html, encoding="utf-8")

llms = ROOT / "llms.txt"
llms.write_text(llms.read_text(encoding="utf-8").replace("Steve", "Stephen"), encoding="utf-8")
print("brand layer complete")

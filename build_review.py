"""Apply owner review notes over the SEO-ready release and create a public-only bundle."""

from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path
import re
import runpy
import zipfile


ROOT = Path(__file__).parent
ASSETS = ROOT / "assets"

runpy.run_path(str(ROOT / "build_seo.py"), run_name="__main__")

REVIEW_DATE = datetime.fromtimestamp(
    (ROOT / "build_review.py").stat().st_mtime, tz=timezone.utc
).date().isoformat()

OLD_THEME_INIT = "<script>(function(){try{var t=localStorage.getItem('theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t)}catch(e){}})()</script>"
NEW_THEME_INIT = "<script>(function(){var r=document.documentElement,t=null;try{t=localStorage.getItem('top-shelf-theme')||localStorage.getItem('theme')}catch(e){}r.setAttribute('data-theme',t==='light'?'light':'dark')})()</script>"

REVIEW_CSS = """/* Owner review notes: bronze FAQ titles, larger homepage titles, dark-first UI. */
.faq summary h2{
  color:var(--brass);
  transition:color .18s ease;
}
.faq summary:hover h2,
.faq details[open] summary h2{
  color:var(--brass-strong);
}
.home-page main section:not(.hero) h2{
  font-size:clamp(2.05rem,3.8vw,3.15rem);
}
.home-page .answer-summary .eyebrow{
  font-size:.82rem;
  letter-spacing:.085em;
}
.home-page .answer-summary h2{
  font-size:clamp(2.2rem,4.2vw,3.4rem);
}
"""

PUBLIC_CONFIG = """/* Public website configuration. Never place private credentials in browser code. */
window.TOP_SHELF_CONFIG=Object.freeze({
  formEndpoint:"",
  calendarUrl:"",
  successUrl:"/thank-you/",
  consentCopy:"I agree that Top Shelf Agency LLC may contact me about my Growth Audit by phone, email, and text. Message and data rates may apply. Consent is not a condition of purchase. Reply STOP to opt out."
});
"""

PUBLIC_JS = r'''(function(){
  "use strict";
  var config=window.TOP_SHELF_CONFIG||{};
  var root=document.documentElement;
  var menu=document.getElementById("mobileMenu");
  var menuButton=document.getElementById("navToggle");
  var themeButton=document.getElementById("themeToggle");

  function updateThemeLabel(){
    if(themeButton){
      themeButton.setAttribute(
        "aria-label",
        root.getAttribute("data-theme")==="dark"?"Switch to light mode":"Switch to dark mode"
      );
    }
  }

  if(root.getAttribute("data-theme")!=="light"){
    root.setAttribute("data-theme","dark");
  }
  updateThemeLabel();

  if(themeButton){
    themeButton.addEventListener("click",function(){
      var next=root.getAttribute("data-theme")==="dark"?"light":"dark";
      root.setAttribute("data-theme",next);
      try{
        localStorage.setItem("top-shelf-theme",next);
        localStorage.removeItem("theme");
      }catch(e){}
      updateThemeLabel();
    });
  }

  if(menuButton&&menu){
    menuButton.addEventListener("click",function(){
      var open=menu.classList.toggle("open");
      menuButton.setAttribute("aria-expanded",String(open));
      menuButton.setAttribute("aria-label",open?"Close menu":"Open menu");
    });
  }
  if(menu){
    menu.querySelectorAll("a").forEach(function(link){
      link.addEventListener("click",function(){
        menu.classList.remove("open");
        if(menuButton)menuButton.setAttribute("aria-expanded","false");
      });
    });
  }

  function track(name){
    dispatchEvent(new CustomEvent("topshelf:conversion",{detail:{name:name,path:location.pathname}}));
  }
  document.querySelectorAll("[data-event]").forEach(function(element){
    element.addEventListener("click",function(){track(element.dataset.event);});
  });

  var keys=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"];
  var query=new URLSearchParams(location.search);
  keys.forEach(function(key){
    if(query.get(key))localStorage.setItem("tsa_"+key,query.get(key));
  });
  if(!localStorage.getItem("tsa_first"))localStorage.setItem("tsa_first",location.href);

  var form=document.getElementById("growthAuditForm");
  if(!form)return;
  var status=document.getElementById("formStatus");
  var started=false;
  keys.forEach(function(key){form.elements[key].value=localStorage.getItem("tsa_"+key)||"";});
  form.elements.landing_page.value=location.href;
  form.elements.first_touch_url.value=localStorage.getItem("tsa_first");
  if(config.consentCopy)document.getElementById("consentCopy").textContent=config.consentCopy;

  form.addEventListener("input",function(){
    if(!started){track("audit_form_start");started=true;}
  });
  form.addEventListener("submit",async function(event){
    event.preventDefault();
    status.className="status";
    if(!form.checkValidity()){
      form.reportValidity();
      status.textContent="Please complete the required fields.";
      status.classList.add("error");
      return;
    }
    if(form.elements.company_fax.value)return;
    if(!config.formEndpoint){
      status.textContent="Online requests are temporarily unavailable. Please email hello@topshelfagency.biz for now.";
      status.classList.add("error");
      return;
    }
    var button=form.querySelector('button[type="submit"]');
    button.disabled=true;
    button.textContent="Sending...";
    try{
      var payload=Object.fromEntries(new FormData(form));
      delete payload.company_fax;
      var response=await fetch(config.formEndpoint,{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify(payload)
      });
      if(!response.ok)throw new Error("Request failed");
      track("audit_form_completion");
      location.assign(config.successUrl||"/thank-you/");
    }catch(error){
      status.textContent="We could not send your request. Try again or email hello@topshelfagency.biz.";
      status.classList.add("error");
      button.disabled=false;
      button.textContent="Get A Free Audit";
    }
  });
}());
'''

ASSETS.mkdir(parents=True, exist_ok=True)
(ASSETS / "review-notes.css").write_text(REVIEW_CSS, encoding="utf-8")
(ASSETS / "public-config.js").write_text(PUBLIC_CONFIG, encoding="utf-8")
(ASSETS / "site-public.js").write_text(PUBLIC_JS, encoding="utf-8")

ABOUT_OLD = "<h2>Built around your operation</h2><p>GoHighLevel stays behind the scenes as the delivery platform; it is not custom software.</p>"
ABOUT_NEW = "<h2>Built around your operation</h2><p>The system is configured around your company\u2019s name, approved messages, and branded links. Homeowners interact with your business while the delivery platform remains behind the scenes.</p>"

PACKAGE_OLD = "Depending on what the audit confirms, the package may use missed-call text-back, forms, booking, follow-up, review requests, pipeline management, and reporting inside GoHighLevel. These are components\u2014not separate products."
PACKAGE_NEW = "Depending on what the audit confirms, the package may use missed-call text-back, forms, booking, follow-up, review requests, pipeline management, and reporting behind the scenes. These capabilities work together as one customer journey system."

for page in ROOT.rglob("*.html"):
    html = page.read_text(encoding="utf-8")
    if len(html) < 500:
        continue

    if OLD_THEME_INIT not in html:
        raise RuntimeError(f"Theme initializer not found in {page}")
    html = html.replace(OLD_THEME_INIT, NEW_THEME_INIT, 1)
    html = html.replace(
        '<link rel="stylesheet" href="/assets/brand-fixes.css">',
        '<link rel="stylesheet" href="/assets/brand-fixes.css"><link rel="stylesheet" href="/assets/review-notes.css">',
        1,
    )
    html = html.replace(
        '<script src="/assets/config.js"></script>',
        '<script src="/assets/public-config.js"></script>',
        1,
    )
    html = html.replace(
        '<script src="/assets/site-dark.js"></script>',
        '<script src="/assets/site-public.js"></script>',
        1,
    )
    html = html.replace('"dateModified":"2026-07-16"', f'"dateModified":"{REVIEW_DATE}"')
    html = html.replace("Site content last updated 2026-07-16.", f"Site content last updated {REVIEW_DATE}.")

    if page == ROOT / "index.html":
        html = html.replace("<body>", '<body class="home-page">', 1)
    if page == ROOT / "about" / "index.html":
        if ABOUT_OLD not in html:
            raise RuntimeError("About delivery-platform copy not found")
        html = html.replace(ABOUT_OLD, ABOUT_NEW, 1)
    if page == ROOT / "growth-package" / "index.html":
        if PACKAGE_OLD not in html:
            raise RuntimeError("Growth Package delivery-platform copy not found")
        html = html.replace(PACKAGE_OLD, PACKAGE_NEW, 1)

    page.write_text(html, encoding="utf-8")

sitemap = ROOT / "sitemap.xml"
sitemap.write_text(
    re.sub(r"<lastmod>\d{4}-\d{2}-\d{2}</lastmod>", f"<lastmod>{REVIEW_DATE}</lastmod>", sitemap.read_text(encoding="utf-8")),
    encoding="utf-8",
)

# Build a deployment-only archive. Internal build files and vendor setup notes stay out.
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

print(f"review notes complete: {archive.name}")

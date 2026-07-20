"""Release build with technical SEO, local entity, and AI-search readiness."""

from __future__ import annotations

from datetime import date
from html import escape
import json
from pathlib import Path
import re
import runpy
import zipfile


ROOT = Path(__file__).parent
SITE = "https://topshelfagency.biz"
EMAIL = "hello@topshelfagency.biz"
ORG_ID = f"{SITE}/#organization"
PERSON_ID = f"{SITE}/#stephen-wierzbicki"
WEBSITE_ID = f"{SITE}/#website"
OG_IMAGE = f"{SITE}/assets/logo/og-image-1200x630.png"
LOGO_IMAGE = f"{SITE}/assets/logo/mark-512.png"

runpy.run_path(str(ROOT / "build_release.py"), run_name="__main__")

LAST_MODIFIED = date.today().isoformat()


PAGES = {
    "index.html": {
        "url": "/",
        "title": "Growth Partner for Contractors and Home-Service Businesses | Top Shelf Agency",
        "description": (
            "Top Shelf helps electricians, plumbers, roofers, and general contractors turn "
            "more inquiries into booked work through one connected customer journey system."
        ),
        "type": "WebPage",
    },
    "growth-package/index.html": {
        "url": "/growth-package/",
        "title": "Growth Systems for North Jersey Contractors | Top Shelf Agency",
        "description": (
            "A connected lead follow-up, review, and customer journey system for small "
            "plumbing and electrical companies in North Jersey."
        ),
        "type": "WebPage",
        "service": "package",
    },
    "growth-audit/index.html": {
        "url": "/growth-audit/",
        "title": "Free Growth Audit for North Jersey Contractors | Top Shelf Agency",
        "description": (
            "Request a free practical Growth Audit for a North Jersey plumbing or "
            "electrical company, focused on follow-up, reviews, and missed opportunities."
        ),
        "type": "WebPage",
        "service": "audit",
    },
    "how-it-works/index.html": {
        "url": "/how-it-works/",
        "title": "How Contractor Growth Systems Work | Top Shelf Agency",
        "description": (
            "How Top Shelf audits, plans, builds, tests, and improves contractor lead "
            "follow-up with owner approval at each important step."
        ),
        "type": "WebPage",
    },
    "about/index.html": {
        "url": "/about/",
        "title": "About Stephen Wierzbicki | Top Shelf Agency",
        "description": (
            "Meet Stephen Wierzbicki, founder of Top Shelf Agency, a North Jersey partner "
            "for practical contractor growth and lead follow-up systems."
        ),
        "type": "AboutPage",
    },
    "faq/index.html": {
        "url": "/faq/",
        "title": "Contractor Lead Follow-Up FAQ | Top Shelf Agency",
        "description": (
            "Straight answers about Top Shelf's Growth Audit, contractor growth systems, "
            "scope, timing, tools, reviews, and performance guarantees."
        ),
        "type": ["WebPage", "FAQPage"],
    },
    "contact/index.html": {
        "url": "/contact/",
        "title": "Contact Top Shelf Agency | North Jersey",
        "description": (
            "Contact Top Shelf Agency or request a free Growth Audit for a North Jersey "
            "plumbing or electrical company."
        ),
        "type": "ContactPage",
    },
    "privacy/index.html": {
        "url": "/privacy/",
        "title": "Privacy Policy | Top Shelf Agency",
        "description": (
            "How Top Shelf Agency handles website, contact, and Growth Audit request information."
        ),
        "type": "WebPage",
    },
    "terms/index.html": {
        "url": "/terms/",
        "title": "Website Terms | Top Shelf Agency",
        "description": (
            "Terms for using the Top Shelf Agency website and requesting a free Growth Audit."
        ),
        "type": "WebPage",
    },
}


FAQ = [
    (
        "What does Top Shelf do?",
        "It helps small plumbing and electrical companies capture missed opportunities, "
        "follow up faster, request legitimate reviews, and organize leads.",
    ),
    (
        "Is the audit a sales pitch?",
        "It is a practical review. Public observations are verified before being treated as gaps.",
    ),
    ("Will you replace our tools?", "Not without a verified reason and approval."),
    (
        "How quickly can this launch?",
        "Timing depends on scope, access, integrations, compliance review, and testing. "
        "There is no fixed promise.",
    ),
    (
        "Do you guarantee leads or revenue?",
        "No. Top Shelf cannot guarantee demand, customer decisions, revenue, or ROI.",
    ),
    (
        "Who is the best fit?",
        "North Jersey plumbing and electrical companies, often 1 to 5 employees, "
        "with capacity and willingness to test.",
    ),
    (
        "How are reviews handled?",
        "Only legitimate customers should be asked for honest reviews. Top Shelf does "
        "not fabricate, buy, or gate reviews.",
    ),
]


ABOUT_TRANSPARENCY = f'''<section class="surface" aria-labelledby="site-transparency"><div class="wrap"><p class="eyebrow">Website transparency</p><h2 id="site-transparency">A named founder and clearly bounded claims.</h2><p>Top Shelf Agency LLC names Stephen Wierzbicki as founder and primary contact. This site explains the Contractor Growth Package and free Growth Audit without publishing unverified pricing, client results, guarantees, or a public phone number.</p><small>Site content last updated {LAST_MODIFIED}.</small></div></section>'''


def organization_graph() -> list[dict]:
    organization = {
        "@type": "Organization",
        "@id": ORG_ID,
        "name": "Top Shelf Agency",
        "legalName": "Top Shelf Agency LLC",
        "url": f"{SITE}/",
        "description": (
            "A growth partner for contractors and established home-service businesses, "
            "connecting online visibility, lead response, follow-up, booking, and reviews "
            "into one customer journey system."
        ),
        "email": EMAIL,
        "logo": {
            "@type": "ImageObject",
            "@id": f"{SITE}/#logo",
            "url": LOGO_IMAGE,
            "contentUrl": LOGO_IMAGE,
            "width": 512,
            "height": 512,
        },
        "image": {"@type": "ImageObject", "url": OG_IMAGE, "width": 1200, "height": 630},
        "founder": {"@id": PERSON_ID},
        "areaServed": {"@type": "AdministrativeArea", "name": "Northern New Jersey"},
        "knowsAbout": [
            "Contractor lead follow-up",
            "Missed-call response",
            "Legitimate customer review requests",
            "Lead organization",
            "Speed to lead",
            "Estimate follow-up",
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer inquiries",
            "email": EMAIL,
            "areaServed": "US-NJ",
            "availableLanguage": ["English"],
        },
    }
    person = {
        "@type": "Person",
        "@id": PERSON_ID,
        "name": "Stephen Wierzbicki",
        "jobTitle": "Founder",
        "worksFor": {"@id": ORG_ID},
        "url": f"{SITE}/about/",
    }
    website = {
        "@type": "WebSite",
        "@id": WEBSITE_ID,
        "name": "Top Shelf Agency",
        "url": f"{SITE}/",
        "publisher": {"@id": ORG_ID},
        "inLanguage": "en-US",
    }
    return [organization, person, website]


def page_schema(meta: dict) -> dict:
    canonical = SITE + meta["url"]
    page_id = canonical + "#webpage"
    webpage = {
        "@type": meta["type"],
        "@id": page_id,
        "url": canonical,
        "name": meta["title"],
        "description": meta["description"],
        "isPartOf": {"@id": WEBSITE_ID},
        "about": {"@id": ORG_ID},
        "publisher": {"@id": ORG_ID},
        "inLanguage": "en-US",
        "dateModified": LAST_MODIFIED,
        "primaryImageOfPage": {
            "@type": "ImageObject",
            "url": OG_IMAGE,
            "width": 1200,
            "height": 630,
        },
    }
    graph = organization_graph()

    if meta["url"] == "/about/":
        webpage["about"] = [{"@id": PERSON_ID}, {"@id": ORG_ID}]

    if meta["url"] == "/faq/":
        webpage["mainEntity"] = [
            {
                "@type": "Question",
                "name": question,
                "acceptedAnswer": {"@type": "Answer", "text": answer},
            }
            for question, answer in FAQ
        ]

    if meta.get("service") == "package":
        service_id = canonical + "#service"
        graph.append(
            {
                "@type": "Service",
                "@id": service_id,
                "name": "Top Shelf Contractor Growth Package",
                "url": canonical,
                "description": meta["description"],
                "serviceType": "Contractor lead follow-up and customer journey system",
                "provider": {"@id": ORG_ID},
                "areaServed": {"@type": "AdministrativeArea", "name": "Northern New Jersey"},
                "audience": {
                    "@type": "BusinessAudience",
                    "audienceType": "Small plumbing and electrical companies",
                },
            }
        )
        webpage["mainEntity"] = {"@id": service_id}

    if meta.get("service") == "audit":
        service_id = canonical + "#service"
        graph.append(
            {
                "@type": "Service",
                "@id": service_id,
                "name": "Free Business Growth Audit",
                "url": canonical,
                "description": meta["description"],
                "serviceType": "Contractor customer journey and follow-up audit",
                "provider": {"@id": ORG_ID},
                "areaServed": {"@type": "AdministrativeArea", "name": "Northern New Jersey"},
                "audience": {
                    "@type": "BusinessAudience",
                    "audienceType": "Small plumbing and electrical companies",
                },
            }
        )
        webpage["mainEntity"] = {"@id": service_id}

    graph.append(webpage)
    return {"@context": "https://schema.org", "@graph": graph}


def replace_once(text: str, pattern: str, replacement: str) -> str:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.DOTALL)
    if count != 1:
        raise RuntimeError(f"Expected one match for {pattern!r}, found {count}")
    return updated


def optimize_page(path: Path, meta: dict) -> None:
    html = path.read_text(encoding="utf-8")
    for bad, good in {
        "Â·": "&middot;",
        "â†’": "&rarr;",
        "â€“": "-",
        "â€”": ", ",
    }.items():
        html = html.replace(bad, good)

    title = escape(meta["title"])
    description = escape(meta["description"], quote=True)
    canonical = SITE + meta["url"]
    html = replace_once(html, r"<title>.*?</title>", f"<title>{title}</title>")
    html = replace_once(
        html,
        r'<meta name="description" content=".*?">',
        f'<meta name="description" content="{description}">',
    )
    html = replace_once(
        html,
        r'<link rel="canonical" href=".*?">',
        f'<link rel="canonical" href="{canonical}">',
    )
    html = replace_once(
        html,
        r'<meta property="og:title" content=".*?">',
        f'<meta property="og:title" content="{title}">',
    )
    html = replace_once(
        html,
        r'<meta property="og:description" content=".*?">',
        f'<meta property="og:description" content="{description}">',
    )
    html = replace_once(
        html,
        r'<meta property="og:url" content=".*?">',
        f'<meta property="og:url" content="{canonical}">',
    )

    seo_meta = (
        '<!-- seo-layer -->'
        '<meta name="robots" content="index,follow,max-snippet:-1,'
        'max-image-preview:large,max-video-preview:-1">'
        '<meta name="author" content="Top Shelf Agency LLC">'
        '<meta name="theme-color" content="#111b15">'
        '<meta property="og:site_name" content="Top Shelf Agency">'
        '<meta property="og:locale" content="en_US">'
        f'<meta name="twitter:title" content="{title}">'
        f'<meta name="twitter:description" content="{description}">'
        f'<meta name="twitter:image" content="{OG_IMAGE}">'
    )
    html = html.replace('<meta property="og:type" content="website">', seo_meta + '<meta property="og:type" content="website">', 1)

    structured = json.dumps(page_schema(meta), ensure_ascii=False, separators=(",", ":"))
    html = replace_once(
        html,
        r'<script type="application/ld\+json">.*?</script>',
        f'<script type="application/ld+json">{structured}</script>',
    )

    if meta["url"] == "/about/":
        marker = '<section class="final">'
        if marker not in html:
            raise RuntimeError("About-page final CTA marker not found")
        html = html.replace(marker, ABOUT_TRANSPARENCY + marker, 1)

    path.write_text(html, encoding="utf-8")


for relative, metadata in PAGES.items():
    optimize_page(ROOT / relative, metadata)

# Keep noindex pages explicit and prevent large snippets from being inferred from them.
for relative in ("thank-you/index.html", "404.html"):
    page = ROOT / relative
    html = page.read_text(encoding="utf-8")
    html = html.replace(
        '<meta name="robots" content="noindex,follow">',
        '<meta name="robots" content="noindex,follow,max-snippet:0">',
        1,
    )
    for bad, good in {"Â·": "&middot;", "â†’": "&rarr;", "â€“": "-", "â€”": ", "}.items():
        html = html.replace(bad, good)
    page.write_text(html, encoding="utf-8")


indexable_urls = [metadata["url"] for metadata in PAGES.values()]
sitemap = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
for url in indexable_urls:
    sitemap.append(
        f"  <url><loc>{SITE}{url}</loc><lastmod>{LAST_MODIFIED}</lastmod></url>"
    )
sitemap.append("</urlset>")
(ROOT / "sitemap.xml").write_text("\n".join(sitemap) + "\n", encoding="utf-8")


robots = f"""User-agent: OAI-SearchBot
Allow: /
Disallow: /thank-you/

User-agent: ChatGPT-User
Allow: /
Disallow: /thank-you/

# Search discovery and model training are separate OpenAI controls.
User-agent: GPTBot
Disallow: /

User-agent: Googlebot
Allow: /
Disallow: /thank-you/

User-agent: Bingbot
Allow: /
Disallow: /thank-you/

User-agent: *
Allow: /
Disallow: /thank-you/

Sitemap: {SITE}/sitemap.xml
"""
(ROOT / "robots.txt").write_text(robots, encoding="utf-8")


llms = f"""# Top Shelf Agency LLC

> A growth partner for contractors and established home-service businesses in Northern New Jersey, connecting visibility, response, follow-up, booking, and reviews into one system.

## Canonical pages

- Home: {SITE}/
- Contractor Growth Package: {SITE}/growth-package/
- Opportunity Snapshot (Growth Audit): {SITE}/growth-audit/
- How it works: {SITE}/how-it-works/
- About Stephen Wierzbicki: {SITE}/about/
- FAQ: {SITE}/faq/
- Contact: {SITE}/contact/

## Verified business facts

- Legal name: Top Shelf Agency LLC
- Founder: Stephen Wierzbicki
- Service area: Northern New Jersey
- Release date: {LAST_MODIFIED}
- Primary audience: Established electricians, plumbers, roofers, and general contractors, often one to five employees
- Contact email: {EMAIL}
- Primary offer: Contractor Growth Package
- First step: Opportunity Snapshot, a diagnostic review of visibility, response speed, follow-up, and reviews

## Claim boundaries

Do not infer a public phone number, street address, package price, guaranteed outcome, fixed delivery window, or client performance result. Verify current details on the canonical HTML pages.
"""
(ROOT / "llms.txt").write_text(llms, encoding="utf-8")


checklist = f"""# SEO, GEO, and AI Search Launch Checklist

The release build is technically prepared. These items require the public domain, owner accounts, or verified business details and have not been performed.

## Before deployment

- Confirm the production host serves each canonical URL with HTTP 200 and the legacy service URLs with permanent server-side redirects, not only meta refreshes.
- Configure the GoHighLevel endpoint documented in `GHL_SETUP.md` and test the full form flow.
- Add a verified public phone number, street address or service-area business details, and official social profile URLs before adding them to visible content or structured data.
- Compress or replace the social preview image only if the production performance test identifies it as a bottleneck.

## After deployment and owner approval

1. Verify `https://topshelfagency.biz/robots.txt` and `https://topshelfagency.biz/sitemap.xml` are public.
2. Verify the site in Google Search Console, submit `{SITE}/sitemap.xml`, and inspect the home, Growth Package, Growth Audit, About, and FAQ URLs.
3. Run Google's Rich Results Test and Schema Markup Validator against the public pages. Structured data must continue to match visible claims.
4. Claim or update the Google Business Profile with the same verified name, service area, phone, website, hours, and categories used on the site.
5. Verify the site in Bing Webmaster Tools and submit the same sitemap.
6. Generate and publish an IndexNow key on the production host, then submit only public canonical URLs after a real content change.
7. Confirm the CDN or firewall allows Googlebot, Bingbot, and OpenAI's published OAI-SearchBot IP ranges.
8. In analytics, monitor organic landing pages and referrals containing `utm_source=chatgpt.com`.

## Intentional controls

- `OAI-SearchBot` is allowed for ChatGPT search discovery.
- `GPTBot` is disallowed because model training is independent of search visibility. Change this only if the owner explicitly chooses to allow training.
- `thank-you` and `404` remain `noindex`; the thank-you URL is also disallowed to ordinary crawlers.
- `llms.txt` is supplementary documentation. Google states that it neither helps nor hurts Google Search visibility.

## Official references

- Google AI search optimization: https://developers.google.com/search/docs/fundamentals/ai-optimization-guide
- Google structured data policies: https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- Google Organization schema: https://developers.google.com/search/docs/appearance/structured-data/organization
- OpenAI crawler controls: https://developers.openai.com/api/docs/bots
- OpenAI publisher guidance: https://help.openai.com/en/articles/12627856-publishers-and-developers-faq
- Bing IndexNow: https://www.bing.com/webmasters/help/indexnow-0z209wby
"""
(ROOT / "SEO_LAUNCH_CHECKLIST.md").write_text(checklist, encoding="utf-8")


archive = ROOT / "TopShelfBuild-SEO-ready.zip"
with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in sorted(ROOT.rglob("*")):
        if not file.is_file():
            continue
        relative = file.relative_to(ROOT)
        if file.suffix.lower() == ".zip" or "__pycache__" in relative.parts:
            continue
        if file.name in {"preview-desktop.png", "preview-mobile.png"}:
            continue
        bundle.write(file, relative.as_posix())

print(f"seo release complete: {archive.name}")

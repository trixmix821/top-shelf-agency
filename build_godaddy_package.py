"""Build and validate the static upload package for GoDaddy Linux hosting."""

from __future__ import annotations

from hashlib import sha256
from html.parser import HTMLParser
import json
from pathlib import Path, PurePosixPath
import re
import runpy
from urllib.parse import unquote, urlsplit
import zipfile


ROOT = Path(__file__).parent
ASSETS = ROOT / "assets"
ARCHIVE = ROOT / "TopShelfAgency-GoDaddy-Deploy.zip"
CHECKSUM = ROOT / "TopShelfAgency-GoDaddy-Deploy.sha256.txt"

runpy.run_path(str(ROOT / "build_review_fix.py"), run_name="__main__")

HTACCESS = r"""DirectoryIndex index.html
Options -Indexes
ErrorDocument 404 /404.html

<IfModule mod_rewrite.c>
  RewriteEngine On

  # Consolidate retired service URLs into the current offer page.
  RewriteRule ^services(?:/.*)?$ https://topshelfagency.biz/growth-package/ [R=301,L,NC]

  # Match the canonical URLs declared in the site metadata.
  RewriteCond %{HTTPS} !=on [OR]
  RewriteCond %{HTTP_HOST} !^topshelfagency\.biz$ [NC]
  RewriteRule ^ https://topshelfagency.biz%{REQUEST_URI} [R=301,L]
</IfModule>

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json application/xml image/svg+xml
</IfModule>
"""

(ROOT / ".htaccess").write_text(HTACCESS, encoding="utf-8", newline="\n")

PUBLIC_ROOT_FILES = {
    ".htaccess",
    "404.html",
    "favicon.ico",
    "index.html",
    "llms.txt",
    "robots.txt",
    "sitemap.xml",
}
PUBLIC_PAGE_DIRECTORIES = {
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
EXCLUDED_ASSETS = {"config.js", "site.js", "site.css", "site-dark.js"}
TEXT_EXTENSIONS = {".html", ".css", ".js", ".txt", ".xml", ".htaccess"}


def collect_public_files() -> list[Path]:
    files: list[Path] = []
    for file in sorted(ROOT.rglob("*")):
        if not file.is_file():
            continue
        relative = file.relative_to(ROOT)
        if relative.as_posix() in PUBLIC_ROOT_FILES:
            files.append(file)
        elif relative.parts[0] in PUBLIC_PAGE_DIRECTORIES:
            files.append(file)
        elif relative.parts[0] == "assets" and file.name not in EXCLUDED_ASSETS:
            files.append(file)
    return files


class ReferenceParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.references: list[str] = []
        self.json_ld: list[str] = []
        self._in_json_ld = False
        self._json_parts: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        for name in ("href", "src"):
            value = values.get(name)
            if value:
                self.references.append(value)
        if tag == "script" and values.get("type") == "application/ld+json":
            self._in_json_ld = True
            self._json_parts = []

    def handle_data(self, data: str) -> None:
        if self._in_json_ld:
            self._json_parts.append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag == "script" and self._in_json_ld:
            self.json_ld.append("".join(self._json_parts))
            self._in_json_ld = False
            self._json_parts = []


def local_target(reference: str) -> Path | None:
    split = urlsplit(reference)
    if split.scheme or split.netloc or reference.startswith(("#", "mailto:", "tel:", "data:")):
        return None
    path = unquote(split.path)
    if not path or path == "/":
        return ROOT / "index.html"
    relative = PurePosixPath(path.lstrip("/"))
    target = ROOT.joinpath(*relative.parts)
    if path.endswith("/"):
        target /= "index.html"
    return target


public_files = collect_public_files()
public_relatives = {file.relative_to(ROOT).as_posix() for file in public_files}

required = {
    ".htaccess",
    "index.html",
    "404.html",
    "robots.txt",
    "sitemap.xml",
    "llms.txt",
    "growth-audit/index.html",
    "assets/public-config.js",
    "assets/site-public.js",
    "assets/owner-review-v2.css",
}
missing_required = sorted(required - public_relatives)
if missing_required:
    raise RuntimeError(f"Missing required public files: {', '.join(missing_required)}")

vendor_pattern = re.compile(r"(?:GoHighLevel|HighLevel|\bGHL\b)", re.IGNORECASE)
old_copy_pattern = re.compile(r"(?:Get My Free Audit|Steve Wierzbicki)", re.IGNORECASE)
missing_references: list[str] = []

for file in public_files:
    suffix = file.suffix.lower()
    if suffix not in TEXT_EXTENSIONS and file.name != ".htaccess":
        continue
    content = file.read_text(encoding="utf-8", errors="strict")
    if vendor_pattern.search(content):
        raise RuntimeError(f"Public vendor reference found in {file.relative_to(ROOT)}")
    if old_copy_pattern.search(content):
        raise RuntimeError(f"Superseded public copy found in {file.relative_to(ROOT)}")

    if suffix == ".html":
        parser = ReferenceParser()
        parser.feed(content)
        for block in parser.json_ld:
            json.loads(block)
        for reference in parser.references:
            target = local_target(reference)
            if target is not None and not target.is_file():
                missing_references.append(
                    f"{file.relative_to(ROOT).as_posix()} -> {reference}"
                )
    elif suffix == ".css":
        for reference in re.findall(r"url\(\s*['\"]?([^)'\"]+)", content):
            target = local_target(reference)
            if target is not None and not target.is_file():
                missing_references.append(
                    f"{file.relative_to(ROOT).as_posix()} -> {reference}"
                )

if missing_references:
    raise RuntimeError("Missing local references:\n" + "\n".join(missing_references))

if 'formEndpoint:""' not in (ASSETS / "public-config.js").read_text(encoding="utf-8"):
    raise RuntimeError("Unexpected public form configuration; review before packaging")

with zipfile.ZipFile(ARCHIVE, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in public_files:
        bundle.write(file, file.relative_to(ROOT).as_posix())

with zipfile.ZipFile(ARCHIVE, "r") as bundle:
    bad = bundle.testzip()
    if bad:
        raise RuntimeError(f"Corrupt ZIP entry: {bad}")
    names = set(bundle.namelist())
    if required - names:
        raise RuntimeError("Required files missing from final ZIP")
    if any(name.startswith("TopShelfBuild/") for name in names):
        raise RuntimeError("ZIP has an unwanted enclosing directory")

digest = sha256(ARCHIVE.read_bytes()).hexdigest()
CHECKSUM.write_text(f"{digest}  {ARCHIVE.name}\n", encoding="ascii", newline="\n")

print(f"GoDaddy package ready: {ARCHIVE.name}")
print(f"Public files: {len(public_files)}")
print(f"SHA-256: {digest}")
print("Notice: the public form endpoint is still blank and requires a verified HTTPS endpoint.")

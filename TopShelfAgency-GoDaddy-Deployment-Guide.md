# Top Shelf Agency GoDaddy deployment guide

## Package

Upload `TopShelfAgency-GoDaddy-Deploy.zip` to the web root for `topshelfagency.biz` and extract it in place. On standard GoDaddy Linux/cPanel hosting, the web root is commonly named `public_html`.

After extraction, `index.html`, `.htaccess`, `robots.txt`, and `sitemap.xml` must sit directly in the web root. Do not leave the site inside an extra `TopShelfAgency-GoDaddy-Deploy` folder.

## Before replacing the current site

1. Download a backup of the existing web root.
2. Confirm the domain is attached to the intended hosting directory.
3. Confirm SSL is active for both `topshelfagency.biz` and `www.topshelfagency.biz`.
4. Keep the included `.htaccess`. It supplies the custom 404 page, canonical HTTPS/non-`www` redirects, retired-service redirects, basic security headers, and compression.

## Known configuration still required

The site itself is deployable, but the Growth Audit form intentionally fails closed because no verified public form endpoint was supplied. The current fallback directs visitors to `hello@topshelfagency.biz`.

Before enabling automated submissions, put the verified public HTTPS form endpoint in `assets/public-config.js` as `formEndpoint`. Never place a private API key or secret in browser code. Rebuild and re-test the package after changing it.

## Post-upload checks

1. Open the homepage in a private browser window and confirm it defaults to dark mode.
2. Confirm the theme button switches between dark and light.
3. Test `/growth-package/`, `/growth-audit/`, `/about/`, `/faq/`, `/privacy/`, and `/terms/`.
4. Confirm a nonexistent URL displays the custom 404 page.
5. Confirm `http://www.topshelfagency.biz/` resolves to `https://topshelfagency.biz/`.
6. Confirm `https://topshelfagency.biz/services/anything/` redirects to `/growth-package/`.
7. Check `https://topshelfagency.biz/robots.txt`, `/sitemap.xml`, and `/llms.txt`.
8. Once the form endpoint is configured, submit two duplicate test leads and verify the intended deduplication, routing, consent fields, notifications, and thank-you redirect.

DNS, SSL issuance, and the external form endpoint are hosting/platform settings and are not contained in the ZIP.

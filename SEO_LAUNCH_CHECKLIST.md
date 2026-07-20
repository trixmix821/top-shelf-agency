# SEO, GEO, and AI Search Launch Checklist

The release build is technically prepared. These items require the public domain, owner accounts, or verified business details and have not been performed.

## Before deployment

- Confirm the production host serves each canonical URL with HTTP 200 and the legacy service URLs with permanent server-side redirects, not only meta refreshes.
- Configure the GoHighLevel endpoint documented in `GHL_SETUP.md` and test the full form flow.
- Add a verified public phone number, street address or service-area business details, and official social profile URLs before adding them to visible content or structured data.
- Compress or replace the social preview image only if the production performance test identifies it as a bottleneck.

## After deployment and owner approval

1. Verify `https://topshelfagency.biz/robots.txt` and `https://topshelfagency.biz/sitemap.xml` are public.
2. Verify the site in Google Search Console, submit `https://topshelfagency.biz/sitemap.xml`, and inspect the home, Growth Package, Growth Audit, About, and FAQ URLs.
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

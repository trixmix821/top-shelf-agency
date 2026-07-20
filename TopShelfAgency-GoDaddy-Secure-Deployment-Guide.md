# Top Shelf Agency secure GoDaddy deployment guide

## Upload package

Upload `TopShelfAgency-GoDaddy-Deploy.zip` to the document root for `topshelfagency.biz` and extract it in place. `index.html`, `.htaccess`, `api`, and `assets` must be directly in the document root, not inside an additional folder.

This package targets GoDaddy Linux hosting with Apache and PHP 8.1 or newer. PHP must have cURL and JSON enabled, and Apache must allow the included `.htaccess` rules.

## Required server environment variables

Use `.env.example` as the variable-name checklist. Configure the values through the hosting environment; do not upload a populated `.env` file into the public web root.

### Configuration method for GoDaddy CloudLinux PHP Selector accounts

This account's cPanel exposes CloudLinux PHP Selector (PHP 8.3) with only ordinary PHP directives — no environment-variable editor, no `env[]`/PHP-FPM pool configuration, and no handler override. `.htaccess`-based `SetEnv` cannot be relied on here (unverified whether the request even reaches a `mod_php`-style handler), so real values will not reach PHP's `getenv()`.

To support this, `GrowthAuditConfig::fromEnvironment()` falls back to a private PHP file **outside `public_html`** for any variable `getenv()` does not supply. Environment variables always take precedence when present; the private file is only consulted for whatever is still missing.

Create, by hand in cPanel File Manager (never via a redeploy or ZIP extraction):

```
<account home>/growth-audit-private/            (directory, permissions 0700)
<account home>/growth-audit-private/config.php  (file, permissions 0600)
<account home>/growth-audit-private/storage/    (directory, permissions 0700 — use this as GROWTH_AUDIT_STORAGE_DIR)
```

`<account home>` is the directory one level above `public_html` (documented in `PHASE3_HANDOFF.md` as the verified production docroot's parent). The application derives this path itself at runtime (`dirname(__DIR__, 3)` from `api/growth-audit/lib.php`) — it is never read from an env var, request input, or hard-coded account name in source.

`config.php` must `return` a plain PHP array keyed by the same variable names used in `.env.example`, for example:

```php
<?php
return [
    'GROWTH_AUDIT_APP_SECRET' => '...',
    'GROWTH_AUDIT_ALLOWED_ORIGIN' => 'https://topshelfagency.biz',
    'GROWTH_AUDIT_STORAGE_DIR' => '<account home>/growth-audit-private/storage',
    'GHL_PRIVATE_INTEGRATION_TOKEN' => '...',
    // ...remaining GHL_* keys from .env.example
];
```

This file must never be placed inside `public_html`, committed to the repository, included in the deploy ZIP, or pasted into logs, terminal output, or chat. Because it sits outside the document root, Apache never serves it regardless of `.htaccess` rules — a stronger guarantee than the `Require all denied` block already protecting `lib.php`. If `config.php` is absent or returns something other than an array, the endpoint fails closed exactly as it does today (HTTP 503, no configuration details exposed).

Required HighLevel values:

- A production sub-account Private Integration Token with `contacts.write`, `opportunities.readonly`, and `opportunities.write` scopes
- Location ID
- `Top Shelf Sales Pipeline` pipeline ID
- `New Lead` pipeline-stage ID
- Contact custom-field keys for trade, team size, best contact time, concern, and consent

The consent custom field should be a text field because the endpoint stores both the grant and its UTC timestamp. The endpoint uses the standard contact fields for name, company name, phone, email, website, and city.

Required hosting values:

- A random application secret of at least 32 characters
- Allowed origin `https://topshelfagency.biz`
- A private writable storage directory outside the public document root for rate-limit and idempotency lock files
- Optional rate-limit, token-lifetime, and API-timeout overrides

## HighLevel behavior

The endpoint upserts the contact, checks the target pipeline for an existing opportunity belonging to that contact, creates a `New Lead` opportunity only when one does not exist, and adds `growth-audit-submitted` only after all contact and opportunity operations succeed.

Do not enable production traffic until the real IDs and field keys have been verified in the intended HighLevel sub-account.

## Post-upload checks

1. Confirm `/api/growth-audit` returns JSON containing a signed token on `GET`.
2. Confirm a valid form submission reaches the existing thank-you page.
3. Verify the mapped contact fields and consent timestamp in HighLevel.
4. Submit the same email and phone twice and confirm only one opportunity exists in `Top Shelf Sales Pipeline`.
5. Confirm the opportunity is at `New Lead` and the contact has `growth-audit-submitted`.
6. Confirm an invalid submission returns `422`, repeated rapid attempts return `429`, and missing credentials return `503` without exposing configuration details.
7. Confirm server error logs are writable and review the request ID for any failed test.

Nothing in the package deploys or configures the external HighLevel account automatically.

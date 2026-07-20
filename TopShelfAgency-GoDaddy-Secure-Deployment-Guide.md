# Top Shelf Agency secure GoDaddy deployment guide

## Upload package

Upload `TopShelfAgency-GoDaddy-Deploy.zip` to the document root for `topshelfagency.biz` and extract it in place. `index.html`, `.htaccess`, `api`, and `assets` must be directly in the document root, not inside an additional folder.

This package targets GoDaddy Linux hosting with Apache and PHP 8.1 or newer. PHP must have cURL and JSON enabled, and Apache must allow the included `.htaccess` rules.

## Required server environment variables

Use `.env.example` as the variable-name checklist. Configure the values through the hosting environment; do not upload a populated `.env` file into the public web root.

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

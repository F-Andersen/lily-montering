# fiksitt

Norwegian furniture assembly and handyman website with a PHP administration panel.

## Requirements

- PHP 8.2+ with PDO MySQL, GD/WebP, fileinfo, EXIF, mbstring and OpenSSL.
- MySQL/MariaDB with utf8mb4.
- Apache-compatible rewrite rules, or an equivalent reviewed reverse-proxy setup.
- HTTPS and a private writable directory for customer attachments.
- Authenticated SMTP for real email notifications.

Docker supplies the runtime. Node.js is only needed for development tools and tests.
GitHub Pages cannot run the PHP backend, administration or enquiry forms.

## Local Development

Copy `.env.example` to an ignored `.env`, then configure development values.
Production credentials must never be reused in development.

```sh
docker compose up -d --build
```

The default website is at http://localhost:8080/ and Mailpit at
http://localhost:8025/. Mailpit captures messages locally; it does not send them
to real inboxes. Development ports bind to loopback.

An empty database imports `database/schema.sql` and `database/seed.sql`.
There are no default administrator credentials. Set a private `SETUP_TOKEN`,
complete the one-time setup under the configured `ADMIN_PATH`, then clear the
token. Never import seed data into an existing live database.

## Architecture

- `index.php`: homepage, enquiry dialog, portfolio and moderated reviews.
- `tjenester.php`: service catalog and individual service pages.
- `contact.php`: validation, private photo storage and SMTP notification.
- `omtale.php`: personal invitation redemption and customer review submission.
- `admin/`: authenticated content, enquiries, reviews, settings and account tools.
- `app/`: configuration, PDO, sessions, CSRF, templates and domain logic.
- `database/`: schema, initial content and additive migrations.
- `assets/images/`: public project photographs and responsive WebP variants.
- `assets/uploads/`: managed public images, never customer enquiry attachments.
- `sitemap.php` and `robots.php`: dynamic search-engine endpoints.
- `tests/` and `tools/`: development checks and CLI maintenance tools.

`index.html` is a static preview, not a substitute for the PHP application.
The admin UI supports Norwegian Bokmal and Ukrainian. Business content is not
automatically translated.

## Enquiries And Reviews

Clients can submit an enquiry without registration, with up to five photos,
each limited to 5 MiB. Valid images are decoded, re-encoded as WebP and stored
outside the public web root. Only authenticated administrators can view them.
Notifications include an authenticated admin link, not public attachment URLs.
Saved enquiries remain available if mail delivery fails.

In administration, open Reviews and choose **Inviter kunder**. A completed,
unarchived job without a review can receive a personal invitation. Create the
link and copy it or explicitly email it to the customer's enquiry address.
Links are single-use, expire after 30 days and require publication consent.
Reviews remain pending until moderated. The full invitation token is available
in the issuing admin session for 30 minutes; creating a replacement invalidates
the previous link.

The Reviews list displays all statuses by default. Administrators can hide the
clearly labeled demonstration separately from customer reviews, or permanently
delete a customer review after explicit confirmation. Deletion preserves the
enquiry and its private photos and revokes its previous invitation. Creating a
new invitation is a separate deliberate action. Service catalog filters apply
on selection, with a normal GET submit button retained for browsers without JS.

SMTP acceptance is not confirmation of inbox delivery. Check provider logs and
the recipient's inbox/spam folder before retrying an ambiguous delivery.

## Production Updates

Use `docker-compose.production.yml` with server-only environment values.
Production passwords are required; example development values are not safe.
Configure `APP_ENV=production`, a trusted HTTPS `APP_URL`, `ADMIN_PATH`, database,
SMTP and exact trusted reverse-proxy addresses. Do not expose backend ports or
trust forwarded headers from arbitrary clients. A custom admin route is not
a security boundary; authentication and rate limits remain mandatory.

Before each update, back up source, database, public uploads and the private
photo volume together, and retain the previous web image. Preserve the server
environment, custom Compose configuration, uploads and volume identities.
Apply only required additive migrations; never reimport seed data or use
`docker compose down -v` on a live installation. Rebuild/recreate only the web
service when database changes are not needed.

Configure PHP `upload_max_filesize=5M`, `post_max_size=28M`,
`max_file_uploads=6` and `memory_limit=256M`. Permit the enquiry endpoint's
multipart body through the proxy without increasing unrelated route limits.
Customer attachment storage must remain outside the public web root.

Search indexing requires production mode, a configured HTTPS public domain and
the explicit indexing setting. Local environments remain noindex. Publish
only verified business information; search ranking cannot be guaranteed.

## Verification

```sh
docker compose exec -T web php tests/lint.php
node tests/design-http.cjs
node tests/seo-public-http.cjs
```

Read the guards at the top of each test before running it. Mutation suites
require their dedicated isolated QA database, Docker project and Mailpit;
never target production or an owner's working preview. Some image/XML checks
require development dependencies such as Sharp and xml-js.

Keep credentials, test evidence, operational handoff notes and backups outside
Git. See `SECURITY.md` for repository hygiene and reporting guidance.

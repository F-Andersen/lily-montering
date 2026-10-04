# fiksitt

The owner selected fiksitt and the intended domain `fiksitt.online` on 2026-10-04.
DNS and HTTPS were activated on the VPS on 2026-10-04 at https://fiksitt.online/.
HTTP and www redirect to this canonical hostname, not Tezamed. Indexing remains
off until the owner approves launch. Future pushes/deployments need authorization.
The F mark is temporary pending the owner's actual logo. Technical LILY names
(repository, directories, database, Docker project and session cookie) stay unchanged.

Local branding preview: `http://127.0.0.1:18081/`, isolated Docker project
`fiksitt-brand-preview`. Port 18090 is still the SSH tunnel to the unchanged
production site. Branding checks: `tests/branding.cjs`, with `QA_URL` set to
the preview URL and `COMPOSE_PROJECT_NAME=fiksitt-brand-preview`.
To restart this local preview without using the production `.env`:

```powershell
docker compose --env-file .qa/fiksitt-preview.env -p fiksitt-brand-preview up -d
```

The ignored preview environment contains development-only values, not server
credentials. Do not enable this environment in production.

A lightweight Norwegian assembly-service website with a native PHP catalog and administration panel. Production uses PHP 8.2+, Apache/LiteSpeed and MySQL/MariaDB on cPanel. No framework, frontend build, Node.js, Docker, Composer, workers or cron is required in production.

For agent handoff, architecture context and a fillable launch-information checklist, see [CODEX_HANDOFF.md](CODEX_HANDOFF.md). Keep credentials out of that document.

## SEO And Admin Optimization (Local, 2026-10-04)

See [SEO_LAUNCH.md](SEO_LAUNCH.md) for the release checklist and known external
requirements. `/admin/seo/` shows business/indexing configuration and current
service metadata. Settings include homepage SEO title/description and an explicit
indexing opt-in. Indexing requires production plus a configured public HTTPS
domain; local environments stay noindex. Missing `seo_indexable` defaults to off,
so check this setting deliberately during an approved release.

Public routes use consistent canonical URLs and 301 aliases. Service pages have
contextual social images and Service/Breadcrumb JSON-LD; the catalog has an
ItemList. Unknown category URLs are 404. No invented prices, reviews or addresses.
Responsive hero preload matches the actual picture; new uploads get 320/900px
WebP derivatives. Existing uploads remain compatible without regeneration.

Admin content lists have literal-text search, active-state filters, bounded
pagination, counts and thumbnail sizing. Request lists avoid loading message
bodies; customer details include safe phone/email links. Dashboard request counts
use one aggregate query. New tests: `tests/seo-admin.cjs` (isolated
`fiksitt-seo-qa` only) and `tests/performance.cjs` (local lab samples, not Lighthouse
or field Core Web Vitals). No SQL schema migration is required.

The owner authorized a separate local administrator on 2026-10-04:
`masxpros@gmail.com` at `http://127.0.0.1:18081/admin/`. Its random password is
in an owner-only private file outside Git:
`C:/Users/Anderson/.codex/private/fiksitt-local-admin/access-local-2026-10-04.json`.
This is not the server password. Change it in Konto; do not reuse production
credentials in development. The production account/tunnel on 18090 is unchanged.

## Architecture

- `index.php`: existing landing-page copy, real project photos, featured services, selected gallery, editable contact information and enquiry form.
- `tjenester.php`: category-filtered catalog and service detail pages.
- `admin/`: login, one-time setup, dashboard, services, categories, gallery, requests and settings.
- `app/`: PDO, configuration, secure sessions, CSRF, reusable templates, validation, mail transport and upload handling. HTTP access is denied.
- `database/schema.sql`, `database/seed.sql`: portable utf8mb4 schema and original site content. No admin credentials, fake prices or business contact details.
- `assets/images/`: existing JPEG, WebP and responsive WebP variants. These are intentional format/size variants.
- `assets/uploads/`: random-named, re-encoded JPEG uploads. Originals are never trusted or retained.
- `sitemap.php`, `robots.php`, `404.php`: dynamic SEO and errors.
- `index.html`: retained static preview for existing GitHub Pages use. Apache redirects it to PHP. GitHub Pages cannot run the catalog, administration or PHP form.
- `tests/`: development-only lint and browser QA. Never deploy this folder.

Structural Norwegian copy stays in the template. Only services, gallery and a small set of settings are database-managed. The homepage has safe original-content fallback when the database is unavailable; catalog and admin return controlled 503 responses.

## Live Docker deployment

The live site is https://fiksitt.online/ on a Zomro VPS, using `docker-compose.production.yml` from `/opt/lily-montering/app`. Shared Nginx routes Tezamed and fiksitt independently. Unlike the development bind mount, production code is copied into the Docker image. After uploading changed files, rebuild only the web service:

```bash
docker compose -f docker-compose.production.yml up -d --build --no-deps web
```

Preserve the server `.env`, database volume and `assets/uploads/`. Never reimport seed data or run `down -v` during an upgrade. Back up changed source and retain the previous image before rebuilding. The 2026-10-03 photo/phone/layout update did not change the schema or database rows. See CODEX_HANDOFF.md for its backup reference and verified deployment state.

Production port bindings are private: `WEB_BIND_IP=127.0.0.1`, `WEB_PORT=8080`
for the SSH tunnel; `INGRESS_BIND_IP=172.18.0.1`, `INGRESS_PORT=18080` for
Nginx. Do not restore a public backend port. `TRUSTED_PROXIES=172.19.0.1`
matches the observed Docker NAT gateway, not a visitor-supplied IP. Nginx
overwrites forwarded scheme headers and blocks `/admin` before proxying.
Verify bridge addresses and Secure cookies whenever Docker networking changes.

Service cards/details and admin service lists/editors prefer the assigned image;
missing or invalid image paths use the same real project photo selected by
category. Editor fallbacks are marked `Standardbilde` and are not written to
the database. Custom service slugs still use their category's default photo.
Four additional original photographs have optimized JPEG/WebP variants in
`assets/images/`. Public styles/scripts and admin CSS use `?v=4`.

The phone selector defaults to Norway (+47), includes several common countries and an international-number option. `app/phone.php` normalizes the prefix and checks basic international syntax, not number ownership or reachability. Full international numbers from autofill remain supported, and submission works without JavaScript.

**Enquiries:** submissions are stored in `contact_requests` and listed at `/admin/requests/`. Email uses `MAIL_RECIPIENT`, `MAIL_FROM` and SMTP settings from server configuration, not the public email in company settings. `/admin/settings/` displays the configured recipient and transport. HTTPS is active; real email delivery is still blocked by missing working SMTP authentication. Enabling mail does not automatically resend previously stored enquiries.

## Owner administration

The active owner login is `masxpros@gmail.com`. All administrative features are available: enquiry search/status/archive, service/category CRUD, gallery uploads, business settings, and password changes in `/admin/account/`. Dashboard counters link to their sections and provide create-service/create-photo shortcuts. Changing the password rotates the current session and CSRF token and rejects other sessions on their next request; older sessions also expire after 30 minutes of inactivity.

Public access to `/admin` is blocked by Nginx on this VPS. `ADMIN_ALLOWED_IPS` contains the loopback addresses and current Docker host gateway (`172.19.0.1`); requests never trust client-supplied forwarded-IP headers. The backend is bound only to loopback/internal ingress, not the public IP. Keep these controls together: trusting the NAT gateway is safe only while public backend access stays closed. An empty list disables this optional restriction for other hosting setups. If Docker networking changes, review the gateway address via trusted SSH. The owner panel remains SSH-tunnel-only even though the public site now has HTTPS.

On the owner's current Windows PC the secure SSH tunnel forwards `127.0.0.1:18090` to the live server. Panel: http://127.0.0.1:18090/admin/. The tunnel is not a second site and requires the existing private SSH key. To reopen it after restarting the PC:

```powershell
& "$env:USERPROFILE\.codex\private\lily-admin\Start-LilyAdmin.ps1"
```

Temporary credentials are in an owner-only local file under `$USERPROFILE/.codex/private/lily-admin/`, outside this repository. No password or private key is stored here. Change the temporary password in **Konto**, then remove the obsolete credential entry from the private file. First-admin setup is disabled and `SETUP_TOKEN` has been cleared on the server. Do not delete administrator rows to reopen setup.

This is one trusted owner account, not a multi-role staff-management system. For additional administrators or password recovery, use a reviewed secure provisioning procedure; do not expose root/SSH credentials through the panel.

Focused local regression checks: `node tests/public-fixes.cjs` and `node tests/delivery-settings.cjs`. They accept a loopback-only `QA_URL` and use the local Docker project selected by `COMPOSE_PROJECT_NAME`; mail checks require the development Mailpit configuration. The public fixes test does not clear shared rate limits, overwrite content or stop database/mail services. Run it in an isolated development project, not production.

## Docker development

Install Docker Desktop with Linux containers. Start from the repository directory:

```powershell
Copy-Item .env.example .env
php -r "echo bin2hex(random_bytes(32));"
```

Put that generated value in `SETUP_TOKEN` inside `.env`. No default administrator exists. The token and `.env` are ignored by Git. Docker uses development-only database credentials on its private network, with web/Mailpit ports bound to loopback.

```bash
docker compose up -d --build
docker compose ps
docker compose logs --tail=50
docker compose exec web php -v
docker compose exec web php tests/lint.php
docker compose down
```

- Website: http://localhost:8080
- Catalog: http://localhost:8080/tjenester
- Admin setup: http://localhost:8080/admin/setup.php
- Admin login: http://localhost:8080/admin/login.php
- Mailpit inbox: http://localhost:8025
- MariaDB: `db:3306`, internal network only.

The web service mounts the repository; PHP/CSS/JS edits appear immediately. Rebuild only for Dockerfile changes. MariaDB 10.11 stores data in the `db_data` named volume. Mailpit has no permanent message volume.

Database schema and seed import automatically on the first start with a new volume. To import explicitly into an existing empty database:

```bash
docker compose exec web php -r 'require "app/bootstrap.php"; db()->exec(file_get_contents("database/schema.sql")); db()->exec(file_get_contents("database/seed.sql"));'
```

Seed uses explicit stable IDs and `INSERT IGNORE`. Import it only for initial setup, not routinely against edited production data. `docker compose down` preserves the database; deleting the named volume erases it.

## Configuration

Production reads `config.example.php` and overrides it with server-only `config.php`. Create `config.php` from the example and set database, SMTP, application URL and a strong setup token. Keep it out of Git. Apache denies direct access to both configuration files and dotfiles.

Docker reads these variables from Compose/`.env`:

| Variable | Purpose |
| --- | --- |
| APP_ENV | `local` in Docker; `production` on hosting |
| APP_URL | Trusted base URL, including installation subdirectory if used |
| DB_HOST / DB_PORT | Database endpoint |
| DB_NAME / DB_USER / DB_PASSWORD | Application database credentials |
| DB_ROOT_PASSWORD | Local MariaDB initialization only |
| SMTP_HOST / SMTP_PORT | Mail server; `mailpit:1025` locally |
| SMTP_USER / SMTP_PASSWORD | Optional authenticated SMTP credentials |
| SMTP_ENCRYPTION | `tls`, `ssl`, or `none`; use TLS/SSL in production |
| MAIL_RECIPIENT / MAIL_FROM | Form recipient and verified sender |
| SETUP_TOKEN | One-time first-admin secret |
| WEB_PORT / MAILPIT_PORT | Loopback development ports |

Compose fixes SMTP to Mailpit for development. Production settings go in `config.php`, not Docker. Set `pretty_urls => false` if rewrite is unavailable. Direct fallback URLs are `tjenester.php` and `tjenester.php?slug=...`. Use `robots.php` and `sitemap.php` directly, and update the robots sitemap reference on such hosting.

## First administrator

1. Import schema and seed, configure database and a random setup token.
2. Open `/admin/setup.php` over HTTPS in production.
3. Enter the token in the form, your email, and a password of at least 12 bytes (maximum 72 bytes).
4. Log in at `/admin/login.php`.
5. Remove/empty `setup_token` in production configuration.

Setup works only with zero administrator accounts and a matching token. A database lock serializes concurrent setup attempts. It returns 404 after the first account exists. There is no default password. Password reset/account management is not exposed publicly; recover through the hosting database using a newly generated `password_hash()` value.

## Administration

- Dashboard: latest enquiries and counts, with failed-mail delivery indicators.
- Services: create/edit/delete, categories, unique URL names, descriptions, optional price/duration, active/featured flags, images, sorting and SEO.
- Categories: edit, reorder, activate/deactivate; deletion is blocked while services reference the category.
- Gallery: JPEG/PNG/WebP uploads, Norwegian alt text, captions, flags, sorting and deletion.
- Requests: read, search, filter, status updates (`new`, `in_progress`, `completed`, `spam`), archive and permanent deletion.
- Settings: business name, slogan, phone, email, service area, domain, organization number, CTA and optional social URL.

Deleting content requires POST, CSRF and a browser confirmation. Uploaded images are deleted only when neither services nor gallery reference them. Existing source photographs are never deleted by admin.

## Contact form and Mailpit

The form validates input on the server, checks CSRF, a honeypot and completion time, and throttles attempts. Field errors are associated with their inputs. It works without JavaScript using a server-generated timestamp and an HTML response.

A legitimate enquiry is stored before sending its email. If mail fails, a stored enquiry returns a registered confirmation and remains visible in admin with `email_sent=0`. If the database fails but email succeeds, the enquiry is delivered by email. If both fail, HTTP 503 is returned; the browser preserves the form. There is no automatic email retry worker.

In Docker, submit a valid enquiry after at least four seconds, then open Mailpit. Production can use native `mail()` or the existing lightweight SMTP implementation. Verify the sender address and TLS/authentication with your hosting provider.

## Security

- Native prepared PDO statements, utf8mb4, output escaping.
- Strict session IDs, regeneration at login, HttpOnly/SameSite cookies, Secure cookies on HTTPS, 30-minute inactivity expiry.
- CSRF for every admin mutation, setup, logout and public enquiry.
- Login throttling per account/IP; public form throttling per IP.
- The limiter stores a hashed identity and an expiring counter in a private temporary file with an exclusive lock; it never trusts forwarded IP headers.
- MIME verification via fileinfo, decoded pixel limits, maximum 5 MB input, GD re-encoding at up to 1800 px and random filenames.
- No SVG/HTML/PHP uploads. Upload directory disables script handlers; app/database/tests are blocked.
- Directory listing disabled; no raw SQL/SMTP exceptions in visitor responses or application logs.
- Dynamic CSP retains self-only scripts/styles and hashes only the exact generated JSON-LD. The old fixed hash could not authorize changing service/business schema, so PHP now supplies its corresponding hash. No `unsafe-eval` or `unsafe-inline`.
- Admin pages are noindex and no-store.

Enable HTTPS before production admin use. Set production PHP `display_errors=Off`, `log_errors=On`, `expose_php=Off`, and a private error log. Native PHP/Apache logs can include request IPs independently of application data; configure their retention with your host. Periodically delete obsolete rate-limit files in the server's private temp area and old customer enquiries according to business retention needs.

## SEO and URLs

Apache maps `/tjenester`, `/tjenester/{slug}`, `/sitemap.xml`, `/robots.txt` to their PHP endpoints. Existing `index.html` redirects to `index.php`. Unknown/inactive service slugs return 404.

Each service has unique metadata and truthful Service schema. Business schema uses only provided settings. No address, ratings, reviews or prices are invented. Empty price fields render no price; empty phone/email settings render no broken links.

Set the public HTTPS domain in admin, and the trusted base URL in configuration. Dynamic sitemap includes active services in active categories. Canonical/OG URLs are omitted when neither domain nor trusted app URL exists. The static fallback SEO files contain no fabricated domain.

If installed in a subdirectory, include it in APP_URL, and change Apache `ErrorDocument 404` to that directory's `404.php` path. Changing a service slug changes its URL; retain old URLs with explicit redirects when required.

## Image handling

GD with JPEG/PNG/WebP input support and PHP fileinfo are required for administration uploads. Docker includes them. Enable GD on cPanel. Apache needs write access to `assets/uploads/`; use normal hosting ownership/permissions (typically 755 directory, 644 files), not 777.

Real project photos are retained. Responsive WebP variants are used for gallery/service images; uploaded files are optimized at upload. Below-the-fold images are lazy-loaded with explicit dimensions. Raw `foto/` and `foto.zip` remain ignored and must not be deployed.

## Repeatable QA

```bash
docker compose up -d --build
docker compose exec web php tests/lint.php
curl -I http://localhost:8080/
curl -I http://localhost:8080/tjenester
curl -I http://localhost:8080/tjenester/not-a-service
```

Optional browser QA uses development-only Playwright and Sharp with installed Microsoft Edge:

```bash
npm install --no-save --package-lock=false playwright sharp
node tests/qa.cjs
node tests/extra.cjs
```

If using bundled dependencies, set `NODE_PATH` to their node_modules directory instead of installing. The QA script refuses non-local URLs and non-local APP_ENV, creates random temporary content/accounts, exercises Mailpit and simulated database/mail outages, and cleans its own records. Run it only against this development stack: it temporarily stops its db/mailpit services and clears local rate limits. Do not run concurrently with another QA process.

Results and responsive screenshots are written to ignored `.qa/`. Tests cover setup/auth/CSRF, CRUD, MIME attacks, XSS, SQL payloads, settings, enquiries/mail/failures, session expiry, logout, mobile nav, and ten public/admin views at 320, 375, 768, 1024 and 1440 px. Extra checks cover a real no-JavaScript form submission, reflected XSS, HTTPS cookie flags, exact CSP hashes and responsive image metadata.

## cPanel deployment

1. Back up the existing files and database.
2. Select PHP 8.2+ with PDO MySQL, mbstring, fileinfo, GD and OpenSSL.
3. Create a database and application user in cPanel MySQL Databases; grant rights only on this database.
4. In phpMyAdmin import `database/schema.sql`, then `database/seed.sql` for a fresh installation.
5. Upload `index.php`, `tjenester.php`, `contact.php`, `omtale.php`, `404.php`, `robots.php`, `sitemap.php`, `styles.css`, `script.js`, `review.js`, `site.webmanifest`, `.htaccess`, `config.example.php`, `app/`, `admin/`, and `assets/` into `public_html`.
6. Create server-only `config.php`, configure the full trusted URL, database credentials, verified sender/recipient, SMTP encryption/authentication, and random setup token. Do not copy local development passwords.
7. Enable SSL in cPanel. Once verified, enable the HTTPS redirect near the bottom of `.htaccess`. Ensure PHP receives the hosting HTTPS indicator correctly.
8. Open the website/catalog, create the first admin, then remove the setup token.
9. In settings enter confirmed business phone/email/domain/area. Complete a real form submission and verify admin storage and mail delivery.
10. Verify sitemap, canonical URLs, private-file denial, mobile navigation and 404 behavior.

For an upgrade from the original static site, leave `index.html` temporarily or remove it after verifying `DirectoryIndex index.php`. Rewrite redirects old index.html requests. Keep old config SMTP values but add the new database/app/setup keys. Never overwrite production `config.php`, uploads, or edited database rows with local files.

Do not deploy `.git/`, `.env`, Docker files, tests, .qa, raw photos, slide decks, node_modules or local secrets. If uploads use FTP, include hidden `.htaccess` files in app/admin assets directories where present.

## Backups and upgrades

Use cPanel file/database backups together, including server-only configuration and uploads; keep copies outside public_html. Test restoring both to a separate installation. The current schema script is idempotent for table creation but does not alter existing columns: future schema changes require explicit reviewed upgrade SQL. Record such changes before upgrading, and preserve stable content IDs.

## Production checklist

- [ ] Correct PHP extensions and production error settings
- [ ] Database created/imported and dedicated user configured
- [ ] No local secrets, Docker or testing files deployed
- [ ] Trusted APP_URL and public domain configured
- [ ] SSL enabled and HTTPS redirect tested
- [ ] First admin created; setup token removed
- [ ] Confirmed phone/email/area and optional organization/social data
- [ ] Sender/recipient and SMTP delivery verified
- [ ] Valid enquiry stored and visible to admin
- [ ] Upload folder writable and scripts blocked
- [ ] Catalog/filter/detail/404/sitemap/robots tested
- [ ] Mobile layouts and keyboard workflows checked
- [ ] Files, database, uploads and configuration backed up

## Enquiry email delivery

See `MAIL_DELIVERY.md` for private SMTP setup, activation and delivery semantics.
Admin settings can send a test message to the configured recipient; unsent
requests have a protected retry action. SMTP uses vendored PHPMailer 7.1.1.
Local previews capture mail in Mailpit and never deliver it to Gmail.
Real Gmail delivery still requires an owner-provided app password and a verified
inbox check. Do not deploy secrets or enable production changes without approval.

## Enquiry Photos (Stage 1, 2026-10-04)

Guests can submit an enquiry without registering, with an optional selection of
up to 5 JPEG/PNG/WebP photos, each at most 5 MiB. HEIC is not supported. The form
previews selections, allows removal and preserves fields on a failed submission.
Server validation checks actual MIME, decoding, dimensions and a 20-megapixel
budget. Every attachment is re-encoded as WebP (quality78, longest edge1600px),
EXIF orientation is applied and metadata is discarded. A320px-wide thumbnail is
generated. Originals and original filenames are not retained.

Photos are PRIVATE customer attachments, not public portfolio items. Metadata
is in `request_photos`; files live in the `request_photos` Docker volume at
`/var/lib/fiksitt/request-photos`, outside the web root (directory0700/files0600).
Admin Forespørsler shows attachment counts, previews, full-size views and downloads
through the authenticated `admin/requests/photo.php` endpoint. Responses are
no-store/noindex. Deleting an enquiry removes its attachment rows and managed
files. Archiving only hides the enquiry; it does not delete attachments.

An enquiry and all its photo rows are saved atomically. Storage/DB failures with
photos return an error and remove staged files; a mail failure does NOT discard
the saved enquiry. Notifications contain the photo count, not email attachments
or public photo links. Production SMTP remains unactivated; check admin for new
enquiries until real mail delivery has been configured and verified.

For existing installations, back up first and apply ONLY the additive
`database/migrations/20261004_request_photos.sql`, never re-import seed data.
Rebuild the web image and mount its private volume. PHP requires GD/WebP,
fileinfo and EXIF; Docker includes them. Set upload_max_filesize=5M,
post_max_size=28M, max_file_uploads=6 and memory_limit=256M. The application
accepts a multipart body of at most26MiB. On the shared VPS, only fiksitt's exact
`/contact.php` Nginx location has client_max_body_size26m; Tezamed is unchanged.
For native hosting, configure `request_photo_dir` or REQUEST_PHOTO_DIR to a
writable directory OUTSIDE public_html. Never make this directory public.

Back up the database AND private photo volume together, in addition to existing
uploads/config. Restoring only the DB cannot restore photos. Never run
`docker compose down -v` on a live stack. Choose a retention period for customer
enquiries and delete obsolete records through admin; backups need retention too.
The deployment backup is `/opt/lily-montering/backups/request-photos-20261004T091944Z`.

Isolated tests: `tests/request-photos.cjs`, project `fiksitt-request-qa`, port18083,
database `fiksitt_request_qa`; set NODE_PATH to the installed Sharp dependency.
Create an ignored `.qa/fiksitt-request-qa.env` with APP_URL=http://127.0.0.1:18083,
WEB_PORT=18083, MAILPIT_PORT=18027 and the isolated database credentials, then
start `docker compose --env-file .qa/fiksitt-request-qa.env -p fiksitt-request-qa up -d --build`.
This suite deliberately simulates local failures; never target production or
the owner's preview. It cleans its own random records unless QA_KEEP_FOR_UI=1.
See QA_REPORT.md and CODEX_HANDOFF.md section29 for verification and deployment.

Stages3 and4 are implemented locally below; production still has stage2 only.

## Public Reviews Section (Stage 2, Deployed, 2026-10-04)

The homepage now has `#omtaler` after the portfolio, plus header/footer links.
It displays up to6 newest approved reviews with a verified timestamp, a past
publication timestamp and a linked completed enquiry. Only public display name,
rating, text, publication date and service are selected; enquiry contact data
and private photos are never rendered. Text is escaped. Long reviews use a
native disclosure, ratings have accessible labels and official Lucide icons.
An empty database shows an honest no-reviews state without stars or fake counts;
a database failure shows temporary unavailability. No aggregate rating schema
or fabricated testimonials were added.

Production currently has this read-only stage. Stage3 is local only, as described
below. Do not manually approve unverified reviews just to fill the section. The
owner's preview at http://127.0.0.1:18081/#omtaler has an empty reviews table;
existing content/accounts were preserved. Stage2 is now live at
https://fiksitt.online/#omtaler. Deployment backup:
`/opt/lily-montering/backups/reviews-stage2-20261004T095713Z`.

Before a future approved deployment, back up production and apply ONLY
`database/migrations/20261004_reviews.sql` to the existing database; never
re-import seed data. Fresh installations include the table in schema.sql.
Reviews have one row per enquiry, a1-5 rating constraint and cascade on enquiry
deletion. Archive does not delete the linked review.

Isolated CLI verification: `tests/reviews.php`, Docker project
`fiksitt-reviews-qa`, APP_ENV=local, APP_URL=http://127.0.0.1:18084,
DB_NAME=fiksitt_reviews_qa. The suite rejects other targets, simulates a missing
table and cleans its own synthetic records. Seven groups PASS; responsive
browser checks cover320/390/768/844/1024/1440px. See QA_REPORT.md and
CODEX_HANDOFF.md section30. Screenshot evidence and local backup remain in
ignored `.qa/`, not Git.

## Verified Review Workflow (Stage 3, Local Only, 2026-10-04)

No customer registration is required. In admin Forespørsler, open a completed,
non-archived enquiry and choose Opprett invitasjon. The personal link can be
copied and shared with that customer. If the enquiry has a valid email, Send
invitasjon på e-post uses the existing verified SMTP sender and that customer's
original enquiry address. Sending is explicit, never triggered automatically
by a status change. A normal repeated send does not duplicate accepted mail.
SMTP acceptance is not proof of inbox delivery; failures leave the link usable.
Production SMTP remains unactivated, so use manual delivery until configured.

Invitations use256-bit random secrets, hash-only database storage, a30-day
expiry and one review per enquiry. Creating a replacement invalidates the old
link and any old session grant; Trekk tilbake revokes an unused link. The raw
secret is retained only in the admin session for30minutes to support copying/
sending, not in database rows. Expired session links cannot be retrieved from
the database; create a replacement. Completed jobs with existing reviews cannot
receive another invitation. Possession of the privately delivered invitation
confirms its association with a completed enquiry, not a legal identity check.

The link uses `omtale.php#token=...`. The fragment is not sent in GET URLs or
access logs; review.js clears it from browser history and exchanges the secret
by CSRF-protected POST for a30-minute session grant. This also works for another
invitation in an existing review page/session. Without JS, open omtale.php and
paste the code or full personal link into the native form. Pages are no-store,
noindex/nofollow and no-referrer, without third-party resources. Never configure
request-body logging on this route or publish personal links. Production must
use HTTPS. Public clients never receive enquiry contact fields or private photos.

The customer chooses1-5 stars, a public name (up to80characters), review text
(10-1500characters) and explicit publication consent. Valid submission atomically
creates a verified pending review and consumes the invitation. Concurrent replay,
revocation, expiry, non-completed jobs and duplicates are blocked. Public POST
bodies are limited to32KiB, with per-IP and per-invitation rate limits. No photo
uploads are accepted on this route; the5MiB enquiry-photo limit is unchanged.

Admin Omtaler lists pending reviews by default, with search/status/pagination,
enquiry links and protected approve/reject/unpublish actions. Only a verified
review for a completed job can be approved. Public text/rating cannot be edited
by admin. Latest moderator ID/time and an internal note are saved; this is not a
full chronological audit log. Moderation should use the same criteria for all
ratings, not remove legitimate criticism. Private notes never appear publicly.
The dashboard includes a pending-review count. Existing public/admin isolation
on the VPS must remain unchanged; access admin through the private SSH tunnel.

Current stage3 preview: http://127.0.0.1:18081/admin/reviews/. No fake reviews or
new owner accounts were inserted. A guarded migration preserved all original
rows/columns; local DB backup is `.qa/review-workflow-preview-before.sql`.
For a future approved deployment, back up DB/files/private photos, apply the
stage2 migration if absent, then ONLY `20261004_review_workflow.sql` before
deploying workflow PHP/JS/CSS and .htaccess. The upgrade SQL is tested with the
project's MariaDB10.11; its ADD COLUMN IF NOT EXISTS syntax needs adaptation
before use on a different database engine. Never import seed data on upgrade.

Tests in isolated fiksitt-reviews-qa18084/18028: `tests/review-workflow.php`
(9groups), SMTP-failure variant, `tests/review-workflow-http.cjs` (5groups), plus
the stage2 reader regression (7groups). HTTP tests create/delete their own
synthetic account/enquiry and reset ONLY the isolated container's rate limits;
do not run concurrently with browser QA. See CODEX_HANDOFF.md section31.

## Work Gallery (Stage 4, Local Only, 2026-10-04)

Preview: http://127.0.0.1:18081/#arbeid. Fifteen real work photographs are
available: nine existing entries and six curated additions from the owner's
86-photo folder. The remaining originals are not automatically published.
Admin -> Bilder retains captions, Norwegian alt text, active/featured flags,
ordering, replacement and deletion. Featured active entries appear in the
portfolio, ordered by sort_order/id, capped at60. Missing photos are omitted;
an empty gallery has a truthful empty state.

Native CSS scroll-snap supports horizontal touch scrolling. gallery.js adds
arrows, visible-range counter and focused-track Left/Right/Home/End. Clicking a
photo opens a native dialog with uncropped full image, previous/next, Escape,
focus restoration, scroll lock and safe-area padding. No autoplay or third-party
slider dependency. Without JS/dialog support, native full-WebP links still work.
Browser tests used Chromium, not a physical iPhone/Safari.

All displayed project photos, hero, thumbnails and photo metadata resolve to
WebP. Existing JPG paths remain compatible by selecting a valid sibling WebP;
legacy files and customer-managed DB paths are preserved. Brand SVGs and platform
PNG touch/favicon icons are not photographs. Twenty-one bundled photo families
have full/900/320 maximum-edge variants, actual-width srcsets, lazy loading and
explicit dimensions. Six new originals total21,450,246bytes; their full WebPs
total510,380bytes. Largest bundled full WebP113,608bytes. Future upload sizes vary.

Admin uploads accept real JPEG/PNG/WebP up to **5MiB (5,242,880bytes)** each,
exact boundary included. Browser feedback and server actual-file size/MIME/decode
checks reject empty/fake/oversized files and unsupported HEIC/SVG/HTML/PHP.
Sources are limited to20MP/12000px. EXIF orientation is applied, metadata stripped,
transparency flattened onto white. Only random-name full/900/320 WebPs are saved,
max1800px longest edge, no upscaling. Encodings are staged before promotion;
failed DB saves clean unused outputs. Shared service/gallery references protect
families, including legacy JPG/WebP siblings. Private enquiry photos are unchanged
and NEVER automatically imported into the public gallery.
Enable PHP exif for JPEG orientation on cPanel; Docker already includes it.

`tools/optimize-gallery.cjs` uses Sharp via NODE_PATH for asset generation only;
PHP does not require Node/Sharp at runtime. Originals are ignored and untouched.
`assets/images/gallery-selection.json` has curated paths/captions/alt texts;
fresh seed.sql includes them. Existing-installation upgrade: back up DB/uploads,
deploy code and assets together, then use PHP CLI (never re-import seed):

```sh
php tools/convert-public-uploads.php          # dry run
php tools/convert-public-uploads.php --apply  # preserve originals and DB paths
php tools/import-gallery.php                # dry run
php tools/import-gallery.php --apply         # additive, idempotent
```

Include gallery.js, app/gallery.php, complete photo families/manifest, templates,
image/upload helpers, CSS, admin JS and HTTP-blocked CLI tools in deployment.
For cPanel, add gallery.js to the upload list above. No stage4 schema migration;
stage3's migration is still required if publishing the whole working tree.
Owner preview backup `.qa/gallery-preview-before.sql`: six entries added once,
repeat import added0. Production, DNS, Tezamed, SMTP and Git untouched this turn.
QA: tests/gallery.php5groups, tests/gallery-http.cjs5groups, manual admin uploads
and responsive slider/dialog checks. See CODEX_HANDOFF.md section32.

## Remaining business information

Brand redesign is deployed at https://fiksitt.online/ (2026-10-04). The supplied
blue/red hammer logo now uses pastel yellow, pale blue and coral surfaces;
green status colors were removed from the public site and administration.
Preview: http://127.0.0.1:18081/. Public CSSv16/admin CSSv8. New versioned icons,
wordmark, manifest, review workflow and the 15-photo WebP slider are published.
The empty review section includes an explicitly labeled demonstration with
the fictional name Ole Hansen and a work photo, never a verified testimonial
or an SEO rating. It disappears when genuine approved reviews are available.
Supplied artwork says fiksit; configured company/domain remains fiksitt pending
confirmation. See CODEX_HANDOFF section34, QA_REPORT and MAIL_DELIVERY.

Public phone, public email, organization number and social links remain blank until provided. Østlandet should be confirmed. Prices and durations are optional. The VPS uses https://fiksitt.online with HTTPS; public_domain and SEO indexing remain intentionally disabled in settings. Real SMTP activation and inbox verification are still required. This repository does not deploy itself to hosting.

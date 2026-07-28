# LILY Montering

Static one-page website for a furniture assembly and handyman service in Norway.

The site is prepared for inexpensive cPanel shared hosting with PHP 8.x and no build step.

## Technology stack

- Plain HTML
- Plain CSS
- Vanilla JavaScript
- PHP 8.x contact endpoint
- Local optimized images
- No npm, Node.js runtime, framework, database, Docker, or CMS required in production

## Project structure

```text
.
├── index.html
├── styles.css
├── script.js
├── contact.php
├── config.example.php
├── .htaccess
├── robots.txt
├── sitemap.xml
├── 404.html
├── site.webmanifest
├── assets/
│   ├── favicon.svg
│   ├── favicon-32.png
│   ├── apple-touch-icon.png
│   └── images/
└── foto/                 # ignored raw originals, not for deployment
```

## Local preview

Open `index.html` in a browser to preview the static page.

To test `contact.php`, use a local PHP server:

```bash
php -S localhost:8080
```

Then open `http://localhost:8080`.

## cPanel deployment

Upload these production files and folders into `public_html`:

- `index.html`
- `styles.css`
- `script.js`
- `contact.php`
- `config.php` created from `config.example.php`
- `.htaccess`
- `robots.txt`
- `sitemap.xml`
- `404.html`
- `site.webmanifest`
- `assets/`

Do not upload:

- `foto/`
- `foto.zip`
- `.git/`
- development notes or temporary files

## Contact form setup

1. Copy `config.example.php` to `config.php` on the hosting server.
2. Replace:
   - `recipient_email`
   - `from_email`
   - `from_name`
3. If SMTP is required by the host, set:
   - `smtp.enabled` to `true`
   - SMTP host, port, username, password, and encryption
4. Keep `config.php` out of git. It is ignored by `.gitignore`.

The form includes:

- server-side validation
- client-side validation
- honeypot field
- minimum completion time check
- file-based rate limiting
- header injection protection
- JSON responses
- loading, success, and error states

No file uploads are implemented. Photos should be sent after first contact through the confirmed communication channel.

## Contact details to replace before launch

The site intentionally does not publish unconfirmed contact information.

Update these values before production:

- public phone number
- public email address
- public domain
- precise service region if different from `Østlandet`
- organization number, if it should be shown
- social or messaging links, if used
- final form recipient in `config.php`

Locations:

- `index.html` configuration comment near the top
- `index.html` contact method placeholders
- `index.html` canonical, Open Graph URL, and Open Graph image URL
- `robots.txt`
- `sitemap.xml`
- `config.php` on the server

## Domain and SEO

Before publishing, replace `https://example.com/` with the real domain in:

- `index.html`
- `robots.txt`
- `sitemap.xml`

LocalBusiness JSON-LD currently includes only:

- business name
- service type
- service area

Do not add address, phone, email, ratings, reviews, or organization number until those details are confirmed and visible on the page.

## Images

Production images live in `assets/images/`.

The site uses:

- WebP images for modern browsers
- JPEG fallbacks
- explicit width and height attributes
- lazy loading below the fold
- meaningful Norwegian alt text

To replace images:

1. Put the new optimized production image in `assets/images/`.
2. Add a matching WebP version.
3. Update the relevant `<picture>` block in `index.html`.
4. Keep original raw photos outside the deployed package.

## SSL and `.htaccess`

`.htaccess` includes safe defaults for:

- directory listing disabled
- cache headers
- Gzip/Brotli where supported
- protection of `config.php`
- Content Security Policy without `unsafe-eval`
- 404 handler

HTTPS redirect is included but commented out. Enable it only after SSL is active and the site works over HTTPS.

The CSP allows the inline JSON-LD block by SHA-256 hash. If you edit the JSON-LD script in `index.html`, update the matching hash in `.htaccess`. Do not add `unsafe-eval`.

## Remaining placeholders

- Real public domain
- Public phone number
- Public email address
- SMTP credentials on the server
- Exact service region confirmation
- Organization number, if needed
- Any social or messaging links

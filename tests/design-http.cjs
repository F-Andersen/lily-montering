const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const sharp = require('sharp');

const base = process.env.QA_URL || 'http://127.0.0.1:18081';
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error('Local read-only QA only.');

async function main() {
  for (const [route, status] of [['/',200],['/tjenester',200],['/tjenester/garderober-og-skyvedorer',200],['/admin/login.php',200],['/omtale.php',200],['/404.php',404]]) {
    const response = await fetch(base + route);
    assert.equal(response.status, status, route);
    const html = await response.text();
    assert.match(html, /fiksitt-icon-v2-32\.png/, route);
    assert(!html.includes('assets/fiksitt-mark.svg'), route);
    assert(!response.headers.get('content-security-policy').includes('unsafe-eval'));
    if (route === '/' || route.startsWith('/tjenester')) {
      assert.match(html, /fiksitt-wordmark-v2\.webp/);
      assert.match(html, /styles\.css\?v=16/);
      const json = html.match(/<script type="application\/ld\+json">([^]*?)<\/script>/)?.[1];
      if (json) {
        assert(response.headers.get('content-security-policy').includes('sha256-' + crypto.createHash('sha256').update(json).digest('base64')));
        JSON.parse(json);
      }
    }
    if (route === '/') {
      assert.match(html, /name="photos\[\]"/);
      assert.match(html, /enctype="multipart\/form-data"/);
      assert.match(html, /data-gallery/);
      assert.match(html, /id="omtaler"/);
      assert.match(html, /name="csrf"/);
      assert.equal(JSON.parse(html.match(/<script type="application\/ld\+json">([^]*?)<\/script>/)[1]).logo, base + '/assets/fiksitt-wordmark-v2.webp');
    }
  }
  const manifest = await (await fetch(base + '/site.webmanifest?v=2')).json();
  const css = await (await fetch(base + '/styles.css?v=16')).text();
  assert.match(css, /--brand-coral: #f6cccf/);
  assert.match(css, /--brand-blue-soft: #e7eff8/);
  assert(!/--pine|--green|#12382b|#12372b|#e4efe8/i.test(css));
  assert.equal(manifest.name, 'fiksitt');
  assert.equal(manifest.theme_color, '#fff3b9');
  for (const [asset, width, height] of [['assets/fiksitt-wordmark-v2.webp',720,300],['assets/fiksitt-hammer-v2.webp',128,128],['assets/fiksitt-icon-v2-32.png',32,32],['assets/fiksitt-icon-v2-180.png',180,180], ...manifest.icons.map(icon=>[icon.src, ...icon.sizes.split('x').map(Number)])]) {
    const response = await fetch(base + '/' + asset);
    assert.equal(response.status, 200);
    const data = Buffer.from(await response.arrayBuffer());
    const metadata = await sharp(data).metadata();
    assert.equal(metadata.width, width, asset);
    assert.equal(metadata.height, height, asset);
    assert(data.length < 70000, asset + ' byte budget');
    assert(response.headers.get('content-type').startsWith('image/'));
  }
  for (const file of ['index.html','404.html']) {
    const html = fs.readFileSync(file, 'utf8');
    assert.match(html, /fiksitt-wordmark-v2\.webp/);
    assert(!html.includes('assets/fiksitt-mark.svg'));
  }
  const staticJson = fs.readFileSync('index.html','utf8').match(/<script type="application\/ld\+json">([^]*?)<\/script>/)[1];
  assert(fs.readFileSync('.htaccess','utf8').includes('sha256-' + crypto.createHash('sha256').update(staticJson).digest('base64')));
  console.log('PASS redesign: 6 routes, new logo/icons/manifest dimensions and byte budgets, form/gallery/review markup, dynamic and static CSP hashes. No data writes or forms sent.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });

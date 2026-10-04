const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { xml2js } = require('xml-js');
const base = process.env.QA_URL || 'http://127.0.0.1:18081';
const production = base === 'https://fiksitt.online';
if (!production && !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('Known public site or local read-only QA only');
function meta(html, name) { return html.match(new RegExp(`<meta name="${name}" content="([^"]+)"`))?.[1]; }
(async () => {
  const robotsResponse = await fetch(base + '/robots.txt');
  assert.equal(robotsResponse.status, 200);
  const robots = await robotsResponse.text();
  if (production) {
    assert(robots.includes('Allow: /\n'));
    assert(!robots.includes('Disallow: /\n'));
    assert(robots.includes('Sitemap: ' + base + '/sitemap.xml'));
    assert(robots.includes('Disallow: /adfiksittmin/'));
  } else assert(robots.includes('Disallow: /\n'), 'Local preview never indexable');
  const alias = await fetch(base + '/sitemap.php', { redirect: 'manual' });
  assert.equal(alias.status, 301);
  assert.equal(alias.headers.get('location'), '/sitemap.xml');
  const response = await fetch(base + '/sitemap.xml');
  assert.equal(response.status, 200);
  assert(response.headers.get('content-type').startsWith('application/xml'));
  const sitemap = xml2js(await response.text(), { compact: true });
  assert.equal(sitemap.urlset._attributes.xmlns, 'http://www.sitemaps.org/schemas/sitemap/0.9');
  const entries = [].concat(sitemap.urlset.url);
  const urls = entries.map(entry => entry.loc._text);
  assert.equal(new Set(urls).size, urls.length);
  assert(urls.includes(base + '/'));
  assert(urls.includes(base + '/tjenester'));
  const titles = new Set(), descriptions = new Set();
  let home;
  for (const url of urls) {
    assert.equal(new URL(url).origin, base);
    assert(!/[?#]|admin|contact\.php|omtale\.php/.test(new URL(url).pathname));
    const page = await fetch(url);
    assert.equal(page.status, 200, url);
    const html = await page.text();
    assert.equal((html.match(/<h1\b/g) || []).length, 1, url);
    assert.match(html, /<html lang="nb"/);
    assert.equal(html.match(/<link rel="canonical" href="([^"]+)"/)?.[1], url);
    const title = html.match(/<title>([^<]+)<\/title>/)[1];
    const description = meta(html, 'description');
    assert(!titles.has(title), 'Unique title: ' + url); titles.add(title);
    assert(description && !descriptions.has(description), 'Unique description: ' + url); descriptions.add(description);
    assert.equal(meta(html, 'robots').startsWith('index,'), production);
    assert.equal(page.headers.get('x-robots-tag').startsWith('index,'), production);
    assert(!html.includes('>Sitemap</a>'));
    assert(!html.includes('unsafe-eval'));
    const json = html.match(/<script type="application\/ld\+json">([^]*?)<\/script>/)[1];
    const schema = JSON.parse(json);
    assert(page.headers.get('content-security-policy').includes('sha256-' + crypto.createHash('sha256').update(json).digest('base64')));
    assert(!json.includes('aggregateRating'), 'No self-serving or demo review ratings in schema');
    if (url === base + '/') {
      home = html;
      for (const type of ['WebSite','WebPage','LocalBusiness']) assert(schema['@graph'].some(node => node['@type'] === type));
      assert.equal(schema['@graph'].find(node => node['@type'] === 'LocalBusiness').hasOfferCatalog.itemListElement.length, 4);
      assert.match(html, /<a href="\/tjenester">Alle tjenester<\/a>/);
      assert.match(html, /name="product_url"/);
    }
    for (const src of [...html.matchAll(/<img[^>]+src="([^"]+)"/g)].map(match => match[1])) {
      assert.equal((await fetch(new URL(src, base))).status, 200, src);
      assert(!/\.(jpe?g|png)$/.test(src) || src.includes('icon'), 'Public photographs use WebP');
    }
  }
  const filtered = await fetch(base + '/tjenester?category=garderober-og-skyvedorer');
  assert.equal(filtered.status, 200);
  assert(filtered.headers.get('x-robots-tag').startsWith('noindex,'));
  const filteredHtml = await filtered.text();
  assert(filteredHtml.includes('rel="canonical" href="' + base + '/tjenester"'));
  const missing = await fetch(base + '/tjenester/does-not-exist');
  assert.equal(missing.status, 404);
  assert(!(await missing.text()).includes('rel="canonical"'));
  for (const path of ['/adfiksittmin/login.php','/omtale.php']) {
    const page = await fetch(base + path);
    assert.equal(page.status, 200, path);
    assert(page.headers.get('x-robots-tag').includes('noindex'));
  }
  assert(!production || !home.includes('127.0.0.1'));
  console.log(`PASS ${production ? 'production' : 'local'} SEO: ${urls.length} sitemap pages, unique metadata, canonical/indexing, linked catalog, structured data/CSP, WebP assets, filtered/404/private pages. Read-only; no forms or emails sent.`);
})().catch(error => { console.error(error); process.exitCode = 1; });

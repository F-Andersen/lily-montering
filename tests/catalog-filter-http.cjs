const assert = require('node:assert/strict');
const base = process.env.QA_URL || 'http://127.0.0.1:18081';
if (base !== 'https://fiksitt.online' && !/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base)) throw new Error('Known site or local read-only QA only');
const cards = html => [...html.matchAll(/<article class="service-card[^]*?<\/article>/g)].map(match => match[0]);
(async () => {
  const response = await fetch(base + '/tjenester');
  assert.equal(response.status, 200);
  const html = await response.text();
  const select = html.match(/<select id="category"[^]*?<\/select>/)[0];
  const options = [...select.matchAll(/<option value="([a-z0-9-]+)"[^>]*>([^<]+)<\/option>/g)];
  assert(options.length > 0);
  const all = cards(html);
  assert(all.length > 0);
  assert(html.includes('data-catalog-filter'));
  assert(html.includes('script.js?v=client-20261005-3'));
  for (const [, category, title] of options) {
    const page = await fetch(base + '/tjenester?category=' + category);
    assert.equal(page.status, 200);
    const filtered = await page.text();
    const expected = all.filter(card => card.includes(`<p class="eyebrow">${title}</p>`));
    assert(expected.length > 0);
    assert.deepEqual(cards(filtered), expected, category);
    assert(filtered.includes(`value="${category}" selected`));
    assert(filtered.includes(`Viser ${expected.length} ${expected.length === 1 ? 'tjeneste' : 'tjenester'}`));
    assert(page.headers.get('x-robots-tag').includes('noindex'));
    assert(filtered.includes(`rel="canonical" href="${base}/tjenester"`));
    const alias = await fetch(base + '/tjenester.php?category=' + category, { redirect: 'manual' });
    assert.equal(alias.status, 301);
    assert.equal(alias.headers.get('location'), '/tjenester?category=' + category);
  }
  assert.equal((await fetch(base + '/tjenester?category=qa-unknown-category')).status, 404);
  assert.equal(cards(await (await fetch(base + '/tjenester?category=')).text()).length, all.length);
  console.log(`PASS catalog: ${options.length} categories return matching services, reset, invalid category 404, query-preserving aliases and noindex canonical. Read-only.`);
})().catch(error => { console.error(error.message); process.exitCode = 1; });

const assert = require('node:assert/strict');
const fs = require('node:fs');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const base = 'http://127.0.0.1:18084';
const mailpit = 'http://127.0.0.1:18028';
const tag = `QA OFFERS ${randomBytes(6).toString('hex')}`;
function state(mode) {
  const output = execFileSync('docker', ['compose', '--env-file', '.qa/fiksitt-reviews-qa.env', '-p', 'fiksitt-reviews-qa', 'exec', '-T', '-e', `QA_TAG=${tag}`, 'web', 'php', 'tests/enquiry-offers-state.php', mode], { encoding: 'utf8' });
  return mode === 'cleanup' ? output : JSON.parse(output);
}
(async () => {
  const info = state('info'); // Guards DB, environment and SMTP before any POST.
  assert(info.offers.every(value => value.length <= 160));
  const mails = [];
  try {
    state('cleanup');
    const page = await fetch(base + '/');
    const html = await page.text();
    const cookie = page.headers.getSetCookie().find(value => value.startsWith('lily_session=')).split(';')[0];
    const fields = { csrf: html.match(/name="csrf" value="([a-f0-9]{64})"/)[1], started_at: String(Date.now() - 5000), name: tag, phone: '91234567', phone_country: 'NO', email: 'qa-offers@example.test', service: info.offers[2], area: 'QA', message: 'TEST ONLY: please assemble the furniture shown in the photo.', privacy: '1', website: '' };
    async function send(values, photo = false) {
      const body = new FormData();
      for (const [key, value] of Object.entries(values)) body.append(key, value);
      if (photo) body.append('photos[]', new Blob([fs.readFileSync('assets/images/hvitt-garderoberom.webp')], { type: 'image/webp' }), 'wardrobe.webp');
      const response = await fetch(base + '/contact.php', { method: 'POST', headers: { Cookie: cookie, Accept: 'application/json' }, body });
      return { status: response.status, json: await response.json() };
    }
    assert.equal((await send({ ...fields, csrf: 'invalid' })).status, 403);
    for (const extra of [{ product_url: 'javascript:alert(1)' }, { product_url: 'https://example.test/\r\nBcc: bad@example.test' }, { 'product_url[]': 'https://example.test/' }]) {
      const response = await send({ ...fields, ...extra });
      assert.equal(response.status, 422);
      assert(response.json.errors.product_url);
    }
    assert.equal(state('state').length, 0, 'Invalid URLs must not create requests');
    const productUrl = 'https://www.ikea.com/no/no/?product=wardrobe&source=QA';
    assert.equal((await send({ ...fields, product_url: productUrl }, true)).status, 200);
    assert.equal((await send({ ...fields, name: tag + ' legacy', service: info.legacy })).status, 200);
    const rows = state('state');
    assert.equal(rows.length, 2);
    assert.equal(rows[0].service, info.offers[2]);
    assert(rows[0].message.endsWith('Produktlenke: ' + productUrl));
    assert.equal(rows[0].photos.length, 1);
    assert.match(rows[0].photos[0].filename, /^[a-f0-9]{40}\.webp$/);
    assert(rows[0].photos[0].bytes < 5 * 1024 * 1024);
    assert.equal(rows[1].message, fields.message, 'Legacy request without URL stays unchanged');
    assert(rows.every(row => Number(row.email_sent) === 1));
    const list = await (await fetch(mailpit + '/api/v1/messages')).json();
    for (const message of list.messages) {
      const detail = await (await fetch(mailpit + '/api/v1/message/' + message.ID)).json();
      if (!detail.Text.includes(tag)) continue;
      mails.push(message.ID);
      if (detail.Text.includes(productUrl)) assert(detail.Text.includes(info.offers[2]));
    }
    assert.equal(mails.length, 2, 'Both notification emails captured locally');
    const linked = await (await fetch(mailpit + '/api/v1/message/' + mails[1])).json();
    const other = await (await fetch(mailpit + '/api/v1/message/' + mails[0])).json();
    assert([linked.Text, other.Text].some(text => text.includes(productUrl)), 'Product URL reaches notification');
    console.log('PASS enquiry offers: URL/CSRF guards, new offer + private WebP upload, URL persistence/mail, legacy request compatibility. SMTP stayed in isolated Mailpit.');
  } finally {
    state('cleanup');
    if (mails.length) await fetch(mailpit + '/api/v1/messages', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ IDs: mails }) });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });

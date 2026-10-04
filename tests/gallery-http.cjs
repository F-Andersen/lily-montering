const assert = require('node:assert/strict');
const fs = require('node:fs');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const sharp = require('sharp');
const base = 'http://127.0.0.1:18084';
const tag = `QA GALLERY ${randomBytes(6).toString('hex')}`;
const groups = [];
function state(mode) {
  const output = execFileSync('docker', ['compose', '--env-file', '.qa/fiksitt-reviews-qa.env', '-p', 'fiksitt-reviews-qa', 'exec', '-T', '-e', `QA_TAG=${tag}`, 'web', 'php', 'tests/gallery-state.php', mode], { encoding: 'utf8' });
  return mode === 'cleanup' ? output : JSON.parse(output);
}
let cookie = '';
async function request(path, fields) {
  const response = await fetch(base + path, { method: fields ? 'POST' : 'GET', redirect: 'manual', headers: cookie ? { Cookie: cookie } : {}, body: fields });
  for (const value of response.headers.getSetCookie()) if (value.startsWith('lily_session=')) cookie = value.split(';')[0];
  return { status: response.status, html: await response.text() };
}
const csrf = page => page.html.match(/name="csrf" value="([a-f0-9]{64})"/)[1];
const pass = name => { groups.push(name); console.log('PASS ' + name); };
async function save(label, data, type, filename = 'photo.jpg', old = null, extra = {}) {
  const path = old ? `/admin/gallery/?id=${old.id}` : '/admin/gallery/?new=1';
  const page = await request(path);
  const form = new FormData();
  for (const [name, value] of Object.entries({ csrf: csrf(page), action: 'save', id: old?.id || 0, caption: tag + ' ' + label, alt_text: 'TEST ONLY <script>alert(1)</script>', sort_order: 0, active: 'on', featured: 'on', ...extra })) form.set(name, value);
  form.set('image_file', new Blob([data], { type }), filename);
  return request(path, form);
}
async function remove(row) {
  const page = await request(`/admin/gallery/?id=${row.id}`);
  return request('/admin/gallery/', new URLSearchParams({ csrf: csrf(page), action: 'delete', id: row.id }));
}
(async () => {
  try {
    assert.equal((await request('/admin/gallery/')).status, 303);
    const fixture = state('create');
    let page = await request('/admin/login.php');
    assert.equal((await request('/admin/login.php', new URLSearchParams({ csrf: csrf(page), email: fixture.email, password: fixture.password }))).status, 303);
    page = await request('/admin/gallery/?new=1');
    const badCsrf = new FormData(); badCsrf.set('csrf', 'bad'); badCsrf.set('action', 'save');
    assert.equal((await request('/admin/gallery/?new=1', badCsrf)).status, 403);
    pass('Admin authentication and CSRF guard');
    const input = fs.readFileSync('assets/images/hjornegarderobe.webp');
    const jpeg = await sharp(input).resize(600, 800, { fit: 'fill' }).withMetadata({ orientation: 6 }).jpeg({ quality: 85 }).toBuffer();
    const png = await sharp(input).resize(480, 600).png().toBuffer();
    for (const [format, data, mime] of [['jpeg', jpeg, 'image/jpeg'], ['png', png, 'image/png'], ['webp', input, 'image/webp']]) {
      assert.equal((await save(format, data, mime, '../../payload.php')).status, 303);
      const row = state('rows').find(row => row.caption === tag + ' ' + format);
      assert.match(row.image, /^assets\/uploads\/[a-f0-9]{40}\.webp$/);
      for (const file of row.files) { assert.equal(file.mime, 'image/webp'); assert(file.size > 0 && file.size < 5 * 1024 * 1024); assert(file.dimensions); }
      if (format === 'jpeg') assert(row.files[0].dimensions[0] > row.files[0].dimensions[1], 'EXIF rotation failed');
      assert(Math.max(...row.files[0].dimensions.slice(0, 2)) <= 1800);
      const bytes = Buffer.from(await (await fetch(base + '/' + row.image)).arrayBuffer());
      const metadata = await sharp(bytes).metadata(); assert.equal(metadata.format, 'webp'); assert(!metadata.exif);
      assert.equal((await fetch(base + '/' + row.image.replace('.webp', '.jpg'))).status, 404);
    }
    const home = (await request('/')).html;
    assert(home.includes('work-photo')); assert(home.includes('&lt;script&gt;')); assert(!home.includes('<script>alert(1)</script>'));
    for (const image of home.matchAll(/<img[^>]+src="([^"]+)"/g)) if (image[1].includes('/images/') || image[1].includes('/uploads/')) assert(image[1].endsWith('.webp'));
    pass('JPEG/PNG/WebP become WebP-only families, EXIF rotation/metadata stripping, responsive escaped public render');
    const before = state('rows').length;
    for (const [data, mime, label] of [[Buffer.from('<?php echo "unsafe"; ?>'), 'image/webp', 'fake'], [Buffer.alloc(0), 'image/jpeg', 'empty'], [Buffer.concat([jpeg, Buffer.alloc(5 * 1024 * 1024 + 1 - jpeg.length)]), 'image/jpeg', 'oversize']]) {
      const rejected = await save(label, data, mime);
      assert.notEqual(rejected.status, 303); assert.equal(state('rows').length, before);
    }
    const exact = Buffer.concat([jpeg, Buffer.alloc(5 * 1024 * 1024 - jpeg.length)]);
    assert.equal((await save('exact-limit', exact, 'image/jpeg')).status, 303);
    pass('Fake/empty/over-5-MiB rejected, exactly-5-MiB image accepted');
    const pngRow = state('rows').find(row => row.caption.endsWith(' png'));
    assert.equal((await save('replaced', input, 'image/webp', 'new.webp', pngRow)).status, 303);
    assert.equal((await fetch(base + '/' + pngRow.image)).status, 404);
    const webpRow = state('rows').find(row => row.caption.endsWith(' webp'));
    state('share'); assert.equal((await remove(webpRow)).status, 303);
    assert.equal((await fetch(base + '/' + webpRow.image)).status, 200);
    const shared = state('rows').find(row => row.caption.endsWith(' shared'));
    assert.equal((await remove(shared)).status, 303);
    for (const suffix of ['', '-320', '-900']) assert.equal((await fetch(base + '/' + webpRow.image.replace('.webp', suffix + '.webp'))).status, 404);
    pass('Replacement cleanup, shared image reference protection and all-family deletion');
    const filesBefore = state('files');
    state('fail-insert');
    const failed = await save('database-failure', input, 'image/webp');
    assert.notEqual(failed.status, 303); assert(failed.html.includes('Kunne ikke lagre'));
    assert.equal(state('files'), filesBefore); assert(!state('rows').some(row => row.caption.endsWith(' database-failure')));
    state('failure-off');
    pass('Database insertion failure cleans unreferenced full and thumbnail WebPs');
    fs.writeFileSync('.qa/gallery-http-results.json', JSON.stringify({ passed: true, groups }, null, 2));
  } finally { console.log(state('cleanup')); }
})().catch(error => { console.error(error); process.exitCode = 1; });

/* Isolated HTTP/GD/DB tests. Never targets production or an owner's database. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const sharp = require('sharp');
const base = 'http://127.0.0.1:18083';
const inbox = 'http://127.0.0.1:18027';
const compose = ['compose', '--env-file', '.qa/fiksitt-request-qa.env', '-p', 'fiksitt-request-qa'];
const php = code => execFileSync('docker', [...compose, 'exec', '-T', 'web', 'php', '-r', code], { encoding: 'utf8' });
const sql = (statement, params = []) => JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + Buffer.from(JSON.stringify([statement, params])).toString('base64') + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
const tag = 'QA PHOTOS ' + crypto.randomBytes(6).toString('hex');
const email = `qa-photos-${crypto.randomBytes(6).toString('hex')}@lily.test`;
const password = crypto.randomBytes(24).toString('hex');
const cookies = { public: '', admin: '' };
const groups = [];
let createdAdmin = false;
let keep = false;
let evidenceId;
async function request(path, { body, admin = false, accept = '', method, headers = {} } = {}) {
  const kind = admin ? 'admin' : 'public';
  const response = await fetch(base + path, { method: method || (body ? 'POST' : 'GET'), body, redirect: 'manual', headers: { Connection: 'close', ...(cookies[kind] ? { Cookie: cookies[kind] } : {}), ...(accept ? { Accept: accept } : {}), ...headers } });
  for (const value of response.headers.getSetCookie()) if (value.startsWith('lily_session=')) cookies[kind] = value.split(';')[0];
  return response;
}
async function token(path = '/', admin = false) {
  const response = await request(path, { admin });
  assert.equal(response.status, 200);
  const html = await response.text();
  const match = html.match(/name="csrf" value="([^"]+)"/);
  assert(match);
  return match[1];
}
function clearContactLimit() {
  php("$dir=sys_get_temp_dir().'/lily-limits-'.substr(hash('sha256',getcwd()),0,12); foreach(glob($dir.'/*.json') as $file) unlink($file);");
}
function countRequests() { return Number(sql('SELECT COUNT(*) AS n FROM contact_requests')[0].n); }
function countFiles() { return Number(php("echo count(glob('/var/lib/fiksitt/request-photos/*.webp'));")); }
async function submit(files = [], overrides = {}, urlencoded = false) {
  clearContactLimit();
  const fields = { csrf: await token(), started_at: String(Date.now() - 10000), name: tag, phone: '91234567', phone_country: 'NO', email: 'customer@lily.test', service: 'Veggmontering', area: 'Oslo', message: 'QA fixture: mount a shelf safely on the wall.', privacy: '1', website: '', ...overrides };
  const body = urlencoded ? new URLSearchParams(fields) : new FormData();
  if (!urlencoded) {
    for (const [key, value] of Object.entries(fields)) body.append(key, value);
    for (const file of files) body.append(file.field || 'photos[]', new Blob([file.data], { type: file.type || 'image/jpeg' }), file.name || 'photo.jpg');
  }
  const response = await request('/contact.php', { body, accept: 'application/json' });
  const payload = await response.json();
  return { response, payload };
}
function pass(name) { groups.push(name); console.log('PASS ' + name); }
async function reject(files, status, overrides = {}) {
  const before = [countRequests(), countFiles()];
  const result = await submit(files, overrides);
  assert.equal(result.response.status, status, JSON.stringify(result.payload));
  assert.equal(result.payload.ok, false);
  assert.deepEqual([countRequests(), countFiles()], before, 'Rejected upload must not create records/files');
  return result.payload;
}
async function login() {
  const body = new URLSearchParams({ csrf: await token('/admin/login.php', true), email, password });
  assert.equal((await request('/admin/login.php', { body, admin: true })).status, 303);
}
async function image(id, thumbnail = false) {
  const response = await request('/admin/requests/photo.php?id=' + id + (thumbnail ? '&size=thumb' : ''), { admin: true });
  assert.equal(response.status, 200);
  assert.equal(response.headers.get('content-type'), 'image/webp');
  assert.match(response.headers.get('cache-control'), /no-store/);
  assert.match(response.headers.get('x-robots-tag'), /noindex/);
  const bytes = Buffer.from(await response.arrayBuffer());
  const metadata = await sharp(bytes).metadata();
  assert.equal(metadata.format, 'webp');
  assert.equal(metadata.exif, undefined);
  assert(Math.max(metadata.width, metadata.height) <= 1600);
  if (thumbnail) assert(metadata.width <= 320);
  return metadata;
}
(async () => {
  assert.deepEqual(JSON.parse(php("require 'app/bootstrap.php'; echo json_encode([config()['app_env'],config()['app_url'],config()['database']['name'],config()['smtp']['host']]);")), ['local', base, 'fiksitt_request_qa', 'mailpit']);
  assert.equal(php("echo ini_get('upload_max_filesize').','.ini_get('post_max_size');"), '5M,28M');
  const beforeMigration = countRequests();
  php("require 'app/bootstrap.php'; db()->exec(file_get_contents('database/migrations/20261004_request_photos.sql')); db()->exec(file_get_contents('database/migrations/20261004_request_photos.sql'));");
  assert.equal(countRequests(), beforeMigration);
  pass('Additive migration is idempotent and preserves requests');
  fs.mkdirSync('.qa/request-photos', { recursive: true });
  const jpeg = await sharp('assets/images/skyvedorer-speil-soverom.jpg').resize(2400, 1800, { fit: 'cover' }).jpeg({ quality: 80 }).toBuffer();
  const portrait = await sharp({ create: { width: 600, height: 400, channels: 3, background: '#c64732' } }).jpeg().withMetadata({ orientation: 6 }).toBuffer();
  const png = await sharp({ create: { width: 600, height: 400, channels: 4, background: { r: 30, g: 160, b: 130, alpha: .5 } } }).png().toBuffer();
  const webp = await sharp(png).webp().toBuffer();
  for (const [name, bytes] of Object.entries({ 'work.jpg': jpeg, 'portrait.jpg': portrait, 'fixture.png': png, 'fixture.webp': webp })) fs.writeFileSync('.qa/request-photos/' + name, bytes);
  const adminValues = Buffer.from(JSON.stringify([email, password])).toString('base64');
  php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + adminValues + "'),true); query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$v[0],password_hash($v[1],PASSWORD_DEFAULT)]);");
  createdAdmin = true;
  await login();
  await reject([{ data: Buffer.from('<svg onload="alert(1)"></svg>'), name: 'fake.jpg' }], 422);
  await reject([{ data: Buffer.from('<?php echo "unsafe"; ?>'), name: 'fake.webp' }], 422);
  await reject([{ data: Buffer.concat([jpeg, Buffer.alloc(5 * 1024 * 1024 + 1 - jpeg.length)]) }], 422);
  await reject(Array.from({ length: 6 }, () => ({ data: jpeg })), 422);
  await reject([{ data: jpeg, field: 'photos' }], 422);
  await reject([{ data: jpeg, field: 'photos[0][]' }], 422);
  await reject([{ data: jpeg, field: 'unexpected[]' }], 422);
  await reject([{ data: jpeg }], 403, { csrf: 'wrong' });
  await reject([{ data: jpeg }], 422, { privacy: '0' });
  pass('MIME spoofing, nested input, file count/size, CSRF and consent are enforced');
  const huge = await sharp({ create: { width: 5000, height: 4100, channels: 3, background: '#ddd' } }).png().toBuffer();
  await reject([{ data: huge, name: 'large.png', type: 'image/png' }], 422);
  const corrupt = Buffer.from(png); corrupt.fill(0, 60);
  await reject([{ data: corrupt, name: 'corrupt.png', type: 'image/png' }], 422);
  pass('Pixel budget and image decoder reject dangerous/corrupt images');
  assert.equal((await submit([], {}, true)).payload.ok, true);
  assert.equal((await submit()).payload.ok, true);
  pass('Legacy urlencoded and multipart requests without photos still work');
  const result = await submit([{ data: jpeg }, { data: portrait, name: 'portrait.jpg' }, { data: png, name: '../../payload.php', type: 'image/png' }, { data: webp, name: 'work.webp', type: 'image/webp' }]);
  assert.equal(result.response.status, 200, JSON.stringify(result.payload));
  assert.equal(result.payload.ok, true);
  evidenceId = Number(sql('SELECT id FROM contact_requests WHERE name=? ORDER BY id DESC LIMIT 1', [tag])[0].id);
  const photos = sql('SELECT * FROM request_photos WHERE request_id=? ORDER BY id', [evidenceId]);
  assert.equal(photos.length, 4);
  for (const photo of photos) {
    assert.match(photo.filename, /^[a-f0-9]{40}\.webp$/);
    assert(Number(photo.bytes) < jpeg.length);
    await image(photo.id);
    const thumb = await image(photo.id, true); assert(thumb.width <= 320);
  }
  assert.deepEqual([Number(photos[1].width), Number(photos[1].height)], [400, 600]);
  pass('JPEG/PNG/WebP become smaller private WebP + thumbnails; EXIF orientation/metadata handled');
  const pixels = Buffer.alloc(120 * 80 * 3);
  const colors = [[220, 30, 20], [20, 200, 30], [20, 30, 220], [220, 200, 20]];
  for (let y = 0; y < 80; y++) for (let x = 0; x < 120; x++) pixels.set(colors[(y >= 40 ? 2 : 0) + (x >= 60 ? 1 : 0)], (y * 120 + x) * 3);
  for (let orientation = 1; orientation <= 8; orientation++) {
    const orientedJpeg = await sharp(pixels, { raw: { width: 120, height: 80, channels: 3 } }).jpeg({ quality: 95 }).withMetadata({ orientation }).toBuffer();
    assert.equal((await submit([{ data: orientedJpeg }])).payload.ok, true);
    const lastPhoto = sql('SELECT p.id FROM request_photos p JOIN contact_requests r ON r.id=p.request_id WHERE r.name=? ORDER BY p.id DESC LIMIT 1', [tag])[0];
    const response = await request('/admin/requests/photo.php?id=' + lastPhoto.id, { admin: true });
    const actual = await sharp(Buffer.from(await response.arrayBuffer())).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    const expected = await sharp(orientedJpeg).autoOrient().removeAlpha().raw().toBuffer({ resolveWithObject: true });
    assert.equal(actual.info.width, expected.info.width); assert.equal(actual.info.height, expected.info.height);
    for (const [rx, ry] of [[.25, .25], [.75, .25], [.25, .75], [.75, .75]]) {
      const offset = (Math.floor(ry * actual.info.height) * actual.info.width + Math.floor(rx * actual.info.width)) * 3;
      for (let c = 0; c < 3; c++) assert(Math.abs(actual.data[offset + c] - expected.data[offset + c]) < 30, 'EXIF orientation ' + orientation);
    }
  }
  pass('All eight EXIF orientations match the reference decoder');
  const html = await (await request('/admin/requests/?id=' + evidenceId, { admin: true })).text();
  assert.equal((html.match(/alt="Vedlagt bilde/g) || []).length, 4);
  assert.match(html, /Last ned/);
  const download = await request('/admin/requests/photo.php?id=' + photos[0].id + '&download=1', { admin: true });
  assert.match(download.headers.get('content-disposition'), /^attachment/); await download.arrayBuffer();
  assert.equal((await request('/admin/requests/photo.php?id=' + photos[0].id)).status, 303);
  assert.equal((await request('/admin/requests/photo.php?id[]=1', { admin: true })).status, 404);
  assert.equal((await request('/admin/requests/photo.php?id=99999999', { admin: true })).status, 404);
  assert.equal((await request('/admin/requests/photo.php?id=' + photos[0].id, { admin: true, body: new URLSearchParams() })).status, 405);
  for (const path of ['/assets/uploads/' + photos[0].filename, '/var/lib/fiksitt/request-photos/' + photos[0].filename]) assert.equal((await request(path)).status, 404);
  pass('Authenticated preview/download only; no public file path or ID/path traversal');
  const firstFiveMb = Buffer.concat([jpeg, Buffer.alloc(5 * 1024 * 1024 - jpeg.length)]);
  assert.equal((await submit(Array.from({ length: 5 }, () => ({ data: firstFiveMb })))).payload.ok, true);
  pass('Five photos at exactly 5 MiB each accepted without PHP truncation');
  const tooLarge = new FormData(); tooLarge.append('padding', 'x'.repeat(27 * 1024 * 1024));
  assert.equal((await request('/contact.php', { body: tooLarge, accept: 'application/json' })).status, 413);
  pass('Total multipart body limit enforced');
  sql("CREATE TRIGGER qa_reject_photo BEFORE INSERT ON request_photos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA rollback'");
  try { await reject([{ data: jpeg }, { data: jpeg }], 503); }
  finally { sql('DROP TRIGGER qa_reject_photo'); }
  pass('Attachment DB failure rolls back the request and removes every staged file');
  php("chmod('/var/lib/fiksitt/request-photos',0500);");
  try { await reject([{ data: jpeg }], 503); }
  finally { php("chmod('/var/lib/fiksitt/request-photos',0700);"); }
  pass('Unavailable private storage fails clearly without a partial request');
  execFileSync('docker', [...compose, 'stop', 'mailpit'], { stdio: 'pipe' });
  try {
    assert.equal((await submit([{ data: jpeg }])).payload.ok, true);
    assert.equal(Number(sql('SELECT email_sent FROM contact_requests WHERE name=? ORDER BY id DESC LIMIT 1', [tag])[0].email_sent), 0);
  } finally { execFileSync('docker', [...compose, 'start', 'mailpit'], { stdio: 'pipe' }); }
  pass('SMTP failure preserves the request and private attachment');
  const fileCount = countFiles();
  execFileSync('docker', [...compose, 'up', '-d', '--no-deps', '--force-recreate', 'web'], { stdio: 'pipe' });
  cookies.public = cookies.admin = '';
  await login();
  assert.equal(countFiles(), fileCount); await image(photos[0].id);
  pass('Private volume persists across web container recreation');
  const csrf = await token('/admin/requests/?id=' + evidenceId, true);
  assert.equal((await request('/admin/requests/', { admin: true, body: new URLSearchParams({ csrf, id: String(evidenceId), status: 'in_progress', archived: '1' }) })).status, 303);
  assert.equal(sql('SELECT * FROM request_photos WHERE request_id=?', [evidenceId]).length, 4);
  pass('Admin status/archive edits preserve all attachments');
  assert.equal((await submit(Array.from({ length: 4 }, () => ({ data: jpeg })))).payload.ok, true);
  const allMail = (await (await fetch(inbox + '/api/v1/messages')).json()).messages;
  let matchingMail = false;
  for (const message of allMail) {
    const detail = await (await fetch(inbox + '/api/v1/message/' + message.ID)).json();
    if (detail.Text?.includes('Bilder: 4')) { matchingMail = true; assert.equal(detail.Attachments?.length || 0, 0); }
  }
  assert(matchingMail);
  pass('Notification includes attachment count, not public image URLs or email attachments');
  if (process.env.QA_KEEP_FOR_UI === '1') {
    fs.writeFileSync('.qa/request-photos/ui.json', JSON.stringify({ email, password, requestId: evidenceId, tag }));
    keep = true;
  }
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
  if (!keep) {
    try {
      for (const row of sql('SELECT id FROM contact_requests WHERE name=?', [tag])) php("require 'app/request-photos.php'; delete_request_with_photos(" + Number(row.id) + ");");
      if (createdAdmin) sql('DELETE FROM admins WHERE email=?', [email]);
    } catch (error) { console.error('QA cleanup failed:', error.message); process.exitCode = 1; }
  }
  fs.mkdirSync('.qa/request-photos', { recursive: true });
  fs.writeFileSync('.qa/request-photos/results.json', JSON.stringify({ groups, passed: !process.exitCode, keptForUI: keep }, null, 2));
});

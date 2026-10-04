const assert = require('node:assert/strict');
const fs = require('node:fs');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const base = 'http://127.0.0.1:18084', prefix = '/adfiksittmin';
const tag = 'QA I18N ' + randomBytes(6).toString('hex');
let cookie = '';
const groups = [];
function fixture(mode) {
  const out = execFileSync('docker', ['compose', '--env-file', '.qa/fiksitt-reviews-qa.env', '-p', 'fiksitt-reviews-qa', 'exec', '-T', '-e', `QA_TAG=${tag}`, 'web', 'php', 'tests/admin-i18n-state.php', mode], { encoding: 'utf8' });
  return mode === 'create' ? JSON.parse(out) : out;
}
async function request(path, fields, headers = {}) {
  const r = await fetch(base + path, { method: fields ? 'POST' : 'GET', redirect: 'manual', headers: { ...(cookie ? { Cookie: cookie } : {}), ...headers }, body: fields && new URLSearchParams(fields) });
  for (const v of r.headers.getSetCookie()) if (v.startsWith('lily_session=')) cookie = v.split(';')[0];
  return { status: r.status, location: r.headers.get('location'), html: await r.text(), headers: r.headers };
}
const csrf = p => p.html.match(/name="csrf" value="([a-f0-9]{64})"/)[1];
const pass = group => { groups.push(group); console.log('PASS ' + group); };
(async () => {
  try {
    for (const path of ['/admin/login.php', '/admin/', '/admin-router.php', prefix + '/unknown.php', prefix + '/app/bootstrap.php']) assert.equal((await request(path)).status, 404, path);
    assert.equal((await request(prefix)).location, prefix + '/');
    assert.equal((await request(prefix + '/requests/')).location, prefix + '/login.php');
    for (const [file, mime] of [['admin.css','text/css'],['admin.js','application/javascript']]) {
      const page = await request(prefix + '/' + file); assert.equal(page.status, 200); assert(page.headers.get('content-type').startsWith(mime));
    }
    pass('Custom routes, closed legacy/admin-router paths, protected sections and static MIME');
    let page = await request(prefix + '/login.php');
    assert(page.html.includes('lang="nb"')); assert.equal(page.status, 200);
    assert.equal((await request(prefix + '/language.php', { csrf: 'wrong', locale: 'uk' })).status, 403);
    assert.equal((await request(prefix + '/language.php', { csrf: csrf(page), locale: 'de' })).status, 422);
    assert.equal((await request(prefix + '/language.php', { csrf: csrf(page), 'locale[]': 'uk' })).status, 422);
    const changed = await request(prefix + '/language.php', { csrf: csrf(page), locale: 'uk', return: 'https://evil.example/' });
    assert.equal(changed.location, prefix + '/login.php');
    page = await request(prefix + '/login.php');
    assert(page.html.includes('lang="uk"')); assert(page.html.includes('Електронна пошта')); assert(page.html.includes('Увійти')); assert(page.html.includes('data-char-label="символів"'));
    pass('CSRF, locale validation, open-redirect rejection and Ukrainian login');
    const f = fixture('create');
    const login = await request(prefix + '/login.php', { csrf: csrf(page), email: f.email, password: f.password });
    assert.equal(login.status, 303); assert.equal(login.location, prefix + '/');
    const paths = ['','requests/','reviews/','services/','categories/','gallery/','seo/','settings/','account/','services/?new=1','categories/?new=1','gallery/?new=1'];
    for (const path of paths) {
      const p = await request(prefix + '/' + path); assert.equal(p.status, 200, path); assert(p.html.includes('lang="uk"'), path); assert(p.html.includes('Обліковий запис')); assert(!/(?:href|action|src)="\/admin(?:\/|\")/.test(p.html)); assert(!p.html.includes('Kunne ikke'));
    }
    page = await request(prefix + '/requests/?id=' + f.id);
    assert(page.html.includes('<p class="message">Lagre</p>')); assert(page.html.includes('<dd>Lagre</dd>')); assert(page.html.includes('Зберегти статус'));
    pass('Ukrainian across all admin views, content unchanged and custom links');
    assert.equal((await request(prefix + '/language.php', { csrf: csrf(page), locale: 'nb', return: 'requests/?id=' + f.id })).location, prefix + '/requests/?id=' + f.id);
    page = await request(prefix + '/requests/?id=' + f.id); assert(page.html.includes('lang="nb"')); assert(page.html.includes('Lagre status')); assert(!page.html.includes('Зберегти статус'));
    await request(prefix + '/language.php', { csrf: csrf(page), locale: 'uk', return: 'account/' });
    page = await request(prefix + '/account/');
    assert.equal((await request(prefix + '/logout.php', { csrf: csrf(page) })).status, 303);
    assert.equal((await request(prefix + '/account/')).status, 303);
    page = await request(prefix + '/login.php'); assert(page.html.includes('lang="uk"'));
    pass('Norwegian switch, return URL, language persisted on login and secure logout');
    for (let i = 0; i < 8; i++) assert.equal((await request(prefix + '/login.php', { csrf: csrf(page), email: tag + '@example.test', password: 'invalid' }, { 'X-Real-IP': '192.0.2.' + (i + 1) })).status, 200);
    const blocked = await request(prefix + '/login.php', { csrf: csrf(page), email: tag + '@example.test', password: 'invalid' });
    assert.equal(blocked.status, 429); assert(blocked.html.includes('Забагато спроб'));
    pass('Account limit after eight attempts; spoofed client IP does not bypass');
    for (let i = 0; i < 10; i++) assert.equal((await request(prefix + '/login.php', { csrf: csrf(page), email: tag + i + '@example.test', password: 'invalid' }, { 'X-Real-IP': '192.0.2.' + (i + 1) })).status, 200);
    assert.equal((await request(prefix + '/login.php', { csrf: csrf(page), email: tag + 'ip@example.test', password: 'invalid' })).status, 429);
    pass('IP limit after twenty total attempts across different accounts');
    const home = await request('/'); assert.equal(home.status, 200); assert(home.html.includes('lang="nb"')); assert(!home.html.includes(prefix + '/'));
    pass('Public site remains Norwegian without admin path disclosure');
    fs.writeFileSync('.qa/admin-i18n-http-results.json', JSON.stringify({ passed: true, groups }, null, 2));
  } finally { console.log(fixture('cleanup')); }
})().catch(error => { console.error(error); process.exitCode = 1; });

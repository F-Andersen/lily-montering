const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const project = 'fiksitt-auth-qa-' + randomBytes(6).toString('hex');
const tag = randomBytes(6).toString('hex');
const output = process.env.AUTH_QA_ARTIFACT_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'fiksitt-auth-qa-'));
const source = path.join(output, project);
const compose = ['compose', '-f', path.join(source, 'tests/admin-auth.compose.yml'), '-p', project];
const prefix = '/adfiksittmin';
const groups = [];
let base, started = false, fixture;
function docker(args, timeout = 120000) {
  return execFileSync('docker', [...compose, ...args], { cwd: source, encoding: 'utf8', timeout, stdio: ['pipe', 'pipe', 'pipe'] });
}
function prepareSource() {
  const tracked = execFileSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8' }).split('\0').filter(Boolean);
  const files = new Set([...tracked, 'tests/admin-auth.php', 'tests/admin-auth-http.cjs', 'tests/admin-auth.compose.yml']);
  fs.mkdirSync(source, { recursive: true });
  for (const relative of files) {
    const input = path.resolve(root, relative), target = path.resolve(source, relative);
    assert(input.startsWith(root + path.sep) && target.startsWith(source + path.sep), 'Invalid source path');
    assert(!fs.lstatSync(input).isSymbolicLink(), 'QA source cannot contain symlinks');
    assert(!/^(?:config(?:\.local[^/]*)?\.php$|\.env(?!\.example$)|(?:private|secrets|backups|\.qa)\/)/.test(relative), 'Private configuration must not be tracked');
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.copyFileSync(input, target);
  }
}
function state(mode) {
  const value = docker(['exec', '-T', '-e', `AUTH_QA_TAG=${tag}`, 'web', 'php', 'tests/admin-auth.php', mode]).trim();
  return ['create', 'state', 'core'].includes(mode) ? JSON.parse(value) : value;
}
function client() {
  let cookie = '';
  return async (route, fields, extraHeaders = {}) => {
    const response = await fetch(base + prefix + route, {
      method: fields ? 'POST' : 'GET', redirect: 'manual', signal: AbortSignal.timeout(15000),
      headers: { ...(cookie ? { Cookie: cookie } : {}), ...extraHeaders },
      body: fields && new URLSearchParams(fields),
    });
    const previousCookie = cookie;
    for (const header of response.headers.getSetCookie()) if (header.startsWith('lily_session=')) cookie = header.split(';')[0];
    return { status: response.status, headers: response.headers, html: await response.text(), cookie, previousCookie };
  };
}
const csrf = page => page.html.match(/name="csrf" value="([a-f0-9]{64})"/)[1];
const pass = name => { groups.push(name); console.log('PASS ' + name); };
async function login(user, email, password) {
  const page = await user('/login.php');
  return user('/login.php', { csrf: csrf(page), email, password });
}
(async () => {
  fs.mkdirSync(output, { recursive: true });
  try {
    prepareSource();
    started = true;
    docker(['up', '-d', '--build', '--wait', '--wait-timeout', '120'], 300000);
    const address = docker(['port', 'web', '80']).trim();
    assert(/^127\.0\.0\.1:\d+$/.test(address), 'QA must bind only to loopback');
    base = 'http://' + address;
    let ready = false;
    for (let attempt = 0; attempt < 30; attempt++) {
      try {
        const response = await fetch(base + prefix + '/login.php', { signal: AbortSignal.timeout(1000) });
        await response.text();
        if (response.status === 200) { ready = true; break; }
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 200));
    }
    assert(ready, 'QA login did not become ready');
    fixture = state('create');
    const user = client();
    let page = await user('/login.php');
    assert.equal((await user('/login.php', { csrf: 'invalid', email: fixture.email, password: fixture.password })).status, 403);
    for (let i = 0; i < 8; i++) {
      const attempt = await user('/login.php', { csrf: csrf(page), email: fixture.aliases[i % fixture.aliases.length], password: 'wrong' });
      assert.equal(attempt.status, 200);
    }
    const blocked = await user('/login.php', { csrf: csrf(page), email: fixture.email.toUpperCase(), password: 'wrong' });
    assert.equal(blocked.status, 429, 'SQL-equivalent email aliases must share one account budget');
    assert.equal(blocked.headers.get('retry-after'), '900');
    assert.equal((await login(client(), fixture.otherEmail, fixture.password)).status, 303, 'Unrelated account must retain its own budget');
    pass('SQL-equivalent accented/case email aliases share eight attempts; other account and CSRF remain intact');

    state('reset-limits');
    page = await user('/login.php');
    for (let i = 0; i < 8; i++) assert.equal((await user('/login.php', { csrf: csrf(page), email: 'unknown-' + fixture.email, password: 'wrong' })).status, 200);
    assert.equal((await user('/login.php', { csrf: csrf(page), email: 'unknown-' + fixture.email, password: 'wrong' })).status, 429);
    state('reset-limits');
    for (let i = 0; i < 20; i++) assert.equal((await user('/login.php', { csrf: csrf(page), email: `unknown-${i}-${fixture.email}`, password: 'wrong' }, { 'X-Real-IP': `192.0.2.${i + 1}` })).status, 200);
    assert.equal((await login(user, fixture.email, fixture.password)).status, 429);
    pass('Unknown-identity budget and twenty-attempt IP limit reject spoofed client IPs');

    state('reset-limits');
    state('legacy');
    assert.equal(state('state').needsRehash, true);
    const owner = client(), other = client();
    page = await owner('/login.php');
    assert.equal((await owner('/language.php', { csrf: csrf(page), locale: 'uk', return: 'login.php' })).status, 303);
    page = await login(owner, fixture.email.toUpperCase(), fixture.password);
    assert.equal(page.status, 303);
    assert.notEqual(page.cookie, page.previousCookie);
    assert.deepEqual(state('state'), { needsRehash: false, originalPasswordWorks: true });
    page = await owner('/account/');
    assert.equal(page.status, 200);
    assert(page.html.includes('lang="uk"'));
    assert.equal((await login(other, fixture.email, fixture.password)).status, 303);
    const newPassword = randomBytes(24).toString('hex');
    const fields = { current_password: fixture.password, new_password: newPassword, confirm_password: newPassword };
    assert.equal((await owner('/account/', { ...fields, csrf: 'wrong' })).status, 403);
    assert.equal((await owner('/account/', { ...fields, current_password: 'wrong', csrf: csrf(page) })).status, 422);
    const changed = await owner('/account/', { ...fields, csrf: csrf(page) });
    assert.equal(changed.status, 303);
    assert.notEqual(changed.cookie, changed.previousCookie);
    assert.equal((await other('/account/')).status, 303);
    assert.equal((await login(client(), fixture.email, fixture.password)).status, 200);
    assert.equal((await login(client(), fixture.email, newPassword)).status, 303);
    page = await owner('/account/');
    assert.equal((await owner('/logout.php', { csrf: csrf(page) })).status, 303);
    assert.equal((await owner('/account/')).status, 303);
    assert((await owner('/login.php')).html.includes('lang="uk"'));
    pass('Normal legacy upgrade, Ukrainian locale, rotation, password change, other-session revocation and logout');

    state('reset-limits');
    state('inactive');
    assert.equal((await login(client(), fixture.email, newPassword)).status, 200);
    for (let i = 0; i < 7; i++) assert.equal((await login(client(), fixture.aliases[i % 3], newPassword)).status, 200);
    assert.equal((await login(client(), fixture.email, newPassword)).status, 429);
    pass('Inactive account cannot authenticate and retains the same alias-proof budget');
    const core = state('core');
    assert.equal(core.passed, true);
    assert.deepEqual(core.groups, ['normal', 'change', 'rehash', 'inactive', 'deleted', 'binary']);
    pass('Two-connection stale snapshots cannot undo password changes, rehashes, deactivation, deletion or binary hash changes');
    console.log(docker(['exec', '-T', 'web', 'php', 'tests/proxy-https.php']).trim());
    pass('Existing trusted-proxy regression checks');
  } finally {
    try {
      if (fixture) state('cleanup');
    } finally {
      try {
        fs.writeFileSync(path.join(output, 'admin-auth-results.json'), JSON.stringify({ passed: groups.length === 6, groups }, null, 2));
      } finally {
        if (started) docker(['down', '-v', '--remove-orphans']);
      }
    }
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });

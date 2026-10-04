/* Local isolated HTTP/SMTP regression checks; browser QA is documented separately. */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { execFileSync, spawn } = require('node:child_process');
const base = 'http://127.0.0.1:18082';
const inbox = 'http://127.0.0.1:18026';
const compose = ['compose', '--env-file', '.qa/fiksitt-seo-qa.env', '-p', 'fiksitt-seo-qa'];
const php = code => execFileSync('docker', [...compose, 'exec', '-T', 'web', 'php', '-r', code], { encoding: 'utf8' });
const sql = (statement, params = []) => JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + Buffer.from(JSON.stringify([statement, params])).toString('base64') + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
const tag = crypto.randomBytes(8).toString('hex');
const email = `qa-mail-${tag}@lily.test`;
const password = crypto.randomBytes(24).toString('hex');
let cookie = '';
let id;
let spamId;
let adminCreated = false;
let groups = 0;
function pass(message) { groups++; console.log('PASS ' + message); }
async function request(path, form, authenticated = true) {
  const response = await fetch(base + path, {
    method: form ? 'POST' : 'GET', redirect: 'manual',
    headers: { ...(authenticated && cookie ? { Cookie: cookie } : {}), ...(form ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
    ...(form ? { body: new URLSearchParams(form) } : {}),
  });
  for (const value of response.headers.getSetCookie()) if (authenticated && value.startsWith('lily_session=')) cookie = value.split(';')[0];
  return response;
}
async function token(path) { const html = await (await request(path)).text(); const match = html.match(/name="csrf" value="([^"]+)"/); assert(match); return match[1]; }
async function messages() { return (await (await fetch(inbox + '/api/v1/messages')).json()).messages; }
async function count() { return (await (await fetch(inbox + '/api/v1/messages')).json()).total; }
(async () => {
  assert.deepEqual(JSON.parse(php("require 'app/bootstrap.php'; echo json_encode([config()['app_env'],config()['app_url'],config()['database']['name'],config()['smtp']['host']]);")), ['local', base, 'fiksitt_seo_qa', 'mailpit']);
  const values = Buffer.from(JSON.stringify([email, password])).toString('base64');
  php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + values + "'),true); query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$v[0],password_hash($v[1],PASSWORD_DEFAULT)]);");
  adminCreated = true;
  assert.equal((await request('/admin/login.php', { csrf: await token('/admin/login.php'), email, password })).status, 303);
  let before = await count();
  assert.equal((await request('/admin/settings/', { action: 'test_email' }, false)).status, 303);
  assert.equal((await request('/admin/settings/', { action: 'test_email', csrf: 'invalid' })).status, 403);
  assert.equal((await request('/admin/settings/?action=test_email')).status, 200);
  assert.equal(await count(), before);
  pass('Authentication, CSRF and POST-only mail actions prevent unintended sending');
  let csrf = await token('/admin/settings/');
  for (let i = 0; i < 3; i++) assert.equal((await request('/admin/settings/', { action: 'test_email', csrf })).status, 303);
  const limited = await request('/admin/settings/', { action: 'test_email', csrf });
  assert.match(await limited.text(), /For mange testforsøk/);
  assert.equal(await count(), before + 3);
  pass('Configured-recipient test message works; rate limit blocks fourth send');
  const fields = [`QA MAIL ${tag}`, '+4791234567', 'customer@lily.test', 'Veggmontering', 'Oslo', 'Norsk æ ø å; українська.\n.dot-stuffed line\nPlain-text description.'];
  sql('INSERT INTO contact_requests(name,phone,email,service,area,message) VALUES (?,?,?,?,?,?)', fields);
  id = sql('SELECT id FROM contact_requests WHERE name=?', [fields[0]])[0].id;
  sql('INSERT INTO contact_requests(name,phone,email,service,area,message,status) VALUES (?,?,?,?,?,?,?)', [...fields.slice(0, 1).map(v => v + ' spam'), ...fields.slice(1), 'spam']);
  spamId = sql('SELECT id FROM contact_requests WHERE name=?', [fields[0] + ' spam'])[0].id;
  before = await count();
  csrf = await token('/admin/requests/?id=' + id);
  assert.equal((await request('/admin/requests/', { id, action: 'send_email', csrf: 'bad' })).status, 403);
  assert.equal((await request('/admin/requests/?id=' + id + '&action=send_email')).status, 200);
  assert.equal(await count(), before);
  assert.match(await (await request('/admin/requests/', { id: spamId, action: 'send_email', csrf })).text(), /Spam sendes ikke/);
  assert.equal(await count(), before);
  const lockCode = "require 'app/bootstrap.php'; $lock='fiksitt-mail-'.substr(hash('sha256',config()['database']['name']),0,16).'-" + id + "'; query('SELECT GET_LOCK(?,0)',[$lock]); echo \"READY\\n\"; fflush(STDOUT); fgets(STDIN); query('SELECT RELEASE_LOCK(?)',[$lock]);";
  const holder = spawn('docker', [...compose, 'exec', '-T', 'web', 'php', '-r', lockCode], { stdio: ['pipe', 'pipe', 'pipe'] });
  const closed = new Promise((resolve, reject) => { holder.once('error', reject); holder.once('close', code => code === 0 ? resolve() : reject(new Error('Lock helper failed'))); });
  await new Promise((resolve, reject) => { holder.stdout.once('data', data => String(data).includes('READY') ? resolve() : reject(new Error('Lock not acquired'))); holder.once('error', reject); });
  try {
    assert.match(await (await request('/admin/requests/', { id, action: 'send_email', csrf })).text(), /E-post kunne ikke sendes/);
    assert.equal(await count(), before);
    assert.equal(sql('SELECT email_sent FROM contact_requests WHERE id=?', [id])[0].email_sent, 0);
  } finally { holder.stdin.end('\n'); await closed; }
  pass('Concurrent delivery lock prevents a second worker from sending the same request');
  assert.equal((await request('/admin/requests/', { id, action: 'send_email', csrf })).status, 303);
  assert.equal(sql('SELECT email_sent FROM contact_requests WHERE id=?', [id])[0].email_sent, 1);
  assert.equal(await count(), before + 1);
  assert.equal((await request('/admin/requests/', { id, action: 'send_email', csrf })).status, 303);
  assert.equal(await count(), before + 1);
  pass('Unsent request retry succeeds; already accepted/spam/GET/invalid-CSRF do not send duplicates');
  const message = (await messages()).find(m => m.Subject === `Ny forespørsel #${id} fra fiksitt`);
  assert(message);
  const full = await (await fetch(inbox + '/api/v1/message/' + message.ID)).json();
  assert.equal(full.From.Address, 'website@lily.test');
  assert.equal(full.To[0].Address, 'inbox@lily.test');
  assert.equal(full.ReplyTo[0].Address, 'customer@lily.test');
  assert.match(full.Text, /Norsk æ ø å; українська/);
  assert.match(full.Text, /\n\.dot-stuffed line/);
  assert(full.Text.includes('/admin/requests/?id=' + id));
  pass('UTF-8, dot stuffing, recipient, verified sender, Reply-To and admin link retained');
  const validation = JSON.parse(php("require 'app/bootstrap.php'; require 'app/mail.php'; $c=config(); $out=[]; $c['app_env']='production'; $out[]=mail_configuration_error($c)!==''; $c['app_env']='local'; $c['smtp']['username']='test'; $c['smtp']['password']='secret'; $out[]=mail_configuration_error($c)!==''; $c['smtp']['encryption']='tls'; $c['smtp']['password']=''; $out[]=mail_configuration_error($c)!==''; $c=config(); foreach(['recipient_email','from_email','from_name'] as $k){$v=$c;$v[$k].=\"\r\nBcc: attacker@example.test\";$out[]=mail_configuration_error($v)!=='';} try{configured_mailer(config(),\"Subject\r\nBcc: attacker@example.test\",'body');$out[]=false;}catch(Throwable $e){$out[]=true;} try{configured_mailer(config(),'Subject','body',\"customer@lily.test\r\nBcc: attacker@example.test\");$out[]=false;}catch(Throwable $e){$out[]=true;} echo json_encode($out);"));
  assert(validation.every(Boolean));
  pass('Reject unencrypted production/authenticated SMTP, incomplete credentials and header injection');
  console.log(`${groups} mail notification groups passed. No external mail was sent.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
  if (id) sql('DELETE FROM contact_requests WHERE id=?', [id]);
  if (spamId) sql('DELETE FROM contact_requests WHERE id=?', [spamId]);
  if (adminCreated) sql('DELETE FROM admins WHERE email=?', [email]);
});

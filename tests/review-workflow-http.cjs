const assert = require('node:assert/strict');
const fs = require('node:fs');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const base = 'http://127.0.0.1:18084';
const tag = `QA HTTP REVIEWS ${randomBytes(6).toString('hex')}`;
const groups = [];
function state(mode) {
  const output = execFileSync('docker', ['compose', '--env-file', '.qa/fiksitt-reviews-qa.env', '-p', 'fiksitt-reviews-qa', 'exec', '-T', '-e', `QA_TAG=${tag}`, 'web', 'php', 'tests/review-http-state.php', mode], { encoding: 'utf8' });
  return mode === 'cleanup' ? output : JSON.parse(output);
}
function client() {
  let cookie = '';
  return async (path, fields, method) => {
    const response = await fetch(base + path, { method: method || (fields ? 'POST' : 'GET'), redirect: 'manual', headers: cookie ? { Cookie: cookie } : {}, body: fields ? new URLSearchParams(fields) : undefined });
    for (const value of response.headers.getSetCookie()) if (value.startsWith('lily_session=')) cookie = value.split(';')[0];
    return { status: response.status, headers: response.headers, html: await response.text() };
  };
}
function csrf(page) { return page.html.match(/name="csrf" value="([a-f0-9]{64})"/)[1]; }
function pass(name) { groups.push(name); console.log(`PASS ${name}`); }
(async () => {
  let fixture;
  try {
    fixture = state('create');
    const admin = client(), guest = client(), other = client();
    let page = await guest('/omtale.php');
    assert.equal(page.status, 200); assert.equal(page.headers.get('referrer-policy'), 'no-referrer'); assert.equal(page.headers.get('x-robots-tag'), 'noindex, nofollow');
    assert(!page.html.includes(tag)); assert(!page.html.includes('http-customer@example.test'));
    assert.equal((await guest('/omtale.php', { action: 'redeem', csrf: 'bad', invitation: 'a'.repeat(64) })).status, 403);
    assert.equal((await guest('/omtale.php', { action: 'redeem', csrf: csrf(page), 'invitation[]': 'bad' })).status, 422);
    assert.equal((await guest('/omtale.php', { body: 'x'.repeat(33000) })).status, 413);
    assert.equal((await guest('/omtale.php', null, 'PUT')).status, 405);
    assert.equal((await guest('/admin/reviews/')).status, 303);
    pass('Private no-referrer/noindex page, no enquiry PII, CSRF/scalar/body/method/auth guards');
    page = await admin('/admin/login.php');
    assert.equal((await admin('/admin/login.php', { csrf: csrf(page), email: fixture.email, password: fixture.password })).status, 303);
    const detail = `/admin/requests/?id=${fixture.id}`;
    page = await admin(detail);
    assert.equal((await admin(detail, { csrf: 'bad', id: fixture.id, action: 'create_review_invitation' })).status, 403);
    assert.equal((await admin(detail, { csrf: csrf(page), id: fixture.id, action: 'create_review_invitation' })).status, 303);
    page = await admin(detail);
    const link = page.html.match(/id="review-link" value="([^"]+)"/)[1];
    const token = new URL(link).hash.slice(7);
    assert.equal(token.length, 64); assert.equal(new URL(link).search, '');
    const mailBefore = (await (await fetch('http://127.0.0.1:18028/api/v1/messages')).json()).total;
    const mailFields = { csrf: csrf(page), id: fixture.id, action: 'send_review_invitation' };
    assert.equal((await admin(detail, mailFields)).status, 303);
    assert.equal((await admin(detail, mailFields)).status, 303);
    const mailAfter = await (await fetch('http://127.0.0.1:18028/api/v1/messages')).json();
    assert.equal(mailAfter.total, mailBefore + 1);
    assert(mailAfter.messages[0].To.some(to => to.Address === 'http-customer@example.test'));
    pass('Admin creates fragment invitation and sends once to original customer via Mailpit');
    const logs = execFileSync('docker', ['compose', '--env-file', '.qa/fiksitt-reviews-qa.env', '-p', 'fiksitt-reviews-qa', 'logs', '--no-color', 'web'], {encoding:'utf8'});
    assert(!logs.includes(token), 'Invitation token leaked into access logs');
    for (const user of [guest, other]) {
      page = await user('/omtale.php');
      const accepted = await user('/omtale.php', { csrf: csrf(page), action: 'redeem', invitation: token });
      assert.equal(accepted.status, 303); assert.equal(accepted.headers.get('location'), '/omtale.php');
    }
    page = await guest('/omtale.php');
    assert(page.html.includes('Navn som vises offentlig')); assert(!page.html.includes(token));
    const values = { csrf: csrf(page), action: 'submit', display_name: 'QA <script>alert(1)</script>', rating: '1', body: 'TEST ONLY: honest customer feedback <img src=x onerror=alert(1)>.', consent: '1' };
    assert.equal((await guest('/omtale.php', { ...values, consent: '0' })).status, 422);
    assert.equal((await guest('/omtale.php', { ...values, csrf: 'bad' })).status, 403);
    const secondPage = await other('/omtale.php');
    const otherCsrf = csrf(secondPage);
    const submissions = await Promise.all([guest('/omtale.php', values), other('/omtale.php', { ...values, csrf: csrf(secondPage) })]);
    assert.equal(submissions.filter(r => r.status === 303).length, 1);
    const data = state('state'); assert.equal(data.reviews.length, 1); assert.equal(data.reviews[0].status, 'pending'); assert(data.invitations[0].used_at);
    assert(!(await guest('/')).html.includes('honest customer feedback'));
    pass('Clean-session redemption, explicit consent, concurrent one-time submission, pending not public');
    const reviewId = data.reviews[0].id, reviewPath = `/admin/reviews/?id=${reviewId}`;
    page = await admin(reviewPath);
    assert(!page.html.includes('<script>alert(1)</script>')); assert(page.html.includes('&lt;script&gt;'));
    assert.equal((await admin(reviewPath, { csrf: 'bad', id: reviewId, status: 'approved' })).status, 403);
    assert.equal((await admin(reviewPath, { csrf: csrf(page), id: reviewId, status: 'approved', moderation_note: 'PRIVATE QA NOTE' })).status, 303);
    const home = (await guest('/')).html;
    assert(home.includes('honest customer feedback')); assert(home.includes('&lt;img src=x')); assert(!home.includes('PRIVATE QA NOTE')); assert(home.includes('aria-label="1 av 5 stjerner"'));
    const audit = state('state').reviews[0]; assert.equal(audit.moderated_by, fixture.adminId); assert(audit.moderated_at);
    page = await admin(reviewPath);
    assert.equal((await admin(reviewPath, { csrf: csrf(page), id: reviewId, status: 'rejected', moderation_note: 'Fixture cleanup' })).status, 303);
    assert(!(await guest('/')).html.includes('honest customer feedback'));
    pass('Authenticated CSRF-protected approval/rejection, XSS escaping and private audit');
    assert.equal((await other('/omtale.php', { csrf: otherCsrf, action: 'redeem', invitation: token })).status, 303);
    assert((await other('/omtale.php')).html.includes('Takk for omtalen.'));
    for (let i = 0; i < 35; i++) page = await other('/omtale.php', { csrf: otherCsrf, action: 'redeem', invitation: 'b'.repeat(64) });
    assert.equal(page.status, 429);
    pass('Used link acknowledgement and HTTP rate limit');
  } finally {
    state('cleanup');
    fs.writeFileSync('.qa/review-workflow-http-results.json', JSON.stringify({ groups, passed: groups.length === 5 }, null, 2));
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });

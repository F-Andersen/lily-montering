const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://localhost:8080";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local development only.");
const email = "qa-account-" + crypto.randomBytes(8).toString("hex") + "@lily.test";
const oldPassword = crypto.randomBytes(20).toString("hex");
const newPassword = crypto.randomBytes(20).toString("hex");
let browser;
let created = false;
function php(code) { return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" }); }
function sql(statement, params = []) {
  const encoded = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  return JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
}
async function token(context, route) {
  const response = await context.request.get(route);
  assert.equal(response.status(), 200);
  const match = (await response.text()).match(/name="csrf" value="([^"]+)"/);
  assert(match);
  return match[1];
}
async function login(context, password) {
  return context.request.post("/admin/login.php", { form: { csrf: await token(context, "/admin/login.php"), email, password } });
}
(async () => {
  assert.equal(php("require 'app/bootstrap.php'; echo config()['app_env'];"), "local");
  const hash = php("echo password_hash(base64_decode('" + Buffer.from(oldPassword).toString("base64") + "'),PASSWORD_DEFAULT);");
  sql("INSERT INTO admins (email,password_hash) VALUES (?,?)", [email, hash]);
  created = true;
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base, reducedMotion: "reduce" });
  const other = await browser.newContext({ baseURL: base });
  assert((await login(context, oldPassword)).url().endsWith("/admin/"));
  assert((await login(other, oldPassword)).url().endsWith("/admin/"));
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  fs.mkdirSync(".qa/admin-account", { recursive: true });
  for (const width of [320, 375, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of ["/admin/", "/admin/requests/", "/admin/services/", "/admin/categories/", "/admin/gallery/", "/admin/settings/", "/admin/account/"]) {
      const response = await page.goto(route);
      assert.equal(response.status(), 200);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), route + " overflow");
      if (route === "/admin/" || route === "/admin/account/") await page.screenshot({ path: `.qa/admin-account/${width}-${route === "/admin/" ? "dashboard" : "account"}.png`, fullPage: true });
    }
  }
  await page.goto("/admin/");
  await page.locator(".quick-actions a[href*='services']").click();
  assert.equal(await page.locator("#title").count(), 1);
  await page.goto("/admin/");
  await page.locator(".quick-actions a[href*='gallery']").click();
  assert.equal(await page.locator("#image_file").count(), 1);
  const cookie = (await context.cookies()).find(cookie => cookie.name === "lily_session").value;
  const fields = { current_password: oldPassword, new_password: newPassword, confirm_password: newPassword };
  assert.equal((await context.request.post("/admin/account/", { form: { ...fields, csrf: "invalid" } })).status(), 403);
  const bad = await context.request.post("/admin/account/", { form: { ...fields, current_password: "not-the-password", csrf: await token(context, "/admin/account/") } });
  assert.equal(bad.status(), 422);
  const mismatch = await context.request.post("/admin/account/", { form: { ...fields, confirm_password: "not-matching", csrf: await token(context, "/admin/account/") } });
  assert.equal(mismatch.status(), 422);
  const result = await context.request.post("/admin/account/", { form: { ...fields, csrf: await token(context, "/admin/account/") } });
  assert.equal(result.status(), 200);
  assert.match(await result.text(), /Passordet er endret/);
  assert.notEqual((await context.cookies()).find(cookie => cookie.name === "lily_session").value, cookie);
  assert((await other.request.get("/admin/")).url().endsWith("/admin/login.php"));
  const fresh = await browser.newContext({ baseURL: base });
  assert.match(await (await login(fresh, oldPassword)).text(), /Feil e-postadresse/);
  assert((await login(fresh, newPassword)).url().endsWith("/admin/"));
  const denied = php("putenv('ADMIN_ALLOWED_IPS=192.0.2.1'); $_SERVER['REMOTE_ADDR']='192.0.2.2'; require 'app/auth.php'; echo 'ACCESS_ALLOWED';");
  assert(!denied.includes("ACCESS_ALLOWED"));
  const allowed = php("putenv('ADMIN_ALLOWED_IPS=127.0.0.1'); $_SERVER['REMOTE_ADDR']='127.0.0.1'; require 'app/auth.php'; echo 'ACCESS_ALLOWED';");
  assert.equal(allowed, "ACCESS_ALLOWED");
  assert.deepEqual(errors, []);
  fs.writeFileSync(".qa/admin-account/results.json", JSON.stringify({ testedAt: new Date().toISOString(), responsiveViews: 35, passwordChange: "PASS", sessionInvalidation: "PASS", accessPolicy: "PASS" }, null, 2));
  console.log("PASS: 35 admin views, dashboard actions, password change, CSRF, old-password rejection, session rotation/invalidation and IP access policy.");
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (browser) await browser.close();
  if (created) sql("DELETE FROM admins WHERE email=?", [email]);
});

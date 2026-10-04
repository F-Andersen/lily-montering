const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://localhost:8080";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local development only.");
const email = "qa-delivery-" + crypto.randomBytes(8).toString("hex") + "@lily.test";
const password = crypto.randomBytes(24).toString("hex");
let browser;
let created = false;
function php(code) { return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" }); }
function sql(statement, params = []) {
  const encoded = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  return JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
}
(async () => {
  assert.equal(php("require 'app/bootstrap.php'; echo config()['app_env'];"), "local");
  const hash = php("echo password_hash(base64_decode('" + Buffer.from(password).toString("base64") + "'),PASSWORD_DEFAULT);");
  sql("INSERT INTO admins (email,password_hash) VALUES (?,?)", [email, hash]);
  created = true;
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base, reducedMotion: "reduce" });
  const page = await context.newPage();
  await page.goto("/admin/login.php");
  await page.locator("#email").fill(email);
  await page.locator("#password").fill(password);
  await Promise.all([page.waitForURL("**/admin/"), page.locator("button[type=submit]").click()]);
  for (const width of [320, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto("/admin/settings/");
    const delivery = await page.locator("section[aria-labelledby='delivery-heading']").textContent();
    assert.match(delivery, /inbox@lily.test/);
    assert.match(delivery, /website@lily.test/);
    assert.match(delivery, /SMTP/);
    assert.equal(await page.locator("a[href$='/admin/requests/']").count() >= 1, true);
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  }
  console.log("PASS local admin: mail recipient/sender/transport visible, request link and mobile/desktop layout.");
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (browser) await browser.close();
  if (created) sql("DELETE FROM admins WHERE email=?", [email]);
});

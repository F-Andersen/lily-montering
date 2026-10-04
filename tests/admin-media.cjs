const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://127.0.0.1:18082";
if (process.env.COMPOSE_PROJECT_NAME !== "fiksitt-seo-qa" || !/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Isolated local fiksitt-seo-qa only.");
const email = "qa-media-" + crypto.randomBytes(8).toString("hex") + "@lily.test";
const password = crypto.randomBytes(24).toString("hex");
let browser;
let verified = false;
function php(code) { return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" }); }
function sql(statement, params = []) {
  const data = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  return JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + data + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
}
async function loaded(page, selector) {
  await page.locator(selector).evaluateAll(images => images.forEach(img => { img.loading = "eager"; }));
  await page.waitForFunction(selector => [...document.querySelectorAll(selector)].every(img => img.complete && img.naturalWidth > 0), selector);
}
async function main() {
  assert.deepEqual(JSON.parse(php("require 'app/bootstrap.php'; echo json_encode([config()['app_env'],config()['app_url']]);")), ["local", base]);
  verified = true;
  const before = JSON.stringify(sql("SELECT * FROM services ORDER BY id"));
  const encoded = Buffer.from(JSON.stringify([email, password])).toString("base64");
  php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$v[0],password_hash($v[1],PASSWORD_DEFAULT)]);");
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base });
  const loginPage = await context.request.get("/admin/login.php");
  const csrf = (await loginPage.text()).match(/name="csrf" value="([^"]+)"/)[1];
  assert.equal((await context.request.post("/admin/login.php", { form: { csrf, email, password } })).status(), 200);
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  fs.mkdirSync(".qa/admin-media", { recursive: true });
  for (const width of [375, 947, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + "/admin/services/");
    const rowCount = await page.locator("tbody tr").count();
    assert.equal(await page.locator("tbody img").count(), rowCount);
    await loaded(page, "tbody img");
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: ".qa/admin-media/services-" + width + ".png", fullPage: true });
  }
  const services = sql("SELECT id,slug,image FROM services ORDER BY id");
  for (const service of services) {
    await page.goto(base + "/tjenester/" + service.slug);
    await loaded(page, ".detail-image img");
    const publicImage = await page.locator(".detail-image img").getAttribute("src");
    await page.goto(base + "/admin/services/?id=" + service.id);
    assert.equal(await page.locator(".image-preview").count(), 1);
    await loaded(page, ".image-preview");
    assert.equal(await page.locator(".image-preview").getAttribute("src"), publicImage);
    assert.equal(await page.locator(".image-preview-group .field-meta").count(), service.image === "" ? 1 : 0);
  }
  // A custom slug still inherits its category's photo; invalid paths safely fall back.
  const cases = JSON.parse(php("require 'app/content.php'; $rows=[['title'=>'QA','slug'=>'custom-slug','category_slug'=>'kjokkenmontering','image'=>''],['title'=>'QA','slug'=>'custom-slug','category_slug'=>'kjokkenmontering','image'=>'assets/uploads/missing.jpg'],['title'=>'QA','slug'=>'custom-slug','category_slug'=>'kjokkenmontering','image'=>'assets/images/tv-og-oppbevaringsvegg.jpg']]; echo json_encode(array_map('service_media',$rows));"));
  assert.equal(cases[0][0], "assets/images/veggskap-i-tre.jpg");
  assert.equal(cases[1][0], cases[0][0]);
  assert.equal(cases[2][0], "assets/images/tv-og-oppbevaringsvegg.jpg");
  assert.equal(JSON.stringify(sql("SELECT * FROM services ORDER BY id")), before);
  assert.deepEqual(errors, []);
  console.log("PASS All service thumbnails and editor previews match public photos at 375/947/1440px; category/custom/missing-path precedence works; service data unchanged.");
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (verified) sql("DELETE FROM admins WHERE email=?", [email]);
  if (browser) await browser.close();
});

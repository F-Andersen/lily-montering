const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://127.0.0.1:18081";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local QA only.");
function php(code) {
  return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" });
}
let browser;
async function main() {
  const config = JSON.parse(php("require 'app/bootstrap.php'; echo json_encode([config()['app_env'],config()['app_url'],settings()['company_name']]);"));
  assert.deepEqual(config, ["local", base, "fiksitt"], "Use the isolated local fiksitt preview, never the production tunnel.");
  const message = php("require 'app/mail.php'; echo build_message(['name'=>'QA','phone'=>'+4712345678','email'=>'','service'=>'Montering','area'=>'QA','message'=>'Test']);");
  assert.match(message, /fra fiksitt-nettsiden/);
  assert(!message.includes("LILY"));
  const admin = php("require 'app/admin-layout.php'; start_session(); $_SESSION=[]; admin_header('Oversikt'); admin_footer();");
  assert.match(admin, /fiksitt admin/);
  assert.match(admin, /aria-label="fiksitt admin"/);
  assert.match(admin, /fiksitt-wordmark-v2\.webp/);
  assert(!admin.includes("LILY"));
  const staticHome = fs.readFileSync("index.html", "utf8");
  const staticJson = staticHome.match(/<script type="application\/ld\+json">([^]*?)<\/script>/)[1];
  assert.equal(JSON.parse(staticJson).name, "fiksitt");
  const staticHash = crypto.createHash("sha256").update(staticJson).digest("base64");
  assert(fs.readFileSync(".htaccess", "utf8").includes("sha256-" + staticHash));
  assert(!staticHome.includes("LILY Montering"));
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  fs.mkdirSync(".qa/branding", { recursive: true });
  for (const width of [320, 375, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto(base);
    assert.equal(response.status(), 200);
    assert.match(await page.title(), /\| fiksitt$/);
    assert.equal(await page.locator(".brand strong").textContent(), "fiksitt");
    assert.equal(await page.locator("#hero-title").textContent(), "fiksitt");
    assert.match(await page.locator(".site-footer").textContent(), /fiksitt/);
    assert(await page.locator(".brand-logo").evaluate(img => img.complete && img.naturalWidth > 0));
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    const json = await page.locator('script[type="application/ld+json"]').textContent();
    assert.equal(JSON.parse(json).name, "fiksitt");
    assert(response.headers()["content-security-policy"].includes("sha256-" + crypto.createHash("sha256").update(json).digest("base64")));
    await page.screenshot({ path: ".qa/branding/home-" + width + ".png" });
  }
  for (const path of ["/tjenester", "/tjenester/kontor-og-naering", "/admin/login.php", "/404.php", "/contact.php"]) {
    const response = await context.request.get(path);
    const html = await response.text();
    assert.match(html, /fiksitt/);
    assert(!html.includes("LILY Montering"));
  }
  const manifest = await (await context.request.get("/site.webmanifest?v=fiksitt")).json();
  assert.equal(manifest.name, "fiksitt");
  assert.equal(manifest.short_name, "fiksitt");
  for (const path of ["assets/fiksitt-wordmark-v2.webp", "assets/fiksitt-icon-v2-32.png", "assets/fiksitt-icon-v2-180.png", ...manifest.icons.map(icon => icon.src)]) {
    const response = await context.request.get("/" + path);
    assert.equal(response.status(), 200);
    assert((await response.body()).length > 100);
  }
  assert.deepEqual(errors, []);
  console.log("PASS fiksitt branding: public/admin/mail/static pages, icons, manifest, exact CSP hashes and 4 responsive viewports. No forms sent or database writes.");
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => { if (browser) await browser.close(); });

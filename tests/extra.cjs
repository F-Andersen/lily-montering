const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://localhost:8080";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local QA only.");
const name = "QA-nojs-" + crypto.randomBytes(8).toString("hex");
const groups = [];
let browser;
function php(code) { return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" }); }
async function main() {
  assert.equal(JSON.parse(php("require 'app/bootstrap.php'; echo json_encode(config()['app_env']);")), "local");
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base, javaScriptEnabled: false, reducedMotion: "reduce" });
  const page = await context.newPage();
  await page.goto(base);
  await page.locator("#contact-form").scrollIntoViewIfNeeded();
  await page.locator("#name").fill(name);
  await page.locator("#phone").fill("+47 12345678");
  await page.locator("#email").fill("nojs-customer@lily.test");
  await page.locator("#service").selectOption(await page.locator("#service option").nth(1).getAttribute("value"));
  await page.locator("#area").fill("QA område");
  await page.locator("#message").fill("Jeg trenger hjelp med montering av et skap hjemme.");
  await page.locator("#privacy").check();
  await page.waitForTimeout(4100);
  await Promise.all([page.waitForURL("**/contact.php"), page.locator(".form-button").click()]);
  assert.match(await page.locator("h1").textContent(), /Takk/);
  const b64 = Buffer.from(name).toString("base64");
  assert.equal(Number(php("require 'app/bootstrap.php'; echo query('SELECT COUNT(*) FROM contact_requests WHERE name=?',[base64_decode('" + b64 + "')])->fetchColumn();")), 1);
  groups.push("No-JavaScript form submits, renders HTML confirmation and stores request");
  const attack = '<img src=x onerror=alert("xss")>';
  const response = await context.request.get("/tjenester?category=" + encodeURIComponent(attack));
  assert.equal(response.status(), 404);
  assert(!(await response.text()).includes(attack));
  groups.push("Reflected XSS in catalog query is not emitted");
  const secure = JSON.parse(php("require 'app/bootstrap.php'; $_SERVER['HTTPS']='on'; start_session(); echo json_encode(session_get_cookie_params());"));
  assert(secure.secure && secure.httponly && secure.samesite === "Lax");
  groups.push("HTTPS session configuration sets Secure, HttpOnly and SameSite");
  const head = await context.request.get("/");
  assert.match(head.headers()["cache-control"], /no-store/);
  const csp = head.headers()["content-security-policy"];
  assert(!csp.includes("unsafe-inline") && !csp.includes("unsafe-eval"));
  const home = await head.text();
  const json = home.match(/<script type="application\/ld\+json">([^]*?)<\/script>/)[1];
  const hash = crypto.createHash("sha256").update(json).digest("base64");
  assert(csp.includes("sha256-" + hash));
  groups.push("No-store headers and exact JSON-LD CSP hash verified");
  await page.goto(base);
  const sizes = await page.locator(".hero source").getAttribute("srcset");
  assert.match(sizes, /675w/);
  groups.push("Hero responsive image descriptor matches actual 675px asset width");
  console.log(groups.map(g => "PASS " + g).join("\n"));
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  try {
    const b64 = Buffer.from(name).toString("base64");
    php("require 'app/bootstrap.php'; query('DELETE FROM contact_requests WHERE name=?',[base64_decode('" + b64 + "')]);");
    php("foreach(glob(sys_get_temp_dir().'/lily-limits-*/*.json') as $file) unlink($file);");
  } catch (error) { console.error(error.message); process.exitCode = 1; }
  if (browser) await browser.close();
  fs.mkdirSync(".qa", { recursive: true });
  fs.writeFileSync(".qa/extra-results.json", JSON.stringify({ generated: new Date().toISOString(), passed: process.exitCode !== 1, groups }, null, 2));
});

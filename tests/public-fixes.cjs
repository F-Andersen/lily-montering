const { chromium } = require("playwright");
const sharp = require("sharp");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const crypto = require("node:crypto");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://localhost:8080";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local development only.");
const name = "QA-public-fixes-" + crypto.randomBytes(8).toString("hex");
const groups = [];
let browser;
function php(code) {
  return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" });
}
function sql(statement, params = []) {
  const encoded = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  return JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
}
function pass(group) { groups.push(group); console.log("PASS " + group); }
async function images(page) {
  await page.locator("img").evaluateAll(async images => {
    images.forEach(img => { img.loading = "eager"; });
    await Promise.all(images.map(img => img.decode().catch(() => {})));
  });
  assert.deepEqual(await page.locator("img").evaluateAll(images => images.filter(img => !img.naturalWidth || !img.naturalHeight).map(img => img.src)), []);
}
async function main() {
  const config = JSON.parse(php("require 'app/bootstrap.php'; $c=config(); echo json_encode([$c['app_env'],$c['smtp']['host'],$c['recipient_email']]);"));
  assert.deepEqual(config, ["local", "mailpit", "inbox@lily.test"], "Email tests require local Mailpit, never real recipients.");
  const cases = [["123 45 678", "NO", "+4712345678"], ["050 806 8159", "UA", "+380508068159"], ["+380 50 806 8159", "NO", "+380508068159"], ["0044 7700 900123", "OTHER", "+447700900123"], ["1234567", "NO", null], ["+1+23456789", "NO", null], ["+4712345678", "BAD", null], ["12345678", "OTHER", null]];
  const encoded = Buffer.from(JSON.stringify(cases)).toString("base64");
  const actual = JSON.parse(php("require 'app/phone.php'; $cases=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(array_map(fn($c)=>normalize_phone($c[0],$c[1]),$cases));"));
  assert.deepEqual(actual, cases.map(c => c[2]));
  pass("Server phone normalization and invalid-format rejection");
  const override = "assets/images/garderobe-under-skratt-tak.jpg";
  const media = JSON.parse(php("require 'app/content.php'; echo json_encode([service_media(['image'=>'" + override + "','title'=>'Custom','slug'=>'kontor-og-naering'])[0],service_media(['image'=>'assets/uploads/not-found.jpg','title'=>'Missing','slug'=>'kontor-og-naering'])[0]]);"));
  assert.deepEqual(media, [override, "assets/images/lys-trehylle-montert.jpg"]);
  pass("Custom images preserved; missing uploads use real photo fallback");
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base, reducedMotion: "reduce" });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  page.on("console", msg => { if (msg.type() === "error") errors.push(msg.text()); });
  await page.goto("/");
  const links = await page.locator(".service-body h3 a").evaluateAll(nodes => nodes.map(node => node.getAttribute("href")));
  assert.equal(links.length, 6);
  fs.mkdirSync(".qa/public-fixes", { recursive: true });
  for (const width of [320, 375, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of ["/", "/tjenester", ...links]) {
      const response = await page.goto(route);
      assert.equal(response.status(), 200);
      await images(page);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Overflow: ${width} ${route}`);
      if (route === "/" || route === "/tjenester") {
        assert.equal(await page.locator(".service-photo img").count(), 6);
        const heights = await page.locator(".service-photo").evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().height));
        assert(Math.max(...heights) - Math.min(...heights) < 1);
      } else {
        assert.equal(await page.locator(".detail-image img").count(), 1);
        const intro = (await page.locator(".detail-intro").textContent()).trim();
        assert(!(await page.locator(".detail-paragraph").allTextContents()).some(text => text.trim() === intro));
        assert(await page.locator(".site-footer").evaluate(node => node.getBoundingClientRect().bottom + scrollY >= innerHeight - 1));
      }
      if (route === "/tjenester" || route === links[0]) {
        await page.screenshot({ path: `.qa/public-fixes/${width}-${route === "/tjenester" ? "catalog" : "detail"}.png`, fullPage: true });
      }
    }
    await page.goto("/");
    if (width < 900) {
      await page.locator(".menu-toggle").click();
      await page.locator("#primary-nav a[href$='#faq']").click();
      assert.equal(await page.locator(".menu-toggle").getAttribute("aria-expanded"), "false");
    } else await page.locator("#primary-nav a[href$='#faq']").click();
    await page.waitForTimeout(150);
    const offset = await page.evaluate(() => ({ section: document.querySelector("#faq").getBoundingClientRect().top, header: document.querySelector(".site-header").getBoundingClientRect().bottom }));
    assert(offset.section >= offset.header - 1, `Anchor hidden by header: ${width}`);
    await page.locator("#kontakt").scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);
    if (width < 900) assert(await page.locator(".mobile-action").evaluate(node => node.classList.contains("is-hidden")));
    await page.screenshot({ path: `.qa/public-fixes/${width}-contact.png`, fullPage: false });
    await page.locator(".lead-form").screenshot({ path: `.qa/public-fixes/${width}-form.png` });
  }
  assert.deepEqual(errors, []);
  const photo = await sharp("assets/images/lys-trehylle-montert-900.webp").stats();
  assert(photo.channels.some(channel => channel.stdev > 20));
  pass("All six card/detail photos, no duplicate intro, footer and anchor layouts at five widths");
  await page.goto("/");
  await page.locator("#phone").fill("+380 50 806 8159");
  assert.equal(await page.locator("#phone-country").inputValue(), "UA");
  await page.locator("#phone").fill("1234567");
  await page.locator("#phone-country").selectOption("NO");
  assert.equal(await page.locator("#phone").evaluate(node => node.validity.valid), false);
  await page.locator("#phone").fill("12345678");
  assert.equal(await page.locator("#phone").evaluate(node => node.validity.valid), true);
  const fields = { name, email: "customer@lily.test", service: await page.locator("#service option").nth(1).getAttribute("value"), area: "QA", message: "Dette er en lokal test av kontaktskjemaet, ikke en kundeforespørsel.", privacy: "1", website: "" };
  await page.locator("#name").fill(name);
  await page.locator("#email").fill(fields.email);
  await page.locator("#service").selectOption(fields.service);
  await page.locator("#area").fill(fields.area);
  await page.locator("#message").fill(fields.message);
  await page.locator("#privacy").check();
  await page.waitForTimeout(4100);
  const responsePromise = page.waitForResponse(response => response.url().endsWith("/contact.php") && response.request().method() === "POST");
  await page.locator(".form-button").click();
  const response = await responsePromise;
  assert.equal(response.status(), 200);
  assert.equal((await response.json()).ok, true);
  assert.match(await page.locator(".form-status").textContent(), /Takk/);
  let rows = sql("SELECT phone,email_sent FROM contact_requests WHERE name=?", [name]);
  assert.deepEqual(rows, [{ phone: "+4712345678", email_sent: 1 }]);
  const nojs = await browser.newContext({ baseURL: base, javaScriptEnabled: false, reducedMotion: "reduce" });
  const plain = await nojs.newPage();
  await plain.goto("/");
  await plain.locator("#name").fill(name);
  await plain.locator("#phone-country").selectOption("UA");
  await plain.locator("#phone").fill("050 806 8159");
  await plain.locator("#service").selectOption(fields.service);
  await plain.locator("#area").fill(fields.area);
  await plain.locator("#message").fill(fields.message);
  await plain.locator("#privacy").check();
  await plain.waitForTimeout(4100);
  await Promise.all([plain.waitForURL("**/contact.php"), plain.locator(".form-button").click()]);
  assert.match(await plain.locator("h1").textContent(), /Takk/);
  rows = sql("SELECT phone,email_sent FROM contact_requests WHERE name=? ORDER BY id", [name]);
  assert.deepEqual(rows, [{ phone: "+4712345678", email_sent: 1 }, { phone: "+380508068159", email_sent: 1 }]);
  const csrf = await page.locator("input[name='csrf']").inputValue();
  const invalid = await context.request.post("/contact.php", { headers: { Accept: "application/json" }, form: { ...fields, csrf, started_at: String(Date.now() - 5000), phone_country: "BAD", phone: "+4712345678" } });
  assert.equal(invalid.status(), 422);
  assert((await invalid.json()).errors.phone);
  pass("Country selector/autofill, JS and no-JS submissions saved with normalized phone and delivered to Mailpit");
  fs.writeFileSync(".qa/public-fixes/results.json", JSON.stringify({ testedAt: new Date().toISOString(), groups }, null, 2));
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  if (browser) await browser.close();
  try { sql("DELETE FROM contact_requests WHERE name=?", [name]); }
  catch (error) { console.error("Could not clean local QA records: " + error.message); process.exitCode = 1; }
});

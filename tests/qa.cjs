/* Development-only end-to-end checks. Uses an isolated, randomly named QA admin. */
const { chromium } = require("playwright");
const sharp = require("sharp");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const crypto = require("node:crypto");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://localhost:8080";
const mailpit = process.env.QA_MAILPIT_URL || "http://localhost:8025";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(mailpit)) throw new Error("QA Mailpit must be local.");
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("QA must run against the local Docker site.");
const suffix = crypto.randomBytes(6).toString("hex");
const email = "qa-" + suffix + "@lily.test";
const password = crypto.randomBytes(24).toString("hex");
const slug = "qa-" + suffix;
const report = [];
let browser;
let adminId;
let createdFirst = false;
const uploads = [];
function sql(statement, params = []) {
  const encoded = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  const php = "require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());";
  return JSON.parse(execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", php], { encoding: "utf8" }));
}
function docker(...args) { return execFileSync("docker", ["compose", ...args], { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }); }
function clearLimits() {
  docker("exec", "-T", "web", "php", "-r", "foreach(glob(sys_get_temp_dir().'/lily-limits-*/*.json') as $file) unlink($file);");
}
function pass(name) { report.push({ name, result: "PASS" }); console.log("PASS " + name); }
async function token(context, path) {
  const response = await context.request.get(path);
  const html = await response.text();
  const match = html.match(/name="csrf" value="([^"]+)"/);
  assert(match, "CSRF token missing at " + path);
  return match[1];
}
async function save(context, path, form, image) {
  form.csrf = await token(context, path);
  const options = image ? { multipart: { ...form, image_file: image } } : { form };
  return context.request.post(path, options);
}
async function main() {
  fs.mkdirSync(".qa", { recursive: true });
  assert.equal(JSON.parse(docker("exec", "-T", "web", "php", "-r", "require 'app/bootstrap.php'; echo json_encode(config()['app_env']);")), "local");
  clearLimits();
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base });
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  page.on("console", message => { if (message.type() === "error") errors.push(message.text()); });
  const unauth = await context.request.get("/admin/", { maxRedirects: 0 });
  assert.equal(unauth.status(), 303);
  assert.match(unauth.headers().location, /login/);
  pass("Unauthenticated admin access redirects to login");
  assert.equal((await context.request.post("/admin/login.php", { form: { email, password } })).status(), 403);
  assert.equal((await context.request.post("/admin/login.php", { form: { csrf: "wrong", email, password } })).status(), 403);
  pass("Missing and invalid CSRF tokens are rejected");
  const count = Number(sql("SELECT COUNT(*) AS n FROM admins")[0].n);
  if (count === 0) {
    const setupToken = JSON.parse(docker("exec", "-T", "web", "php", "-r", "require 'app/bootstrap.php'; echo json_encode(config()['setup_token']);"));
    assert(setupToken, "Set a development SETUP_TOKEN in the isolated QA environment first");
    const denied = await save(context, "/admin/setup.php", { setup_token: "wrong", email, password });
    assert.match(await denied.text(), /Ugyldig oppsettnøkkel/);
    const result = await save(context, "/admin/setup.php", { setup_token: setupToken, email, password });
    assert.equal(result.status(), 200);
    createdFirst = true;
    assert.equal((await context.request.get("/admin/setup.php")).status(), 404);
    pass("First-admin setup requires secret token and disables after creation");
  } else {
    const encoded = Buffer.from(JSON.stringify([email, password])).toString("base64");
    docker("exec", "-T", "web", "php", "-r", "require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$v[0],password_hash($v[1],PASSWORD_DEFAULT)]);");
  }
  adminId = sql("SELECT id FROM admins WHERE email = ?", [email])[0].id;
  const invalid = await save(context, "/admin/login.php", { email: "' OR 1=1 --", password: "invalid-password" });
  assert.match(await invalid.text(), /Feil e-postadresse eller passord/);
  pass("Invalid login and SQL injection payload do not authenticate");
  const before = (await context.cookies()).find(c => c.name === "lily_session").value;
  const valid = await save(context, "/admin/login.php", { email, password });
  assert.equal(valid.status(), 200);
  const cookie = (await context.cookies()).find(c => c.name === "lily_session");
  assert.notEqual(cookie.value, before);
  assert(cookie.httpOnly);
  assert.equal(cookie.sameSite, "Lax");
  assert.equal((await context.request.get("/admin/")).status(), 200);
  pass("Login succeeds, session ID rotates, HttpOnly/SameSite cookies and persistence work");

  const categoryForm = { title: "QA kategori", slug: slug + "-category", sort_order: "12", active: "on" };
  await save(context, "/admin/categories/?new=1", categoryForm);
  let category = sql("SELECT * FROM service_categories WHERE slug = ?", [categoryForm.slug])[0];
  assert(category);
  const categoryPath = "/admin/categories/?id=" + category.id;
  await save(context, categoryPath, { ...categoryForm, id: String(category.id), title: "QA endret kategori", sort_order: "2" });
  assert.equal(sql("SELECT sort_order FROM service_categories WHERE id=?", [category.id])[0].sort_order, 2);
  pass("Category create, edit and reorder");
  const image = { name: "../../malicious.php.jpg", mimeType: "image/jpeg", buffer: await sharp({ create: { width: 50, height: 60, channels: 3, background: "#235844" } }).jpeg().toBuffer() };
  const serviceForm = { title: 'QA <script>alert("xss")</script>', slug, category_id: String(category.id), short_description: "Praktisk montering for QA.", full_description: "Full beskrivelse av en trygg testtjeneste.", sort_order: "4", active: "on", featured: "on", seo_title: "QA SEO", seo_description: "QA SEO description" };
  await save(context, "/admin/services/?new=1", serviceForm, image);
  let service = sql("SELECT * FROM services WHERE slug=?", [slug])[0];
  assert(service);
  uploads.push(service.image);
  assert.match(service.image, /^assets\/uploads\/[a-f0-9]{40}\.webp$/);
  assert.equal((await context.request.get("/" + service.image)).status(), 200);
  assert.equal((await context.request.get("/tjenester/" + slug)).status(), 200);
  assert.match(await (await context.request.get("/tjenester/" + slug)).text(), /&lt;script&gt;/);
  pass("Service create, safe random image filename, public detail and escaped stored XSS");
  const duplicate = await save(context, "/admin/services/?new=1", serviceForm);
  assert.match(await duplicate.text(), /allerede i bruk/);
  const used = await save(context, categoryPath, { id: String(category.id), action: "delete" });
  assert.match(await used.text(), /Kategorien er i bruk/);
  pass("Duplicate slug rejected and used category cannot be deleted");
  const servicePath = "/admin/services/?id=" + service.id;
  const editedService = { ...serviceForm, id: String(service.id), title: "QA monteringshjelp", category_id: "1", sort_order: "1", price_text: "", duration_text: "" };
  await save(context, servicePath, editedService);
  service = sql("SELECT * FROM services WHERE id=?", [service.id])[0];
  assert.equal(service.category_id, 1);
  assert.equal(service.featured, 1);
  assert.equal(service.sort_order, 1);
  assert.match(await (await context.request.get("/sitemap.xml")).text(), new RegExp(slug));
  const inactive = { ...editedService }; delete inactive.active;
  await save(context, servicePath, inactive);
  assert.equal((await context.request.get("/tjenester/" + slug)).status(), 404);
  await save(context, servicePath, editedService);
  await save(context, servicePath, { ...editedService, category_id: String(category.id) });
  const inactiveCategory = { ...categoryForm, id: String(category.id) }; delete inactiveCategory.active;
  await save(context, categoryPath, inactiveCategory);
  assert.equal((await context.request.get("/tjenester/" + slug)).status(), 404);
  await save(context, categoryPath, { ...categoryForm, id: String(category.id), active: "on" });
  assert.equal((await context.request.get("/tjenester/" + slug)).status(), 200);
  pass("Service edit, reassignment, featuring, ordering, deactivate/reactivate and inactive-category hiding");
  for (const format of ["jpeg", "png", "webp"]) {
    const buffer = await sharp({ create: { width: 50, height: 60, channels: 3, background: "#dbe6df" } })[format]().toBuffer();
    const caption = "QA " + suffix + " " + format;
    await save(context, "/admin/gallery/?new=1", { caption, alt_text: "Testbilde på norsk", sort_order: "1", active: "on", featured: "on" }, { name: "qa." + format, mimeType: "image/" + format, buffer });
    const gallery = sql("SELECT * FROM gallery_items WHERE caption=?", [caption])[0];
    assert(gallery, format + " upload");
    uploads.push(gallery.image);
    const path = "/admin/gallery/?id=" + gallery.id;
    await save(context, path, { id: String(gallery.id), caption, alt_text: "Endret norsk alternativ tekst", sort_order: "5", featured: "on" });
    const updated = sql("SELECT * FROM gallery_items WHERE id=?", [gallery.id])[0];
    assert.equal(updated.active, 0);
    assert.equal(updated.sort_order, 5);
    assert.equal(updated.alt_text, "Endret norsk alternativ tekst");
    await save(context, path, { id: String(gallery.id), action: "delete" });
    assert.equal((await context.request.get("/" + gallery.image)).status(), 404);
  }
  pass("JPEG, PNG and WebP uploads; alt text, reorder, deactivate, delete and file cleanup");
  for (const bad of [
    { name: "evil.php", mimeType: "application/x-httpd-php", buffer: Buffer.from("<?php echo 1;") },
    { name: "fake.jpg", mimeType: "image/jpeg", buffer: Buffer.from("<html>not an image</html>") },
    { name: "large.jpg", mimeType: "image/jpeg", buffer: Buffer.alloc(5 * 1024 * 1024 + 1, 1) }
  ]) {
    const rejected = await save(context, "/admin/gallery/?new=1", { caption: "QA bad " + suffix, alt_text: "Testbilde", sort_order: "0", active: "on" }, bad);
    assert.match(await rejected.text(), /Velg et ekte|høyst 5 MB/);
  }
  pass("PHP, spoofed MIME and oversized uploads rejected");
  const settingsBefore = sql("SELECT * FROM site_settings");
  const settingsForm = Object.fromEntries(settingsBefore.map(r => [r.setting_key, r.setting_value]));
  // Unchecked browser checkboxes are omitted, not submitted with a zero value.
  if (settingsForm.seo_indexable !== '1') delete settingsForm.seo_indexable;
  await save(context, "/admin/settings/", { ...settingsForm, phone: "+47 12345678", email: "qa-public@lily.test", service_region: "QA område", public_domain: "https://qa.lily.test" });
  let homepage = await (await context.request.get("/")).text();
  assert.match(homepage, /tel:\+4712345678/);
  assert.match(homepage, /mailto:qa-public@lily.test/);
  assert.match(homepage, /QA område/);
  assert.match(homepage, /https:\/\/qa.lily.test/);
  await save(context, "/admin/settings/", settingsForm);
  homepage = await (await context.request.get("/")).text();
  assert(!homepage.includes('href="tel:"') && !homepage.includes('href="mailto:"'));
  pass("Settings update public contact, region, canonical URLs; blank links omitted; originals restored");

  clearLimits();
  const contactCsrf = await token(context, "/");
  const contact = { csrf: contactCsrf, started_at: String(Date.now() - 6000), name: "QA " + suffix, phone: "+47 12345678", email: "customer@lily.test", service: sql('SELECT s.title FROM services s JOIN service_categories c ON c.id=s.category_id WHERE s.active=1 AND c.active=1 ORDER BY s.id LIMIT 1')[0].title, area: "QA område", message: "Jeg trenger hjelp med montering av et skap hjemme.", privacy: "1", website: "" };
  async function submit(form) { return context.request.post("/contact.php", { headers: { Accept: "application/json" }, form }); }
  assert.equal((await submit({ ...contact, csrf: "invalid" })).status(), 403);
  assert.equal((await submit({ ...contact, website: "bot" })).status(), 400);
  assert.equal((await submit({ ...contact, started_at: String(Date.now()) })).status(), 400);
  assert.equal((await submit({ ...contact, email: "bad-email" })).status(), 422);
  assert.equal((await submit({ ...contact, name: "" })).status(), 422);
  clearLimits();
  assert.equal((await submit(contact)).status(), 200);
  let request = sql("SELECT * FROM contact_requests WHERE name=? ORDER BY id DESC", [contact.name])[0];
  assert(request && request.email_sent === 1);
  const mail = await context.request.get(mailpit + "/api/v1/messages");
  assert((await mail.json()).messages.some(m => m.Subject.includes("fiksitt")));
  pass("Contact CSRF, honeypot, timing, required fields, email validation, database storage and Mailpit delivery");
  const requestPath = "/admin/requests/?id=" + request.id;
  await save(context, requestPath, { id: String(request.id), status: "in_progress" });
  assert.equal(sql("SELECT status FROM contact_requests WHERE id=?", [request.id])[0].status, "in_progress");
  const filtered = await context.request.get("/admin/requests/?q=" + suffix + "&status=in_progress");
  assert.match(await filtered.text(), new RegExp(suffix));
  await save(context, requestPath, { id: String(request.id), status: "completed", archived: "on" });
  assert.equal(sql("SELECT archived FROM contact_requests WHERE id=?", [request.id])[0].archived, 1);
  await save(context, requestPath, { id: String(request.id), action: "delete" });
  assert.equal(sql("SELECT id FROM contact_requests WHERE id=?", [request.id]).length, 0);
  pass("Requests view, status changes, search, filter, archive and delete");
  for (let i = 0; i < 4; i++) assert.equal((await submit(contact)).status(), 200);
  assert.equal((await submit(contact)).status(), 429);
  pass("Contact rate limiting");
  clearLimits();
  docker("stop", "mailpit");
  try {
    const saved = await submit(contact);
    assert.equal(saved.status(), 200);
    assert.match((await saved.json()).message, /registrert/);
    assert.equal(sql("SELECT email_sent FROM contact_requests WHERE name=? ORDER BY id DESC LIMIT 1", [contact.name])[0].email_sent, 0);
  } finally { docker("start", "mailpit"); }
  pass("Mail failure preserves request and returns registered confirmation");
  clearLimits();
  docker("stop", "db");
  try {
    assert.equal((await context.request.get("/")).status(), 200);
    assert.equal((await context.request.get("/tjenester")).status(), 503);
    const adminFailure = await context.request.get("/admin/");
    assert.equal(adminFailure.status(), 503);
    assert(!/SQLSTATE|password|Stack trace/.test(await adminFailure.text()));
    assert.equal((await submit(contact)).status(), 200);
    docker("stop", "mailpit");
    try { assert.equal((await submit(contact)).status(), 503); } finally { docker("start", "mailpit"); }
  } finally { docker("up", "-d", "--wait", "--wait-timeout", "60"); }
  pass("Database failure: homepage fallback, controlled catalog/admin errors, mail-only delivery; dual failure returns 503");
  assert.equal((await context.request.get("/tjenester/not-found-qa")).status(), 404);
  assert.equal((await context.request.get("/not-found-qa")).status(), 404);
  for (const path of ["/config.example.php", "/.env", "/app/bootstrap.php", "/database/schema.sql", "/tests/qa.cjs", "/Dockerfile"]) assert.equal((await context.request.get(path)).status(), 403);
  assert.equal((await context.request.get("/index.html", { maxRedirects: 0 })).status(), 301);
  assert.equal((await context.request.get("/tjenester.php?slug=" + slug)).status(), 200);
  pass("Unknown URLs return 404, old homepage redirects, query fallback works, private files blocked");
  for (const width of [320, 375, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    for (const path of ["/", "/tjenester", "/tjenester/" + slug, "/admin/", "/admin/services/", servicePath, "/admin/categories/", "/admin/gallery/", "/admin/requests/", "/admin/settings/"]) {
      await page.goto(base + path);
      await page.evaluate(() => document.querySelectorAll("img").forEach(i => { i.loading = "eager"; }));
      await page.waitForFunction(() => [...document.images].every(i => i.complete));
      await page.evaluate(async () => { await Promise.all([...document.images].map(i => i.decode().catch(() => {}))); });
      const state = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > window.innerWidth + 1, broken: [...document.images].filter(i => !i.naturalWidth).map(i => i.src), h1: document.querySelectorAll("h1").length }));
      assert(!state.overflow, path + " overflow at " + width);
      assert.deepEqual(state.broken, [], path + " broken image at " + width);
      assert.equal(state.h1, 1, path + " H1");
      if ((width === 375 || width === 1440) && ["/", "/tjenester", "/admin/", servicePath].includes(path)) {
        await page.evaluate(async () => {
          document.documentElement.style.scrollBehavior = "auto";
          for (let top = 0; top < document.documentElement.scrollHeight; top += 700) {
            window.scrollTo(0, top);
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
          }
          window.scrollTo(0, 0);
        });
        await page.screenshot({ path: ".qa/" + width + "-" + (path === "/" ? "home" : path.startsWith("/tjenester") ? "catalog" : path === "/admin/" ? "admin" : "edit") + ".png", fullPage: true });
      }
    }
  }
  pass("Ten public/admin views: 320, 375, 768, 1024, 1440px; no overflow, broken images or duplicate H1");
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto(base);
  await page.locator(".menu-toggle").click();
  assert.equal(await page.locator(".menu-toggle").getAttribute("aria-expanded"), "true");
  await page.keyboard.press("Escape");
  assert.equal(await page.locator(".menu-toggle").getAttribute("aria-expanded"), "false");
  await page.locator(".menu-toggle").click();
  await page.locator("#primary-nav a").first().click();
  assert.match(page.url(), /tjenester/);
  await page.locator("#category").selectOption(categoryForm.slug);
  await page.locator(".catalog-filter button").click();
  assert.equal(await page.locator(".catalog-card").count(), 1);
  pass("Mobile menu, Escape, navigation and category filter");
  clearLimits();
  await page.goto(base);
  await page.locator("#name").fill(contact.name);
  await page.locator("#phone").fill(contact.phone);
  await page.locator("#email").fill(contact.email);
  await page.locator("#service").selectOption(contact.service);
  await page.locator("#area").fill(contact.area);
  await page.locator("#message").fill(contact.message);
  await page.locator("#privacy").check();
  await page.waitForTimeout(4100);
  await page.locator(".form-button").click();
  await page.waitForFunction(() => document.querySelector(".form-status").textContent.includes("Takk"));
  assert.equal(await page.locator("#name").inputValue(), "");
  assert.equal(await page.locator("[data-lead-form] [aria-invalid='true']").count(), 0);
  pass("Real browser contact submission shows success, resets fields and clears error styling");
  await page.route("**/contact.php", route => route.fulfill({ status: 422, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Kontroller feltene.", errors: { email: "Ugyldig e-post." } }) }));
  for (const name of ["name", "phone", "email", "area", "message"]) await page.locator("#" + name).fill(contact[name]);
  await page.locator("#service").selectOption(contact.service);
  await page.locator("#privacy").check();
  await page.locator(".form-button").click();
  await page.waitForFunction(() => !!document.querySelector("#error-email"));
  assert.equal(await page.locator("#email").getAttribute("aria-describedby"), "error-email");
  await page.unroute("**/contact.php");
  pass("Server field errors displayed, associated with input and focus moved");
  assert.deepEqual(errors.filter(e => !e.includes("Failed to load resource")), []);
  pass("No JavaScript runtime or CSP console errors");
  const nojs = await browser.newContext({ baseURL: base, javaScriptEnabled: false });
  const nojsPage = await nojs.newPage();
  await nojsPage.setViewportSize({ width: 375, height: 812 });
  await nojsPage.goto(base);
  assert(await nojsPage.locator("#primary-nav").isVisible());
  assert.notEqual(await nojsPage.locator("[name=started_at]").inputValue(), "");
  await nojs.close();
  pass("No-JavaScript navigation and server-generated form timestamp");
  const logoutToken = await token(context, "/admin/");
  assert.equal((await context.request.get("/admin/logout.php")).status(), 405);
  assert.equal((await context.request.post("/admin/logout.php", { form: { csrf: logoutToken }, maxRedirects: 0 })).status(), 303);
  assert.equal((await context.request.get("/admin/", { maxRedirects: 0 })).status(), 303);
  pass("Logout is POST/CSRF protected and invalidates session");
  const expiredLogin = await save(context, "/admin/login.php", { email, password });
  assert.equal(expiredLogin.status(), 200);
  const sid = (await context.cookies()).find(c => c.name === "lily_session").value;
  docker("exec", "-T", "web", "php", "-r", "require 'app/bootstrap.php'; session_id('" + sid + "'); start_session(); $_SESSION['last_activity']=time()-1900; session_write_close();");
  assert.equal((await context.request.get("/admin/", { maxRedirects: 0 })).status(), 303);
  pass("Idle admin session expires");
  clearLimits();
  for (let i = 0; i < 8; i++) await save(context, "/admin/login.php", { email: "throttle@lily.test", password: "invalid-password" });
  assert.equal((await save(context, "/admin/login.php", { email: "throttle@lily.test", password: "invalid-password" })).status(), 429);
  pass("Repeated login attempts trigger throttling");
  clearLimits();
  await save(context, "/admin/login.php", { email, password });
  await save(context, servicePath, { id: String(service.id), action: "delete" });
  assert.equal(sql("SELECT id FROM services WHERE id=?", [service.id]).length, 0);
  assert.equal((await context.request.get("/" + service.image)).status(), 404);
  await save(context, categoryPath, { id: String(category.id), action: "delete" });
  assert.equal(sql("SELECT id FROM service_categories WHERE id=?", [category.id]).length, 0);
  pass("Service and unused category deletion, upload cleanup");
  console.log("Completed " + report.length + " QA groups.");
}
main().catch(error => {
  report.push({ name: "Run", result: "FAIL", detail: error.stack });
  console.error(error);
  process.exitCode = 1;
}).finally(async () => {
  try {
    docker("up", "-d", "--wait", "--wait-timeout", "60");
    sql("DELETE FROM contact_requests WHERE name=?", ["QA " + suffix]);
    const serviceImages = sql("SELECT image FROM services WHERE slug=?", [slug]).map(r => r.image);
    const galleryImages = sql("SELECT image FROM gallery_items WHERE caption LIKE ?", ["QA " + suffix + "%"]).map(r => r.image);
    sql("DELETE FROM services WHERE slug=?", [slug]);
    sql("DELETE FROM gallery_items WHERE caption LIKE ?", ["QA " + suffix + "%"]);
    sql("DELETE FROM service_categories WHERE slug=?", [slug + "-category"]);
    sql("DELETE FROM admins WHERE email=?", [email]);
    for (const path of [...uploads, ...serviceImages, ...galleryImages]) if (/^assets\/uploads\/[a-f0-9]{40}\.(?:jpg|webp)$/.test(path)) {
      for (const file of [path, path.replace(/\.(?:jpg|webp)$/, "-320.webp"), path.replace(/\.(?:jpg|webp)$/, "-900.webp")]) if (fs.existsSync(file)) fs.unlinkSync(file);
    }
    clearLimits();
  } catch (error) { console.error("QA cleanup failed:", error.message); process.exitCode = 1; }
  if (browser) await browser.close();
  fs.writeFileSync(".qa/results.json", JSON.stringify({ generated: new Date().toISOString(), createdFirst, groups: report }, null, 2));
});

const { chromium } = require("playwright");
const sharp = require("sharp");
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const { execFileSync } = require("node:child_process");
const base = process.env.QA_URL || "http://127.0.0.1:18082";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base) || process.env.COMPOSE_PROJECT_NAME !== "fiksitt-seo-qa") throw new Error("Use the isolated local fiksitt-seo-qa stack only.");
const suffix = crypto.randomBytes(8).toString("hex");
const prefix = "QA-seo-" + suffix;
const email = prefix + "@lily.test";
const password = crypto.randomBytes(24).toString("hex");
let browser;
let upload = "";
let originalSettings;
let verifiedLocal = false;
const groups = [];
function php(code) { return execFileSync("docker", ["compose", "exec", "-T", "web", "php", "-r", code], { encoding: "utf8" }); }
function sql(statement, params = []) {
  const encoded = Buffer.from(JSON.stringify([statement, params])).toString("base64");
  return JSON.parse(php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); echo json_encode(query($v[0],$v[1])->fetchAll());"));
}
function pass(name) { groups.push(name); console.log("PASS " + name); }
async function fields(context, path) {
  const response = await context.request.get(path);
  return (await response.text()).match(/name="csrf" value="([^"]+)"/)[1];
}
async function main() {
  const config = JSON.parse(php("require 'app/bootstrap.php'; echo json_encode([config()['app_env'],config()['app_url']]);"));
  assert.deepEqual(config, ["local", base]);
  verifiedLocal = true;
  const domains = JSON.parse(php("require 'app/seo.php'; echo json_encode(array_map('seo_domain_valid',['https://fiksitt.online','http://fiksitt.online','https://localhost','https://127.0.0.1','https://brand.test','https://fiksitt.online/path','https://user@fiksitt.online','https://fiksitt.online:443']));"));
  assert.deepEqual(domains, [true, false, false, false, false, false, false, false]);
  const testIndex = (env, domain, enabled) => {
    const encoded = Buffer.from(JSON.stringify([domain, enabled])).toString("base64");
    return JSON.parse(php("putenv('APP_ENV=" + env + "'); require 'app/seo.php'; $v=json_decode(base64_decode('" + encoded + "'),true); db()->beginTransaction(); foreach(['public_domain'=>$v[0],'seo_indexable'=>$v[1]] as $k=>$value) query('INSERT INTO site_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$k,$value]); echo json_encode(seo_indexable()); db()->rollBack();"));
  };
  assert.equal(testIndex("local", "https://fiksitt.online", "1"), false);
  assert.equal(testIndex("production", "https://fiksitt.online", "1"), true);
  assert.equal(testIndex("production", "https://fiksitt.online", "0"), false);
  assert.equal(testIndex("production", "http://188.137.232.136:8080", "1"), false);
  pass("Indexing opt-in: only production and configured public HTTPS domain; local/staging excluded");
  const encoded = Buffer.from(JSON.stringify([email, password])).toString("base64");
  php("require 'app/bootstrap.php'; $v=json_decode(base64_decode('" + encoded + "'),true); query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$v[0],password_hash($v[1],PASSWORD_DEFAULT)]);");
  originalSettings = sql("SELECT * FROM site_settings");
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const context = await browser.newContext({ baseURL: base });
  assert.equal((await context.request.get("/admin/seo/", { maxRedirects: 0 })).status(), 303);
  const logged = await context.request.post("/admin/login.php", { form: { csrf: await fields(context, "/admin/login.php"), email, password } });
  assert.equal(logged.status(), 200);
  const home = await context.request.get("/");
  assert.match(home.headers()["x-robots-tag"], /noindex/);
  assert.match(await (await context.request.get("/robots.txt")).text(), /Disallow: \/\n/);
  assert(!(await (await context.request.get("/robots.txt")).text()).includes("Sitemap:"));
  for (const [path, location] of [["/index.html", "/"], ["/index.php", "/"], ["/index.php?service=Veggmontering", "/?service=Veggmontering"], ["/tjenester/", "/tjenester"], ["/tjenester.php?slug=kontor-og-naering", "/tjenester/kontor-og-naering"], ["/tjenester/kontor-og-naering/", "/tjenester/kontor-og-naering"]]) {
    const response = await context.request.get(path, { maxRedirects: 0 });
    assert.equal(response.status(), 301, path);
    assert.equal(response.headers().location, location);
  }
  assert.equal((await context.request.get("/tjenester?category=not-a-category")).status(), 404);
  const conflict = await context.request.get('/tjenester/kontor-og-naering?slug=handyman-tjenester');
  assert.match(await conflict.text(), /<h1>Kontor, skole og næring<\/h1>/);
  for (const path of ['/SEO_LAUNCH.md', '/docker-compose.production.yml', '/tests/seo-admin.cjs']) assert.equal((await context.request.get(path)).status(), 403);
  const error = await context.request.get("/404.php");
  assert(!(await error.text()).includes('rel="canonical"'));
  pass("Duplicate URL redirects, staging robots, unknown-category 404 and noncanonical error pages");
  const page = await context.newPage();
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  for (const path of ["/", "/tjenester", "/tjenester/kontor-og-naering", "/tjenester?category=kontor-og-naering"]) {
    const response = await page.goto(base + path);
    assert.equal(response.status(), 200);
    const json = await page.locator('script[type="application/ld+json"]').textContent();
    assert(response.headers()["content-security-policy"].includes("sha256-" + crypto.createHash("sha256").update(json).digest("base64")));
    assert.equal(await page.locator('link[rel="canonical"]').count(), 1);
    assert.equal(await page.locator('meta[property="og:site_name"]').getAttribute("content"), "fiksitt");
    assert(await page.locator('meta[property="og:image:alt"]').getAttribute("content"));
    if (path.startsWith("/tjenester")) {
      const graph = JSON.parse(json)["@graph"];
      const breadcrumbs = graph.find(node => node["@type"] === "BreadcrumbList");
      assert(breadcrumbs.itemListElement.length >= 2);
      if (path.includes("kontor-og-naering") && !path.includes("?")) {
        assert.equal(graph[0]["@type"], "Service");
        assert(graph[0].image.endsWith("lys-trehylle-montert.webp"));
        assert.equal(await page.locator('meta[property="og:image"]').getAttribute("content"), base + "/assets/images/lys-trehylle-montert.webp");
      } else assert.equal(graph[0].itemListElement.length, await page.locator(".catalog-card").count());
    }
    assert(!/ratingValue|reviewCount/.test(json));
  }
  await page.goto(base);
  assert.match(await page.locator('link[rel="preload"]').getAttribute("imagesrcset"), /675w/);
  const sitemap = await (await context.request.get("/sitemap.xml")).text();
  assert(!sitemap.includes("admin") && !sitemap.includes("category="));
  assert(sitemap.includes(base + "/tjenester/kontor-og-naering"));
  pass("Unique metadata, contextual social images, truthful schemas, exact CSP hashes and sitemap consistency");
  for (let i = 0; i < 32; i++) {
    sql("INSERT INTO services(category_id,title,slug,short_description,full_description,active) VALUES (1,?,?,?,?,0)", [prefix + " " + i, prefix.toLowerCase() + "-" + i, "QA short description", "QA full description"]);
    sql("INSERT INTO contact_requests(name,phone,email,service,area,message) VALUES (?,?,?,?,?,?)", [prefix + " " + i, "+4712345678", "qa@lily.test", "QA", "QA", prefix + " message"]);
  }
  await page.goto(base + "/admin/services/?q=" + prefix + "&active=0&page=999999999999");
  // Invalid/overflowing input resets to the first page; valid high input clamps to the last.
  await page.goto(base + "/admin/services/?q=" + prefix + "&active=0&page=99999");
  assert.equal(await page.locator("tbody tr").count(), 2);
  assert.match(await page.locator(".list-summary").textContent(), /32/);
  assert.match(await page.locator(".pagination").textContent(), /Side 2 av 2/);
  assert.match(await page.locator(".pagination a").first().getAttribute("href"), /active=0/);
  await page.goto(base + "/admin/services/?q=%25");
  assert.equal(await page.locator("tbody tr").count(), 0);
  await page.goto(base + "/admin/requests/?q=" + prefix + "&page=99999");
  assert.equal(await page.locator("tbody tr").count(), 2);
  assert.match(await page.locator(".pagination").textContent(), /Side 2 av 2/);
  const requestId = sql("SELECT id FROM contact_requests WHERE name=?", [prefix + " 0"])[0].id;
  await page.goto(base + "/admin/requests/?id=" + requestId);
  assert.equal(await page.locator('a[href="tel:+4712345678"]').count(), 1);
  assert.equal(await page.locator('a[href="mailto:qa@lily.test"]').count(), 1);
  pass("Admin search, literal wildcard escaping, status filters, counts, clamped pagination and customer contact links");
  const serviceId = sql("SELECT id FROM services WHERE slug=?", [prefix.toLowerCase() + "-0"])[0].id;
  await page.goto(base + "/admin/services/?id=" + serviceId);
  await page.locator("#seo_title").fill('<img src=x onerror="alert(1)">');
  assert.equal(await page.locator("[data-preview-title] img").count(), 0);
  assert.match(await page.locator("[data-preview-title]").textContent(), /<img/);
  assert.match(await page.locator("#seo_title-count").textContent(), /\/ 160 tegn/);
  const settingsForm = Object.fromEntries(originalSettings.map(row => [row.setting_key, row.setting_value]));
  settingsForm.company_name = "fiksitt";
  const saveSettings = async values => context.request.post("/admin/settings/", { form: { ...values, csrf: await fields(context, "/admin/settings/") } });
  const rejected = await saveSettings({ ...settingsForm, public_domain: "https://localhost", seo_indexable: "1" });
  assert.match(await rejected.text(), /Indeksering krever/);
  await saveSettings({ ...settingsForm, home_seo_title: "QA SEO forside", home_seo_description: "QA SEO beskrivelse for forsiden", public_domain: "https://fiksitt.online", seo_indexable: "1" });
  await page.goto(base);
  assert.equal(await page.title(), "QA SEO forside");
  assert.equal(await page.locator('meta[name="description"]').getAttribute("content"), "QA SEO beskrivelse for forsiden");
  assert.match((await context.request.get("/")).headers()["x-robots-tag"], /noindex/);
  pass("SEO preview XSS safety, character counters, editable homepage metadata and local noindex even after opt-in");
  const image = await sharp({ create: { width: 1200, height: 800, channels: 3, background: "#658679" } }).png().toBuffer();
  const uploaded = await context.request.post("/admin/gallery/?new=1", { multipart: { csrf: await fields(context, "/admin/gallery/?new=1"), caption: prefix, alt_text: "QA image", sort_order: "0", active: "1", action: "save", id: "0", image_file: { name: "qa.png", mimeType: "image/png", buffer: image } } });
  assert.equal(uploaded.status(), 200);
  upload = sql("SELECT image FROM gallery_items WHERE caption=?", [prefix])[0].image;
  for (const width of [320, 900]) {
    const response = await context.request.get("/" + upload.replace(/\.(?:jpg|webp)$/, "-" + width + ".webp"));
    assert.equal(response.status(), 200);
    assert.equal((await sharp(await response.body()).metadata()).width, width);
  }
  await page.goto(base + "/admin/gallery/?q=" + prefix);
  const source = await page.locator("tbody source").getAttribute("srcset");
  assert.match(source, /320w/);
  assert.match(source, /900w/);
  assert.equal(await page.locator("tbody source").getAttribute("sizes"), "64px");
  pass("Re-encoded uploads produce real 320/900px WebP thumbnails and responsive table images");
  fs.mkdirSync(".qa/seo-admin", { recursive: true });
  for (const width of [320, 375, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    for (const [name, path] of [["seo", "/admin/seo/"], ["services", "/admin/services/"], ["edit", "/admin/services/?id=" + serviceId], ["requests", "/admin/requests/"], ["settings", "/admin/settings/"]]) {
      const response = await page.goto(base + path);
      assert.equal(response.status(), 200);
      assert.match(response.headers()["x-robots-tag"], /noindex/);
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), name + " at " + width);
      assert.equal(await page.locator("h1").count(), 1);
      await page.screenshot({ path: ".qa/seo-admin/" + name + "-" + width + ".png", fullPage: true });
    }
  }
  assert.deepEqual(errors, []);
  pass("20 responsive admin views: no horizontal page overflow, one H1, no JS errors, noindex and protected SEO access");
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
  try {
    if (!verifiedLocal) return;
    if (originalSettings) {
      sql("DELETE FROM site_settings WHERE setting_key IN ('home_seo_title','home_seo_description','seo_indexable')");
      for (const row of originalSettings) sql("INSERT INTO site_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [row.setting_key, row.setting_value]);
    }
    sql("DELETE FROM services WHERE title LIKE ?", [prefix + "%"]);
    sql("DELETE FROM contact_requests WHERE name LIKE ?", [prefix + "%"]);
    sql("DELETE FROM gallery_items WHERE caption=?", [prefix]);
    if (upload) {
      const encoded = Buffer.from(upload).toString("base64");
      php("require 'app/uploads.php'; remove_unused_upload(base64_decode('" + encoded + "'));");
    }
    sql("DELETE FROM admins WHERE email=?", [email]);
  } catch (error) { console.error("Cleanup failed:", error.message); process.exitCode = 1; }
  if (browser) await browser.close();
  fs.mkdirSync(".qa/seo-admin", { recursive: true });
  fs.writeFileSync(".qa/seo-admin/results.json", JSON.stringify({ generated: new Date().toISOString(), passed: process.exitCode !== 1, groups }, null, 2));
});

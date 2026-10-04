const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const base = process.env.QA_URL || "http://127.0.0.1:18081";
if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(base)) throw new Error("Local performance sampling only.");
let browser;
async function main() {
  browser = await chromium.launch({ channel: "msedge", headless: true });
  const samples = [];
  for (const width of [375, 1440]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    await context.addInitScript(() => {
      window.localVitals = { lcp: null, cls: 0 };
      new PerformanceObserver(list => {
        for (const entry of list.getEntries()) window.localVitals.lcp = entry.startTime;
      }).observe({ type: "largest-contentful-paint", buffered: true });
      new PerformanceObserver(list => {
        for (const entry of list.getEntries()) if (!entry.hadRecentInput) window.localVitals.cls += entry.value;
      }).observe({ type: "layout-shift", buffered: true });
    });
    for (const path of ["/", "/tjenester", "/tjenester/kontor-og-naering"]) {
      const page = await context.newPage();
      const response = await page.goto(base + path, { waitUntil: "networkidle" });
      assert.equal(response.status(), 200);
      await page.waitForTimeout(500);
      const sample = await page.evaluate(() => {
        const navigation = performance.getEntriesByType("navigation")[0];
        const resources = performance.getEntriesByType("resource");
        return { ttfbMs: Math.round(navigation.responseStart - navigation.requestStart), lcpMs: window.localVitals.lcp === null ? null : Math.round(window.localVitals.lcp), cls: Number(window.localVitals.cls.toFixed(4)), transferredBytes: navigation.transferSize + resources.reduce((total, entry) => total + entry.transferSize, 0), loadedImages: document.querySelectorAll("img").length, missingImages: [...document.images].filter(img => img.complete && !img.naturalWidth).length };
      });
      assert.equal(sample.missingImages, 0);
      samples.push({ path, width, ...sample });
      await page.close();
    }
    await context.close();
  }
  fs.mkdirSync(".qa/performance", { recursive: true });
  fs.writeFileSync(".qa/performance/results.json", JSON.stringify({ generated: new Date().toISOString(), methodology: "Local headless Edge, first viewport, no throttling, one sample per route/width; caches can be warm. CLS is observed cumulative shift, not a full field session-window metric. Not Lighthouse or field Core Web Vitals; INP not measured.", samples }, null, 2));
  console.table(samples);
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => { if (browser) await browser.close(); });

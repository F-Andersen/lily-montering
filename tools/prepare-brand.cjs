const fs = require('node:fs/promises');
const path = require('node:path');
const sharp = require('sharp');

async function main() {
  const [wordmark, mark] = process.argv.slice(2);
  if (!wordmark || !mark) throw new Error('Usage: node tools/prepare-brand.cjs wordmark.png hammer.png');
  const output = path.resolve(__dirname, '../assets');
  const logo = await sharp(wordmark).trim().toBuffer();
  const hammer = await sharp(mark).trim().toBuffer();
  const metadata = await sharp(logo).metadata();
  if (!metadata.hasAlpha || !(await sharp(hammer).metadata()).hasAlpha) {
    throw new Error('Both source assets must have transparency.');
  }
  await sharp(logo).resize({ width: 720, withoutEnlargement: true }).webp({ quality: 90, alphaQuality: 100 }).toFile(path.join(output, 'fiksitt-wordmark-v2.webp'));
  await sharp(hammer).resize(128, 128, { fit: 'contain', background: '#00000000' }).webp({ lossless: true }).toFile(path.join(output, 'fiksitt-hammer-v2.webp'));
  for (const size of [32, 180, 192, 512]) {
    const padding = Math.round(size * .13);
    await sharp(hammer).resize(size - padding * 2, size - padding * 2, { fit: 'contain', background: '#00000000' })
      .flatten({ background: '#fff3b9' })
      .extend({ top: padding, bottom: padding, left: padding, right: padding, background: '#fff3b9' })
      .png({ compressionLevel: 9 }).toFile(path.join(output, `fiksitt-icon-v2-${size}.png`));
  }
  const stats = await Promise.all(['fiksitt-wordmark-v2.webp', 'fiksitt-hammer-v2.webp', ...[32,180,192,512].map(s => `fiksitt-icon-v2-${s}.png`)].map(async name => ({ name, bytes: (await fs.stat(path.join(output, name))).size, ...(await sharp(path.join(output, name)).metadata()) })));
  console.log(JSON.stringify(stats.map(({name,bytes,width,height,hasAlpha}) => ({name,bytes,width,height,hasAlpha})), null, 2));
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });

// Run with Sharp available in NODE_PATH. Originals are never modified.
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
sharp.cache(false);
const root = path.resolve(__dirname, '..');
const images = path.join(root, 'assets/images');
const selected = [
  ['IMG_7539.JPG', 'hjornegarderobe', 'Hjørnegarderobe', 'Hvit hjørnegarderobe med åpne hyller og skuffer'],
  ['IMG_7626.JPG', 'hvite-skyvedorer', 'Hvite skyvedører', 'Hvite skyvedører foran garderobeinnredning'],
  ['IMG_7839.JPG', 'tregarderobe-med-skuffer', 'Tregarderobe med skuffer', 'Garderobe i lyst tre med hyller, skuffer og hengestang'],
  ['IMG_7972.JPG', 'speilgarderobe-soverom', 'Speilgarderobe på soverom', 'Garderobe med store speilskyvedører på soverom'],
  ['IMG_8533.JPG', 'skyvedorer-morkt-tre', 'Skyvedører i mørkt tre', 'Garderobe med skyvedører i mørkt tre og sort ramme'],
  ['IMG_9009.JPG', 'hvitt-garderoberom', 'Hvitt garderoberom', 'Hvitt garderoberom med åpne hyller og skuffer på begge sider'],
];
function originals(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? originals(path.join(dir, entry.name)) : [path.join(dir, entry.name)]);
}
(async () => {
  const files = originals(path.join(root, 'foto'));
  const jobs = fs.readdirSync(images).filter(name => /\.jpg$/i.test(name)).map(name => [path.join(images, name), name.slice(0, -4)]);
  for (const [file, name] of selected) {
    const source = files.find(item => path.basename(item) === file);
    if (!source) throw Error(`Missing original ${file}`);
    jobs.push([source, name]);
  }
  const report = [];
  for (const [source, name] of jobs) {
    const sizes = [];
    for (const [suffix, width, height, quality] of [['', 1800, 1800, 78], ['-900', 900, 900, 76], ['-320', 320, 320, 74]]) {
      const target = path.join(images, name + suffix + '.webp');
      const temporary = target + '.building.webp';
      await sharp(source).rotate().resize({ width, height, fit: 'inside', withoutEnlargement: true }).webp({ quality, effort: 6 }).toFile(temporary);
      fs.renameSync(temporary, target);
      const metadata = await sharp(target).metadata();
      if (metadata.format !== 'webp' || fs.statSync(target).size > 5 * 1024 * 1024) throw Error('Invalid output');
      sizes.push({ file: path.basename(target), bytes: fs.statSync(target).size, width: metadata.width, height: metadata.height });
    }
    report.push({ source: path.basename(source), sourceBytes: fs.statSync(source).size, outputs: sizes });
  }
  fs.writeFileSync(path.join(root, '.qa/gallery-optimization.json'), JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(images, 'gallery-selection.json'), JSON.stringify(selected.map(([, name, caption, alt_text], index) => ({ image: `assets/images/${name}.webp`, caption, alt_text, sort_order: 9 + index })), null, 2) + '\n');
  console.log(`Optimized ${jobs.length} image families; six curated originals added.`);
})().catch(error => { console.error(error); process.exitCode = 1; });

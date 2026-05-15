const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const SOURCE = 'assets/images/logo.png';
const BACKGROUND_COLOR = '#0f6b45';

if (!fs.existsSync(SOURCE)) {
  console.error(`Error: Source file ${SOURCE} not found.`);
  process.exit(1);
}

const assets = [
  { name: 'icon.png', width: 1024, height: 1024, alpha: false, background: BACKGROUND_COLOR, fit: 'contain' },
  { name: 'favicon.png', width: 48, height: 48, alpha: false, background: BACKGROUND_COLOR, fit: 'contain' },
  { name: 'splash-icon.png', width: 1024, height: 1024, alpha: true, fit: 'contain' },
  { name: 'android-icon-foreground.png', width: 432, height: 432, alpha: true, fit: 'contain', padding: 80 },
  { name: 'android-icon-background.png', width: 432, height: 432, alpha: false, background: BACKGROUND_COLOR },
  { name: 'android-icon-monochrome.png', width: 432, height: 432, alpha: true, monochrome: true, fit: 'contain', padding: 80 },
  { name: 'apple-touch-icon.png', width: 180, height: 180, alpha: false, background: BACKGROUND_COLOR, fit: 'contain' },
  { name: 'pwa/icon-192.png', width: 192, height: 192, alpha: false, background: BACKGROUND_COLOR, fit: 'contain' },
  { name: 'pwa/icon-512.png', width: 512, height: 512, alpha: false, background: BACKGROUND_COLOR, fit: 'contain' },
  { name: 'pwa/maskable-192.png', width: 192, height: 192, alpha: false, background: BACKGROUND_COLOR, fit: 'contain', padding: 40 },
  { name: 'pwa/maskable-512.png', width: 512, height: 512, alpha: false, background: BACKGROUND_COLOR, fit: 'contain', padding: 100 },
];

async function generate() {
  const results = [];
  
  for (const asset of assets) {
    const outputPath = path.join('assets/images', asset.name);
    const dir = path.dirname(outputPath);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

    try {
      let pipeline;
      
      if (asset.name === 'android-icon-background.png') {
        pipeline = sharp({
          create: {
            width: asset.width,
            height: asset.height,
            channels: 3,
            background: asset.background
          }
        });
      } else {
        pipeline = sharp(SOURCE);
        
        if (asset.monochrome) {
            pipeline = pipeline
                .toColourspace('b-w')
                .threshold(128)
                .negate(false)
                .linear(0, 255) // Make it white
                .ensureAlpha();
            // Note: Simplistic monochrome conversion to white silhouette
        }

        let resizeOptions = {
          width: asset.width - (asset.padding || 0),
          height: asset.height - (asset.padding || 0),
          fit: asset.fit || 'contain',
          background: { r: 0, g: 0, b: 0, alpha: 0 }
        };

        pipeline = pipeline.resize(resizeOptions);

        if (asset.padding) {
           pipeline = pipeline.extend({
               top: Math.floor(asset.padding/2),
               bottom: Math.ceil(asset.padding/2),
               left: Math.floor(asset.padding/2),
               right: Math.ceil(asset.padding/2),
               background: { r: 0, g: 0, b: 0, alpha: 0 }
           });
        }

        if (!asset.alpha) {
          pipeline = pipeline.flatten({ background: asset.background || BACKGROUND_COLOR });
        }
      }

      await pipeline.toFile(outputPath);
      results.push({ name: asset.name, status: 'Success', dims: `${asset.width}x${asset.height}` });
    } catch (err) {
      results.push({ name: asset.name, status: `Error: ${err.message}`, dims: `${asset.width}x${asset.height}` });
    }
  }

  console.table(results);
  
  const readmeContent = `# Branding Assets\n\nGenerated files:\n\n` + 
    assets.map(a => `- **${a.name}**: ${a.width}x${a.height} size.`).join('\n');
  fs.writeFileSync('assets/images/README-branding.md', readmeContent);
}

generate();

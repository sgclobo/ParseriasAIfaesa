# PWA Pre-Deployment Checklist

Complete this checklist before deploying to production on Hostinger.

## 🔧 Files & Configuration

- [ ] `public/manifest.json` — exists and contains all app metadata
- [ ] `public/service-worker.js` — exists and handles caching
- [ ] `public/offline.html` — offline fallback page created
- [ ] `public/.htaccess` — server configuration with proper MIME types
- [ ] `web/index.php` — updated with PWA meta tags and SW registration

## 📱 Assets & Icons

- [ ] `/assets/images/favicon.png` — 16x16 minimum, 32x32 ideal
- [ ] `/assets/images/apple-touch-icon.png` — 180x180 PNG
- [ ] `/assets/images/pwa/icon-192.png` — 192x192 PWA icon
- [ ] `/assets/images/pwa/icon-512.png` — 512x512 PWA icon
- [ ] `/assets/images/pwa/maskable-192.png` — maskable variant (192x192)
- [ ] `/assets/images/pwa/maskable-512.png` — maskable variant (512x512)

## 🌐 Server Setup (Hostinger)

### HTTPS

- [ ] SSL certificate installed (usually free with Hostinger)
- [ ] Redirect HTTP → HTTPS enabled
- [ ] Uncomment HTTPS rewrite in `.htaccess` if not auto-enabled

### MIME Types

- [ ] `.json` → `application/manifest+json` (configured in .htaccess)
- [ ] `.js` → `application/javascript` (configured in .htaccess)

### Headers

- [ ] Service-Worker-Allowed: / (configured in .htaccess)
- [ ] X-Content-Type-Options: nosniff (configured in .htaccess)
- [ ] Cache-Control headers set (configured in .htaccess)

### File Upload

- [ ] All files from `public/` uploaded to web root
- [ ] `.htaccess` placed in web root (not in `public/`)
- [ ] Permissions: 644 for files, 755 for directories

## 🧪 Local Testing

Before uploading:

```bash
# Start dev server
npm run web

# Or test with local HTTP server
python3 -m http.server 8000
# Then open http://localhost:8000
```

- [ ] Service Worker registers (DevTools → Application → Service Workers)
- [ ] Manifest loads at `/manifest.json`
- [ ] All icons load without 404 errors
- [ ] Offline page shows when connection is disabled

## ✅ Production Validation

After deployment:

### Via Browser DevTools

1. Open your deployed URL: `https://yourdomain.com`
2. DevTools → Application tab
   - [ ] Manifest section shows all icons and metadata
   - [ ] Service Workers tab shows active registration
   - [ ] Storage → Cache Storage shows cached assets
   - [ ] No console errors related to PWA

### Via Chrome Lighthouse

```bash
# Install lighthouse
npm install -g lighthouse

# Run audit on your domain
lighthouse https://yourdomain.com
```

- [ ] PWA score ≥ 90
- [ ] Install criteria met
- [ ] All icons detected

### Via curl/Network

```bash
# Test manifest
curl -I https://yourdomain.com/manifest.json
# Should return: Content-Type: application/manifest+json

# Test service worker
curl -I https://yourdomain.com/service-worker.js
# Should return: Content-Type: application/javascript

# Test offline page
curl -I https://yourdomain.com/offline.html
# Should return: 200 OK
```

## 📱 Mobile Testing

### Android (Chrome/Brave)

- [ ] Open https://yourdomain.com
- [ ] Menu → "Install app" appears
- [ ] Click to install
- [ ] App launches from home screen

### iOS (Safari)

- [ ] Open https://yourdomain.com in Safari
- [ ] Share → "Add to Home Screen"
- [ ] App launches from home screen
- [ ] Offline functionality works

### Desktop (Chrome/Edge)

- [ ] Install icon appears in address bar
- [ ] Click to install
- [ ] App launches in standalone window

## 🔄 Performance & Caching

- [ ] Service Worker caches static assets
- [ ] API requests fetch from network first
- [ ] Offline pages show gracefully
- [ ] Page loads in < 3 seconds (Lighthouse)
- [ ] No 404 errors in console
- [ ] No CORS errors for cross-origin requests

## 🔐 Security

- [ ] HTTPS enabled (no mixed content)
- [ ] Service Worker uses HTTPS only
- [ ] No sensitive data logged to console
- [ ] Manifest icons use absolute paths
- [ ] API authentication tokens not cached

## 📊 Monitoring

After deployment, monitor:

- [ ] Check Hostinger logs for 404 errors
- [ ] Monitor browser console for JS errors
- [ ] Track installation rates (if analytics available)
- [ ] Test on multiple devices monthly
- [ ] Validate after each update deployment

## 🚀 Deploy Script (Automated)

Create `deploy.sh` for easier deployments:

```bash
#!/bin/bash

# Build web version
npm run web

# Copy PWA files to dist
cp public/manifest.json dist/
cp public/service-worker.js dist/
cp public/offline.html dist/
cp public/.htaccess dist/

# Upload to Hostinger via FTP/SCP (example)
# scp -r dist/* user@domain.com:/home/your-domain/public_html/

echo "✅ PWA files ready for deployment!"
```

---

## 📝 Notes

- Keep `CACHE_NAME` in sync with app version for updates
- Update manifest.json if app metadata changes
- Monitor cache size (keep under 50MB for mobile)
- Clear old caches when deploying major updates

**When ready to deploy, check off this list and upload via Hostinger hPanel!**

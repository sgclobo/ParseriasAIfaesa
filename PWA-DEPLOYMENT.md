# PWA Deployment Guide

## 📱 Progressive Web App Ready!

Your ParseriasAIfaesa application is now configured for PWA deployment. This guide walks through building and deploying your PWA.

---

## ✅ PWA Features Enabled

- **Offline Support**: Service worker caches assets and API responses
- **Installable**: Add to home screen on mobile/desktop
- **Native-like Experience**: Standalone display mode
- **Smart Caching**: Network-first for APIs, cache-first for static assets
- **Background Sync**: Foundation for future sync features

---

## 🛠️ Files Created

```
public/
├── manifest.json          # PWA manifest (app metadata)
├── service-worker.js      # Caching & offline logic
└── offline.html           # Offline fallback page

web/index.php              # Updated with PWA meta tags + SW registration
```

---

## 📦 Building for Production

### Option 1: Expo Web Build

```bash
# Build static Expo web output (recommended for this setup)
expo export --platform web

# This creates a `dist/` folder with static assets
# Output ready for deployment to any web server
```

### Option 2: Manual Web Build

```bash
# Start dev server for testing
npm run web

# Build optimized bundle
npx expo export --platform web
```

---

## 🚀 Deployment Steps

### 1. **Prepare Assets**

Ensure these files are in the web root:

```
/manifest.json                          # From public/
/service-worker.js                      # From public/
/offline.html                           # From public/
/assets/images/favicon.png              # Already exists
/assets/images/apple-touch-icon.png     # Already exists
/assets/images/pwa/icon-192.png         # Already exists
/assets/images/pwa/icon-512.png         # Already exists
```

### 2. **Server Configuration**

Add to your Hostinger hPanel or web server:

**HTTP Headers** (via .htaccess or server config):

```apache
# .htaccess (for Apache)
<IfModule mod_headers.c>
  Header set Service-Worker-Allowed "/"
  Header set Access-Control-Allow-Origin "*"
  Header set X-Content-Type-Options "nosniff"
  Header set Cache-Control "public, max-age=31536000" ".jpg|.css|.js|.png|.woff2"
</IfModule>

<IfModule mod_mime.c>
  AddType application/manifest+json .json
  AddType application/javascript .js
</IfModule>

# Cache static assets
<FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|woff|woff2)$">
  Header set Cache-Control "max-age=31536000, public"
</FilesMatch>

# Don't cache HTML/service worker
<FilesMatch "\.(html|sw\.js|php)$">
  Header set Cache-Control "no-cache, no-store, must-revalidate"
</FilesMatch>
```

### 3. **Deploy to Hostinger**

Using Hostinger hPanel:

1. **Upload files** via File Manager:
   - Upload `public/` contents to web root
   - Upload built `dist/` from `expo export` (if applicable)

2. **Set MIME types**:
   - `.json` → `application/manifest+json`
   - `.js` → `application/javascript`

3. **Test deployment**:
   ```bash
   curl -I https://yourdomain.com/manifest.json
   curl -I https://yourdomain.com/service-worker.js
   ```

---

## ✔️ PWA Validation Checklist

Test on browser DevTools → Application tab:

- [ ] **Manifest**: Loads at `/manifest.json` with all icons and metadata
- [ ] **Service Worker**: Registered and active (DevTools → Application → Service Workers)
- [ ] **Icons**: 192px and 512px variants load successfully
- [ ] **Install Prompt**: "Add to Home Screen" option available (mobile/Chrome)
- [ ] **Offline**: Navigate while offline → service worker shows offline page
- [ ] **Caching**: Static assets cached (DevTools → Storage → Cache Storage)

### Validation Tools

```bash
# Check PWA score locally
npm install -g lighthouse
lighthouse https://yourdomain.com --view

# Or use Google PageSpeed Insights
# https://pagespeed.web.dev/
```

---

## 📱 Testing Installation

### On Mobile (Android/iOS):

1. **Chrome/Brave** (Android):
   - Open app → Menu → "Install app" or "Add to Home Screen"

2. **Safari** (iOS):
   - Tap Share → "Add to Home Screen"

3. **Firefox**:
   - Tap Menu → "Install" (if PWA-capable)

### On Desktop:

1. **Chrome/Brave**:
   - Click install icon in address bar
   - Or: Menu → "Install ParseriasAIfaesa"

2. **Edge**:
   - Click install icon in address bar

---

## 🔄 Updates & Cache Invalidation

When deploying updates:

1. **Increment cache version** in `public/service-worker.js`:

```javascript
const CACHE_NAME = "parseiras-v2"; // v1 → v2
```

2. **Clear old caches**:
   - The service worker automatically deletes v1 cache
   - Users will get fresh content on next visit

3. **Force refresh** (for debugging):
   - DevTools → Application → Service Workers → Unregister
   - Or: Settings → Storage → Clear Site Data

---

## 🔐 Security Considerations

- ✅ All resources served over HTTPS
- ✅ Service worker intercepts only GET requests
- ✅ API requests always go to network first (for fresh data)
- ✅ Offline responses marked with 503 status
- ✅ No sensitive data cached in service worker

---

## 📊 Performance Tips

1. **Optimize images** in `assets/images/pwa/`:

   ```bash
   npm run generate-branding  # Uses sharp to optimize
   ```

2. **Lazy load non-critical assets**:
   - Service worker caches on demand

3. **Monitor cache size**:
   - Keep under 50MB for mobile compatibility

4. **Implement update checks**:
   - Listen for service worker `controllerchange` event
   - Prompt user to refresh when update available

---

## 🚨 Troubleshooting

| Issue                         | Solution                                             |
| ----------------------------- | ---------------------------------------------------- |
| Service Worker won't register | Check MIME type (should be `application/javascript`) |
| Install prompt not showing    | Requires HTTPS + manifest.json + icons               |
| Offline page not showing      | Verify `offline.html` path in service worker         |
| Cache not updating            | Increment `CACHE_NAME` and redeploy                  |
| Icons broken on installed app | Verify absolute paths (`/assets/images/...`)         |

---

## 📚 Next Steps

1. ✅ Add `public/` files to deployment
2. ✅ Configure server headers
3. ✅ Build & deploy via Hostinger hPanel
4. ✅ Test on multiple devices
5. ✅ Monitor via Lighthouse
6. 🔄 Plan cache invalidation strategy for future updates

---

## 📖 References

- [MDN PWA Docs](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Web.dev PWA Checklist](https://web.dev/pwa-checklist/)
- [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)

---

**Ready to deploy!** Your app is PWA-compliant and ready for production.

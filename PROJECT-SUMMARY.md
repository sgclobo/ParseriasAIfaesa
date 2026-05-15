# ParseriasAIfaesa — Project Summary

**Platform:** Multi-institution article portal for AIFAESA and partner institutions  
**Stack:** PHP 7.4+ · SQLite3 · Expo React Native (SDK ~54) · React Native Web  
**Live URL:** https://parserias.aifaesa.org  
**Repository:** https://github.com/sgclobo/ParseriasAIfaesa

---

## What Was Built

### 1. Backend (PHP + SQLite)

#### Database Schema (`web/db.php`)
- `users` — account records with role, institution, and password hash
- `articles` — institutional publications with full content
- `institutions` — reference list of partner organisations
- `article_institutions` — many-to-many junction: one article can target multiple institutions
- `comments` — article commentary (backend ready, mobile UI pending)
- `mobile_sessions` — token store for Expo mobile app authentication (48h expiry)

#### Seeded Data
- **6 institutions:** AIFAESA, INDIMO, MAE, PAM, DNRKPK-MIC, DNRKPK-MCI
- **3 users:** Administrator, sgclobo (admin), drsergio (user)
- **2 sample articles** (~400 words each) with multi-institution targeting:
  - *Training Certifications…* → AIFAESA + INDIMO
  - *Compulsory Product Registration…* → AIFAESA + DNRKPK-MCI

#### Web API Endpoints
| File | Purpose |
|------|---------|
| `web/api/login.php` | Session-based login (username or email) |
| `web/api/logout.php` | Session destroy |
| `web/api/articles.php` | Article CRUD + institution-filtered listing |
| `web/api/users.php` | User CRUD with institution selection |
| `web/api/institutions.php` | Institution CRUD with referential integrity checks |
| `web/api/comments.php` | Comment CRUD (web only) |
| `web/api/profile.php` | Own profile update |
| `web/api/mobile.php` | Token-based mobile API: login, bootstrap, full CRUD |

#### Authentication
- **Web:** PHP session + 48h cookie for remembered logins
- **Mobile:** Bearer token issued on login, stored in `mobile_sessions`, expires after 48h
- **Login:** Accepts **username** (the `name` field) or email interchangeably
- **Cookie prefill:** Returns stored username on revisit

---

### 2. Web Dashboard (`web/app.php` + `web/index.php`)

- Login page with username/password form and 48h session auto-fill
- Admin dashboard with four tabs:
  - **Institutions** — add/edit/delete with linkage validation (prevents deletion if linked)
  - **Users** — CRUD with role selection (admin/user) and institution dropdown
  - **Articles** — CRUD with multi-institution checkbox targeting, two content templates
  - **Comments** — view and moderate user comments
- Article form includes two pre-filled templates:
  - *Certification Training* template
  - *Product Registration* template
- AJAX-based forms with client-side validation

---

### 3. Mobile App (`app/index.tsx`)

Built with Expo React Native (~1,400 lines), targeting iOS, Android, and Web.

#### Authentication & Session
- Safe AsyncStorage wrappers with fallback to `localStorage` (web) and in-memory store (React Native)
- Username always persisted locally; password cached only if less than 48 hours since last login
- Session restore on app launch: attempts token reuse, falls back to re-login

#### Role-Based Dashboard
| Tab | Admin | User |
|-----|-------|------|
| Artigos | ✅ | ✅ |
| Users Management | ✅ | — |
| Institutions | ✅ | — |
| Profile | ✅ | ✅ |

#### Features
- Article list with institution audience labels and content snippets
- Full article detail panel on tap (complete content, not just summary)
- Profile editing (name, position, email, WhatsApp, password change) with remote + local fallback
- Users CRUD: add/edit/delete users, assign role and institution
- Institutions CRUD: add/edit/delete with linkage validation
- Articles CRUD: add/edit/delete with multi-institution targeting

---

### 4. Progressive Web App (PWA)

Added full PWA configuration for installation on mobile and desktop:

| File | Purpose |
|------|---------|
| `public/manifest.json` | App name, icons, display mode, theme colours |
| `public/service-worker.js` | Offline caching: network-first for API, cache-first for static assets |
| `public/offline.html` | Friendly offline fallback page (Portuguese) |
| `public/.htaccess` | Apache server headers, MIME types, cache control, gzip |

- PWA meta tags and service worker registration added to `web/index.php`
- Apple touch icon and favicon configured
- Icons available in 192px and 512px, both standard and maskable variants
- See `PWA-DEPLOYMENT.md` and `PWA-CHECKLIST.md` for deployment and validation steps

---

### 5. Branding & Assets

- Custom icon generation via `generate-branding.js` (uses Sharp)
- Assets: `icon.png`, `favicon.png`, `apple-touch-icon.png`, `logo.png`, `splash-icon.png`
- PWA icons: `pwa/icon-192.png`, `pwa/icon-512.png`, `pwa/maskable-192.png`, `pwa/maskable-512.png`
- Android adaptive icon set (foreground, background, monochrome)
- Design system: navy/blue primary (#0059bb), green tertiary (#006b24), Inter + Manrope typefaces

---

## Deployment

- **Hosting:** Hostinger hPanel (manual deployment)
- **CI/CD:** Git push to `origin/main` (https://github.com/sgclobo/ParseriasAIfaesa) triggers deployment
- **Database:** SQLite file stored in `web/data/` — initialised automatically on first request via `web/db.php`
- **Uploads:** User photos stored in `web/uploads/`

---

## Plans for Enhancement

### High Priority

| # | Enhancement | Rationale |
|---|------------|-----------|
| 1 | **Comments module (mobile)** | Backend is complete; mobile UI not yet implemented. Users can read articles but cannot comment from the app. |
| 2 | **Password reset / forgot password** | Currently users must contact an admin to reset passwords. A self-service email link would reduce friction. |
| 3 | **Push notifications** | Alert users when new articles are published to their institution. Requires Expo Notifications + a notification queue on the backend. |
| 4 | **Search and filter for articles** | As content grows, users need to search by keyword or filter by institution, date, or category. |

### Medium Priority

| # | Enhancement | Rationale |
|---|------------|-----------|
| 5 | **Article categories / tags** | Classify articles by topic (e.g. Certification, Regulation, Events) for easier discovery. |
| 6 | **Profile photo upload (mobile)** | Web dashboard supports photo uploads; mobile app currently shows initials-based avatars. |
| 7 | **Read receipts / article views** | Track which users have read each article, useful for compliance or training confirmation. |
| 8 | **Dark mode (web dashboard)** | The mobile app supports automatic dark mode; the web dashboard is light-only. |
| 9 | **Pagination for articles list** | As article volume grows, infinite scroll or pagination will improve performance. |
| 10 | **Audit log** | Record admin actions (create/edit/delete) with timestamps for accountability. |

### Lower Priority / Future

| # | Enhancement | Rationale |
|---|------------|-----------|
| 11 | **Multi-language support** | UI is in Portuguese; adding Tetum or English would broaden reach. |
| 12 | **Export to PDF** | Allow admins to export articles or user lists as PDFs for offline distribution. |
| 13 | **SQLite → MySQL migration** | SQLite works well at current scale; MySQL would support concurrent write load if user numbers grow significantly. |
| 14 | **Two-factor authentication** | For admin accounts, an optional TOTP or email-code second factor. |
| 15 | **Analytics dashboard** | Article view counts, active user trends, institution engagement metrics. |
| 16 | **Scheduled article publishing** | Admins write articles in advance and set a publish date/time. |
| 17 | **Automated PWA update notifications** | Prompt users to reload when a new version of the service worker is available. |
| 18 | **Session management UI** | Allow users to see and revoke active mobile sessions from their profile page. |

---

## Known Limitations

- SQLite does not support concurrent writes; suitable for current scale but not for high-concurrency load
- Service worker cache must be manually versioned on each deployment to force client updates
- Article content is plain text only; no rich text / Markdown rendering yet
- No email delivery configured (password reset, notifications require SMTP setup)
- Mobile app requires Expo Go or a native build for full testing; web preview available via `npm run web`

---

*Last updated: May 2026*

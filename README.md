# ViewPay v2.0.0
**Video & Website View Monetization Platform**
Developed by **Jcode** | Author: **Obrempong**

---

## What's New in v2.0.0
- **Ad Watching System** — Users earn by watching ads (35/day split: morning 10, afternoon 10, night 15)
- **Email Verification** — Optional verified email gate before earning
- **Moderator Role** — Admin can promote users to moderator (can approve campaigns/payments)
- **Audit Logs** — Full trail of every user, moderator and admin action
- **PWA** — Install as native app + offline support + splash screen
- **Advanced SEO** — Open Graph, Twitter cards, meta tags, canonical URLs
- **IP & Device Limits** — Admin controls max accounts per IP and device
- **Improved UI** — Inter font, smoother animations, better mobile UX

---

## Installation on cPanel

### Step 1 — Create MySQL Database
1. cPanel → **MySQL Databases**
2. Create database: `user_viewpay`
3. Create user + strong password
4. Add user → **All Privileges**

### Step 2 — Upload Files
1. cPanel → File Manager → `public_html`
2. Upload `install.php` to `public_html`
3. Upload zip → extract → move all contents of `app/` into `public_html`
   - `.htaccess`, `index.php`, `config.php` go directly in `public_html`

### Step 3 — Run Installer
```
https://yourdomain.com/install.php
```

### Step 4 — DELETE install.php
**cPanel → File Manager → `install.php` → Delete immediately**

### Step 5 — Configure Admin
```
https://yourdomain.com/admin
```

| Priority | Setting | Path |
|----------|---------|------|
| 1 | Currency & exchange rate | Settings → Currency |
| 2 | MoMo number & Paystack keys | Settings → Payments |
| 3 | Ad watching network & rewards | Settings → Ad Watching |
| 4 | Email/SMTP for verification | Settings → Email |
| 5 | Logo, splash screen, PWA | Settings → Appearance & PWA |

---

## Ad Watching System

Users can watch ads to earn extra rewards — 35 ads per day total:

| Period | Hours | Daily Limit | Default Reward |
|--------|-------|-------------|----------------|
| Morning | 6:00 AM – 12:00 PM | 10 ads | $0.002/ad |
| Afternoon | 12:00 PM – 6:00 PM | 10 ads | $0.002/ad |
| Night | 6:00 PM – 3:00 AM | 15 ads | $0.003/ad |

Configure in: **Admin → Settings → Ad Watching**

Supported networks: Google AdSense, In-House HTML, AdMob, Unity Ads, IronSource

---

## Roles & Permissions

| Feature | User | Moderator | Admin |
|---------|------|-----------|-------|
| Earn money | ✅ | ✅ | ✅ |
| Watch ads | ✅ | ✅ | ✅ |
| View admin panel | ✗ | ✅ | ✅ |
| Approve campaigns | ✗ | ✅ | ✅ |
| Confirm payments | ✗ | ✅ | ✅ |
| Process withdrawals | ✗ | ✅ | ✅ |
| Manage packages | ✗ | ✗ | ✅ |
| Site settings | ✗ | ✗ | ✅ |
| Audit logs | ✗ | ✗ | ✅ |

Set roles at: **Admin → Users → [User] → Set Role**

---

## File Structure

```
install.php          ← DELETE after install
README.md
app/
  .htaccess          ← Security + routing
  config.php         ← Core: DB, auth, helpers, ad slots
  index.php          ← Router (all requests flow here)
  manifest.json      ← PWA manifest
  sw.js              ← Service worker (offline support)
  includes/
    layout.php       ← Shared nav, footer, flash, PWA splash
  uploads/           ← Logo, favicon, user files
  assets/            ← PWA icons
  pages/
    home.php         ← Landing page
    login.php        ← Rate-limited + audit logged
    register.php     ← IP/device limit check
    verify-email.php ← Email verification handler
    dashboard.php    ← Earner stats + wallet
    tasks.php        ← Browse tasks
    task-view.php    ← Watch video + claim reward
    watch-ads.php    ← Ad watching with time slots
    packages.php     ← Buy views (all payment methods)
    creator.php      ← Manage campaigns
    wallet.php       ← Earnings + withdrawals
    profile.php      ← Account settings
    admin/
      dashboard.php      ← Stats + Chart.js
      users.php          ← User management + roles
      user-detail.php    ← User profile + history
      tasks.php          ← Campaign management
      task-detail.php    ← Campaign detail + views
      deposits.php       ← Confirm payments
      payments.php       ← Process withdrawals
      packages.php       ← Package CRUD
      ads.php            ← Advertisement management
      pages.php          ← CMS page list
      page-edit.php      ← Visual + HTML editor
      settings.php       ← All settings (10 tabs)
      audit-logs.php     ← Full audit trail
```

---

## Security Features

- Session hardening (HttpOnly, SameSite=Lax, 30-min ID rotation)
- CSRF tokens on all POST forms
- Prepared statements (zero SQL injection)
- `VP_BOOT` guard on all pages
- `require_once` for admin partials (prevents HTTP 500)
- Rate limiting: login (5/2min), registration (3/5min), orders (5/10min)
- IP + device fingerprint account limits (admin configurable)
- Fraud detection on ad watches and task claims
- Audit log on every significant action
- Security headers: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
- Upload validation (extension + getimagesize check)

---

## Requirements

| | Minimum |
|-|---------|
| PHP | 8.1+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Apache | mod_rewrite enabled |
| Extensions | PDO, pdo_mysql, openssl, mbstring, json, curl |
| SSL | Strongly recommended |

---

© 2026 ViewPay v2.0.0
Developed by **Jcode** | Author: **Obrempong**

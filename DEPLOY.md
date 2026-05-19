# Deploy on Hostinger (PHP + MySQL + Brevo)

Stack: **GitHub** (code + CI) · **Hostinger** (website + MySQL) · **Brevo** (OTP email)

> `Dockerfile` and `render.yaml` are **not used** on Hostinger. You can ignore or delete them.

---

## 1. Hostinger setup

1. Log in to [Hostinger hPanel](https://hpanel.hostinger.com).
2. **Websites** → your site → note your domain (e.g. `https://yourdomain.com`).
3. **Advanced** → **PHP Configuration** → set PHP **8.1** or **8.2**.
4. Enable **SSL** (Hostinger → SSL → install free certificate).

---

## 2. Create MySQL database

1. hPanel → **Databases** → **MySQL Databases**.
2. Create a database (e.g. `u123456789_docu`).
3. Create a user and password; assign user to the database (**All privileges**).
4. Note these values (Hostinger often uses `localhost` as host):

   | Setting | Example |
   |---------|---------|
   | Host | `localhost` |
   | Database name | `u123456789_docu` |
   | Username | `u123456789_user` |
   | Password | *(your password)* |

5. Open **phpMyAdmin** → select your database → **Import** → choose your `.sql` schema file.

---

## 3. Upload project files

### Option A — File Manager (easiest)

1. hPanel → **Files** → **File Manager** → `public_html`.
2. Upload a **ZIP** of your project (without `.env`, `vendor/`, `*.sql`, `.git`).
3. Extract the ZIP in `public_html`.

### Option B — FTP (FileZilla)

1. hPanel → **FTP Accounts** → create or view FTP credentials.
2. Connect to `public_html` and upload all project files.

### Folder layout

**Site at domain root** (recommended):

```
public_html/
├── index.php
├── api/
├── assets/
├── config/
├── storage/
└── .env          ← create on server only
```

**Site in subfolder** (e.g. `yourdomain.com/docu_request`):

```
public_html/docu_request/
├── index.php
└── ...
```

Set `APP_BASE=/docu_request` in server `.env`.

---

## 4. Create `.env` on the server

In File Manager, create `public_html/.env` (same folder as `index.php`).

**Domain root example:**

```env
BASE_URL=https://yourdomain.com
APP_BASE=

DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_docu
DB_USER=u123456789_user
DB_PASS=your_database_password

BREVO_API_KEY=your_brevo_api_key
MAIL_FROM=your-verified@email.com
MAIL_FROM_NAME=Document Request
```

**Subfolder example** (`/docu_request`):

```env
BASE_URL=https://yourdomain.com
APP_BASE=/docu_request
```

Copy from `.env.example` locally — **never commit `.env` to GitHub.**

---

## 5. Folder permissions

In File Manager, set permissions:

| Path | Permission |
|------|------------|
| `storage/sessions/` | **755** or **775** (must be writable) |
| `storage/logs/` | **755** or **775** |

---

## 6. Test

1. Open `https://yourdomain.com/` (or `https://yourdomain.com/docu_request/`).
2. Register a student account → check OTP email (Brevo).
3. Log in and submit a test request.

---

## 7. GitHub (CI only)

Push code to GitHub; `.github/workflows/php.yml` runs PHP checks on push.

Hostinger deploy is **manual upload** (File Manager/FTP) or optional FTP automation later — not required for capstone.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| **Database connection failed** | Use `localhost`, correct DB name/user from hPanel (not `root` unless Hostinger says so). |
| **API / login 404** | Wrong `APP_BASE` — empty at root, `/docu_request` in subfolder. Hard refresh (Ctrl+F5). |
| **Blank page / 500** | Check PHP version 8.1+; view **Error log** in hPanel. |
| **OTP email not sent** | Brevo API key + verified `MAIL_FROM` sender. |
| **Sessions / login drops** | `storage/sessions/` writable (755/775). |

---

## Local vs Hostinger

| | XAMPP (local) | Hostinger |
|--|---------------|-----------|
| `BASE_URL` | `http://localhost` | `https://yourdomain.com` |
| `APP_BASE` | `/docu_request` | `` (root) or `/docu_request` |
| `DB_HOST` | `localhost` | `localhost` (usually) |

`assets/js/app-config.js` auto-detects `/docu_request` on localhost; on Hostinger at domain root it uses `/api` automatically.

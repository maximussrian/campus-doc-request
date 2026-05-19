# Docu Request

Document request management system for students, with teller, registrar, and developer dashboards.

## Features

- **Students** – Submit document requests, track status
- **Tellers** – Process requests, assign departments
- **Registrar** – Manage staff, permissions, daily limits, export reports
- **Developers** – Full admin: users, audit logs, maintenance mode
- **2FA OTP** – Password reset via Brevo email

## Tech Stack

- PHP 7.4+ / XAMPP (Apache + MySQL)
- Composer
- Brevo (transactional email)

## Requirements

- XAMPP (or Apache + MySQL)
- PHP 7.4+
- Composer
- Brevo API key

## Setup

1. **Database**
   - Create database `docu_request`
   - **SQL schema/migrations are intentionally not included in this GitHub repo** (to avoid tampering).
   - Ask the project owner for the SQL files, then import/run them in phpMyAdmin.

2. **Secrets (.env)**
   - Copy `.env.example` to `.env`
   - Edit `.env` and add your Brevo API keys, email, DB credentials, etc.
   - **Never commit `.env`** — it's in `.gitignore`; only `.env.example` (placeholders) is in the repo

3. **Dependencies**
   ```bash
   composer install
   ```

4. **Start**
   - Start XAMPP (Apache + MySQL)
   - Open `http://localhost/docu_request/`

## Password Reset (2FA OTP)

Forgot password → Enter email → OTP sent via Brevo → Enter OTP on verify page → Reset password

## Project Structure

```
docu_request/
├── api/              # PHP endpoints
├── assets/           # CSS, JS, images
├── config/           # config.php, database.php
├── database/         # (ignored) schema and migrations
├── storage/sessions/ # PHP sessions
└── *.php             # Entry pages (index, dashboards, login)
```

# 🛡️ ReclaimX — Cloud Campus Belongings Portal

ReclaimX is a full-stack campus lost-and-found web portal designed to help students and administrators report, track, and reunite misplaced belongings in real time.

---

## ✨ Key Features

- **OTP Email Verification (PHPMailer + SMTP):** Secures account registration and password resets using time-limited 6-digit one-time passcodes.
- **Campus Domain & Admin Passcode Access Control:** Restricts standard student accounts to institutional email addresses (`@college.edu`), while allowing authorized administrators to unlock personal email registration via an encrypted Admin Passcode check.
- **Role-Based Access Control (RBAC):**
  - **Guests:** Browse live lost/found items and view public details.
  - **Registered Users:** File lost/found reports with photo uploads, track personal submissions, and mark own items as claimed.
  - **System Admins:** Promote/demote users in the Admin Directory, moderate all posts, and resolve matched pairs.
- **Smart Attribute Cross-Matcher:** Automatically calculates similarity scores across **Item Type (40%)**, **Color (30%)**, and **Location (30%)** to surface high-confidence lost-and-found matches.
- **Interactive Media & Reporting:** Drag-and-drop image uploads (`JPG`, `PNG`, `WEBP`, `GIF`) with a full-screen zoom/rotate lightbox viewer and responsive mobile navigation.

---

## 🛠️ Tech Stack

- **Frontend:** HTML5, Vanilla JavaScript (ES6+ Fetch API), Tailwind CSS, Lucide Icons
- **Backend:** PHP 8+ (RESTful JSON API with session-based authentication)
- **Database:** MySQL / MariaDB (`mysqli` prepared statements)
- **Email Delivery:** PHPMailer (SMTP over TLS)

---

## 📂 Project Structure

```text
reclaimx/
├── index.html          # Single-page responsive frontend UI & client logic
├── api.php             # REST API endpoints (Auth, OTP, RBAC, Items CRUD)
├── schema.sql          # MySQL database schema & initial seed data
├── PHPMailer/          # PHPMailer library files
├── uploads/            # User-uploaded item images (ignored in Git)
└── .gitignore          # Excludes uploads/ and local environment files
```

---

## 🚀 Local Setup (XAMPP)

1. **Clone the repository** into your XAMPP `htdocs` folder:
   ```bash
   cd C:\xampp\htdocs
   git clone <https://github.com/pandarabinarayan313-debug/ReclaimX> reclaimx
   ```
2. **Start Services:** Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. **Import the Database:**
   - Open `http://localhost/phpmyadmin/`
   - Import `schema.sql` to create `reclaimx_db` along with the `users`, `items`, and `verifications` tables.
4. **Configure `api.php`:**
   Update the database and SMTP variables at the top of `api.php`:
   ```php
   $host   = "localhost";
   $user   = "root";
   $pass   = "";
   $dbname = "reclaimx_db";

   $smtpUser      = "your-sender-email@gmail.com";
   $smtpPass      = "your-16-char-app-password";
   $allowedDomain = "@college.edu";
   $adminPasscode = "your-secret-admin-code";
   ```
5. **Launch the App:** Visit `http://localhost/reclaimx/` in your browser.

---

## 🌐 Production Deployment (InfinityFree)

1. Create a MySQL database in your InfinityFree control panel and import `schema.sql` via phpMyAdmin.
2. Update `$host`, `$user`, `$pass`, and `$dbname` in `api.php` with your InfinityFree MySQL credentials, along with your Gmail App Password.
3. Upload `index.html`, `api.php`, and the `PHPMailer/` folder into your site's `htdocs/` directory.

---

## 🔒 Security & Git Hygiene

- All SQL queries use parameterized `mysqli` prepared statements to prevent SQL injection.
- Passwords are hashed using `PASSWORD_BCRYPT` and verified with `password_verify()`.
- **Important:** Always replace `$smtpPass`, `$adminPasscode`, and production database passwords with placeholders before committing changes to GitHub.

---

## 👥 Credits

Developed by **Team InfernoX**
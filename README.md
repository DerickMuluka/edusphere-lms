# EduSphere LMS

A modern, role-based Learning Management System built from scratch with **PHP**, **MySQL**, **HTML5**, **CSS3**, and **vanilla JavaScript**. Designed for real institutions — supports students, instructors, and administrators with a full approval workflow, assignments, online exams, past papers, course notes, and progress tracking.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=flat&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Live Demo

> Hosted on InfinityFree: **https://edusphere-lms.infinityfreeapp.com**

**Demo accounts** (all passwords: `password`)

| Role | Email | Status |
|---|---|---|
| Administrator | `admin@demo.com` | Active |
| Instructor | `instructor@demo.com` | Active |
| Student | `student@demo.com` | Active |
| Pending applicant | `alex@demo.com` | Pending (must be approved) |

---

## Features

### For students
- Self-registration with admin approval gate
- Enroll in courses across departments
- Watch lesson videos, download notes and attachments
- Submit assignments with file attachments (respects due dates / late policy)
- Take timed online exams with auto-submit
- Download past papers by year and exam type
- Track progress per course and overall
- Personal gradebook

### For instructors
- Create, edit, publish, and delete courses
- Upload lesson content, video links, and course notes
- Create assignments with deadlines and late submission rules
- Create exams with multiple-choice questions, timers, and windows
- Grade submissions with feedback
- Upload past papers
- View roster of enrolled students per course

### For administrators
- **Approval workflow** — new accounts are `pending` until reviewed
- Approve, reject, suspend, or reactivate users
- Bulk approve/reject with reason
- Full audit trail of every status change
- Manage departments (courses are grouped under departments)
- Override any instructor action

### General
- Fully responsive (mobile, tablet, desktop)
- Modern color palette (warm cream, rust, amber, sage)
- No external icon libraries — inline SVG only
- CSRF protection, prepared statements, hashed passwords
- File upload validation (extension + size)
- Session-hardened for HTTPS proxies

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 (procedural, no framework) |
| Database | MySQL / MariaDB |
| Frontend | HTML5, CSS3 (custom), vanilla JavaScript (ES6) |
| Server | Apache (XAMPP locally, InfinityFree in production) |
| Fonts | Google Fonts (Inter, Source Serif 4) |

---

## Project Structure

```
virtual_classroom/
├── .htaccess
├── .gitignore
├── index.php
├── README.md
├── assets/
│   ├── css/
│   │   ├── base.css
│   │   ├── auth.css
│   │   ├── layout.css
│   │   ├── components.css
│   │   ├── dashboard.css
│   │   ├── courses.css
│   │   ├── assignments.css
│   │   ├── exams.css
│   │   └── admin.css
│   ├── js/
│   │   ├── core.js
│   │   ├── auth.js
│   │   ├── courses.js
│   │   ├── exams.js
│   │   └── admin.js
│   └── uploads/
│       ├── avatars/
│       ├── courses/
│       ├── notes/
│       ├── assignments/
│       └── papers/
├── includes/
│   ├── config.php
│   ├── db.php
│   ├── helpers.php
│   ├── auth.php
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── pages/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── courses.php
│   ├── course-view.php
│   ├── course-manage.php
│   ├── assignments.php
│   ├── assignment-view.php
│   ├── assignment-manage.php
│   ├── exams.php
│   ├── exam-take.php
│   ├── exam-manage.php
│   ├── past-papers.php
│   ├── gradebook.php
│   ├── students.php
│   ├── progress.php
│   ├── profile.php
│   ├── notifications.php
│   ├── approvals.php
│   ├── admin.php
│   └── departments.php
├── api/
│   ├── enroll.php
│   └── lesson-complete.php
└── database/
    └── schema.sql
```

---

## Installation (Local — XAMPP)

1. **Install XAMPP** and start **Apache** + **MySQL**.
2. **Clone** this repo into `C:\xampp\htdocs\virtual_classroom`.
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `virtual_classroom`.
5. Import `database/schema.sql`.
6. Edit `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'virtual_classroom');
   define('SITE_URL', 'http://localhost/virtual_classroom');
   ```
7. Create folders under `assets/uploads/`: `avatars/`, `courses/`, `notes/`, `assignments/`, `papers/`.
8. Visit `http://localhost/virtual_classroom`.

---

## Deployment (InfinityFree)

1. Create a hosting account and get a free subdomain.
2. Create a MySQL database — note host, user, password, db name.
3. Import `database/schema.sql` through phpMyAdmin.
4. Upload all files to `htdocs/`.
5. Update `includes/config.php` with the new database credentials and `SITE_URL`.
6. Set `assets/uploads/*` permissions to **755**.
7. Sign in as `admin@demo.com` / `password`.

Detailed deployment walkthrough is available in the project wiki.

---

## Security Notes

- All passwords hashed with `password_hash()` (bcrypt)
- All SQL uses prepared statements with `bind_param`
- All output escaped with `htmlspecialchars`
- CSRF tokens on every state-changing form
- Uploaded files validated by extension + size
- `.htaccess` blocks direct access to `includes/` and `database/`
- PHP execution disabled inside `assets/uploads/`

---

## Roadmap

- [ ] Email verification on signup
- [ ] Live chat between instructor and students
- [ ] CSV export for gradebook
- [ ] Rich text editor for lesson content
- [ ] Course completion certificates (PDF)

---

## License

Released under the **MIT License**. See `LICENSE` for details.

---

## Author

Built as a full-featured capstone LMS project. Contributions and feedback welcome.
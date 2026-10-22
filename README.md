# Farmogram — Smart Agriculture Platform

Farmogram is a PHP/MySQL web application concept for crop management, farmer assistance, and connecting farmers with buyers through an online crop marketplace.

## Features
- Farmer and buyer areas with separate dashboards
- Crop listing, harvest status, and marketplace workflows
- Cart, orders, enquiries, and reviews
- Farming tasks, notifications, and analytics
- Optional integrations for weather, Gemini assistant, payments, and push notifications

> Feature availability depends on the local configuration and API credentials you provide.

## Technology
- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript, Bootstrap (where used)
- External APIs for optional integrations

## Run locally
1. Install XAMPP, WAMP, or another PHP + MySQL/MariaDB environment.
2. Copy the project folder into the local web root (for XAMPP, usually `htdocs/`).
3. Start Apache and MySQL/MariaDB.
4. Create a database named `farmogram` in phpMyAdmin.
5. Import `database/farmogram .sql` into the database. The included SQL keeps table definitions but omits seeded `INSERT` data for privacy.
6. Review `config/db.php` and set local database credentials if needed.
7. Configure optional API integrations using environment variables listed in `.env.example`. Do not paste real keys into committed files.
8. Open the project through your local server, for example `http://localhost/farmogram/`.

## Important notes
- The ZIP prepared for GitHub excludes `uploads/` contents and the APK build. Runtime upload folders are represented by `.gitkeep` files.
- Add only sample images that you have permission to publish. Uploaded claim/profile images and other user-submitted files should remain private.
- API integrations may not work until you provide valid keys locally.
- Before public release, test authentication, authorization, uploads, payment flows, and error handling in a local environment.
- This is a student portfolio project, not a security-audited production system.

## Repository contents
The repository includes application source files, the database schema, and project documentation/diagrams where present.

## Author
Prajwal Gouda

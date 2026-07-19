# MeetVerse

MeetVerse is a PHP and Oracle-based meeting management portal for corporate teams. It supports employee registration and login, role-based dashboards, meeting scheduling, attendance tracking, reports, and Oracle-backed business rules for time conflict prevention.

## Features

- Role-based access for `ADMIN` and `MEMBER` users.
- Meeting creation, editing, cancellation, and dashboard views.
- Attendance and participant tracking.
- Reports for meetings, employees, departments, and activity.
- Oracle PL/SQL procedures, triggers, sequences, and views for core business logic.
- PDO-compatible Oracle access through `oracle_pdo_compat.php`.

## Requirements

- Windows with XAMPP or another PHP 8.2-compatible stack.
- Oracle Database / Oracle XE.
- Oracle Instant Client and OCI8 enabled for PHP.
- A web server configured to serve the project folder.

## Database Setup

1. Open Oracle SQL Developer or another Oracle client.
2. Run the SQL script in `query` to create the tables, sequences, functions, procedures, triggers, and the `meeting_overview` view.
3. Confirm the `employees`, `meetings`, `attendance`, and `activity_log` objects exist.
4. Make sure the Oracle user in `db.php` has permission to create and use the required objects.

The current database connection in `db.php` points to:

- Host: `localhost`
- Port: `1521`
- Service: `XE`
- Username: `admin`
- Password: `2022`

Update those values if your local Oracle setup is different.

## Application Setup

1. Copy the project folder into your web root, for example `C:\xampp\htdocs\MeetVerse_web`.
2. Enable OCI8 for PHP and make sure the Oracle Instant Client DLLs are available in the PHP and Apache paths.
3. Import the SQL script in `query` before opening the app.
4. Start Apache and open the app in your browser.

## Usage

- Open `index.php` for the landing page.
- Register through `register.html` and log in through `login.html`.
- The first two registered users are automatically assigned the `ADMIN` role; later users become `MEMBER` accounts.
- Admins are routed to `admin_dashboard.php` and members to `member_dashboard.php`.

## Main Pages

- `index.php` - landing page and feature overview.
- `login.html` / `login.php` - user sign-in.
- `register.html` / `register.php` - account creation.
- `admin_dashboard.php` - admin overview.
- `member_dashboard.php` - member overview.
- `create_meeting.php` - meeting creation.
- `edit_meeting.php` - meeting editing.
- `meeting.php` - meeting listing and management.
- `employees.php` - employee directory.
- `reports.php` - reporting dashboard.
- `admin_settings.php` / `member_settings.php` - account and system settings.

## Notes

- The app uses Oracle PL/SQL logic for meeting status refreshes and overlap checks.
- `db.php` relies on `oracle_pdo_compat.php` so existing PDO-style calls continue to work.
- If login or database connection fails, verify Oracle Instant Client loading order and the connection credentials in `db.php`.

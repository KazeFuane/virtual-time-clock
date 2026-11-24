# ClockWise

ClockWise (repository: virtual-time-clock) is a web-based time clock for employees. Staff clock in, take their break and clock out from the browser, and every action is recorded so they are paid for exactly the time they worked. Managers and administrators get a live attendance register, CSV exports and a report that flags clocking mistakes before payroll.

Built with **PHP 8**, **MariaDB/MySQL**, **Tailwind CSS** and **vanilla JavaScript**. No framework and no Composer dependencies.

---

## Features

### For employees
- **Virtual time clock**: a live digital clock and dial with one-click *Start workday*, *Start break*, *End break* and *End workday*.
- **Live counters** for worked time and break time, kept in sync with the server clock.
- **Break policy**: one 45-minute break per day by default (configurable).
- **Shift schedule and lateness**: morning (08:00 to 12:00) and afternoon (14:00 to 18:00) shifts, with punctuality and delay calculated automatically.
- **My history**: pick any date to see the full timeline of clock actions and totals for that day.
- **Summary of time errors**: employees can see the clocking problems on their own days and report a time error to their manager.

### For managers and administrators
- **Attendance register** for any date, showing each employee's start and end times, work and break hours, punctuality and status.
- **CSV export** of the register for payroll.
- **Employee management** (administrators): create and update accounts and roles.
- **Time errors by agent**: a read-only report over a date range that lists every clocking problem per employee, such as:
  - missing clock-out or clock-in
  - a break that was never ended, ran over the limit, or happened more often than allowed
  - clocking in again after clocking out
  - actions out of order, or stored totals that don't match the clock times
  - unusually long or short shifts

  It also shows the difference between the recorded work time and the time the clock actions actually support, and can be exported as CSV.
- **Time error reports**: review the reports employees send in and mark them resolved with a note.
- **Correct times** (Management → Correct Times, or "Correct times" next to any error or report): fix an agent's clock-in, break and clock-out times for a day, for example when they were on time but forgot to clock in. A reason is required, and every correction is kept with who made it, when, and the times before and after. The day's totals are recalculated and the Time Errors report updates on its own. Correcting from an employee report also resolves that report. Managers can correct employees only (not themselves); administrators can correct anyone.

### Security
- Passwords hashed with `password_hash()`, session fixation protection and login lockout.
- CSRF tokens on every state-changing request.
- Role-based access (employee, manager, administrator).
- PDO prepared statements throughout. Database credentials live in `.env` and are never committed.

---

## Getting started

### Requirements
- PHP 8.0 or newer with the `pdo_mysql` extension
- MariaDB 10.x or MySQL 8.x

### 1. Get the code
```bash
git clone https://github.com/KazeFuane/virtual-time-clock.git
```

### 2. Create the database
```bash
mysql -u root -e "CREATE DATABASE virtual_time_clock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root virtual_time_clock < virtual-time-clock/database/schema.sql
mysql -u root virtual_time_clock < virtual-time-clock/database/seed.sql
```
If you already have a database from an older version, run the migrations in `database/migrations/` instead of the full schema.

### 3. Configure
Copy `.env.example` to `.env` and fill in your database settings:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=virtual_time_clock
DB_USER=root
DB_PASSWORD=
APP_TIMEZONE=Africa/Casablanca
```
Break length, breaks per day, shift times and session lifetime can also be changed in `.env` (see `config/config.php` for every option).

### 4. Run it
The app expects to be served from a folder named `virtual-time-clock`, so serve the **parent** folder:
```bash
php -S 127.0.0.1:8000 -t /path/to/parent-folder
```
Then open <http://127.0.0.1:8000/virtual-time-clock/public/>.

It also works under XAMPP, WAMP or any Apache/Nginx setup as long as the project is reachable at `/virtual-time-clock/`.

### Demo accounts
The seed data creates these **development-only** accounts. Change or remove them before going live.

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin2024!` | Administrator |
| `manager` | `Manager2024!` | Manager |
| `employee` | `Employee2024!` | Employee |

---

## Project structure

```
virtual-time-clock/
├── api/                  JSON endpoints
│   ├── auth/             login, logout, current user
│   ├── attendance/       clock actions, today, history, own time errors
│   └── admin/            attendance register and CSV, users, time errors, error reports
├── config/               app settings (.env reader) and database connection
├── database/             schema, seed data and migrations
├── public/               pages: dashboard, history, time errors, admin console
│   └── assets/js/        API helper, live clock and timers
├── src/
│   ├── Helpers/          JSON responses, time formatting, validation
│   ├── Middleware/       auth, admin role and CSRF checks
│   └── Services/         attendance logic, auth, users, time error checks and reports
└── views/                layout, header, sidebar and login view
```

---

## How time is counted

Each day is a small state machine: **Not started → Working ⇄ On break → Finished**. Every transition is saved as a timestamped action, and the day's totals are calculated from those actions: worked time is the time from start to end minus the break. Because the raw actions are kept, the Time Errors report can replay them and point out any day where the stored totals don't add up.

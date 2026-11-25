# ClockWise

ClockWise (repository: virtual-time-clock) is a web-based time clock for employees. Staff clock in, take their break and clock out from the browser, and every action is recorded so they are paid for exactly the time they worked. Managers and administrators get a live attendance register, CSV exports and a report that flags clocking mistakes before payroll.

Built with **PHP 8**, **MariaDB/MySQL**, **Tailwind CSS** and **vanilla JavaScript**. No framework and no Composer dependencies.

---

## Features

### For employees
- **Virtual time clock**: a live digital clock and dial with one-click *Start workday*, *Start break*, *End break* and *End workday*.
- **Live counters** for worked time and break time, kept in sync with the server clock.
- **Break policy**: one 45-minute break per day by default (configurable).
- **My Schedule**: each agent sees their planned shifts, week by week or month by month. Punctuality and delay are calculated against those shifts. Agents with no schedule follow the default one: morning (08:00 to 12:00) and afternoon (14:00 to 18:00) shifts, Monday to Friday.
- **My history**: pick any date to see the full timeline of clock actions and totals for that day.
- **Summary of time errors**: employees can see the clocking problems on their own days and report a time error to their manager.

### For everyone
- **Three languages**: English, French and Arabic, chosen from the language picker in the header or on the sign-in page. Arabic switches the whole layout to right-to-left. The choice is saved per user (and in a cookie, so the sign-in page remembers it). Any text without a translation falls back to English. To add a language, copy `lang/fr.php` to `lang/<code>.php`, set its name and direction and translate the lines; it appears in the picker on its own.
- **Light and dark mode**: a toggle in the header (and on the sign-in page) switches themes. Dark is the default. The choice is saved per user, so it follows them to any browser.
- **Search** in the header: press `/` or `Ctrl+K` to jump to it. Employees search the app's pages; managers and administrators can also find people (managers see employees, administrators see any active account) and jump straight to their schedule or time errors.

### For managers and administrators
- **Attendance register** for any date, showing each employee's start and end times, work and break hours, punctuality and status.
- **CSV exports** for payroll: the attendance register, the time errors Summary and Details sheets, and the team plan. Files use UTF-16 with tab separators, so they open straight into columns in Excel whatever the regional settings, keep accented and Arabic names readable, and also open in Google Sheets and LibreOffice. Cells starting with `=`, `+` or `@` are escaped so a spreadsheet never runs them as formulas.
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
- **Plan schedules** (Management → Plan Schedules): plan each agent's shifts.
  - *Schedules* are named weekly patterns (e.g. "Early team", 06:00 to 14:00 Monday to Friday), with up to 4 shifts per day or none (day off). One schedule is the default for agents with no schedule assigned.
  - *Assign* a schedule to one or several agents from a start date. The history is kept, so past days keep the hours that applied then.
  - *Change single days* by clicking a day in the team plan: other hours, an extra working day, or a day off, for one agent or several, for one day or a range.
  - The time errors report (lateness, early departure, absences), Correct Times ("Fill missing in/out" and the default times), the attendance register and the CSV exports all use each agent's planned shifts. A day with no shift planned is not counted as an absence. The team plan can be downloaded as CSV.
  - Managers plan employees only (not themselves); administrators can plan anyone. Editing a schedule changes it on every day it is used, past days included; to change hours from a date on, create a new schedule and assign it from that date. Shifts must start and end on the same day (no overnight shifts).
- **Leave & PTO** (Management → Leave & PTO, or "Record leave" next to an absence): record paid time off, sick leave, special leave or unpaid leave for an agent, as a full day, a morning or afternoon shift, or a date range (days with no shift planned are skipped). Those days show as leave in the time errors summary instead of an absence, with the hours counted as paid or unpaid leave, and appear in the Summary, Details and Attendance CSVs. On a half day the late/early checks use the shift that is worked. Clocking in on a full day of leave is flagged. Leave can be cancelled; the record keeps who recorded and cancelled it, and why. Leave types and whether they are paid are set in `src/Services/LeaveService.php`.

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
If you already have a database from an older version, run the migrations in `database/migrations/` instead of the full schema, oldest first, skipping any you have already applied:
```bash
mysql -u root virtual_time_clock < virtual-time-clock/database/migrations/2026_11_22_time_error_reports.sql
mysql -u root virtual_time_clock < virtual-time-clock/database/migrations/2026_11_24_time_corrections.sql
mysql -u root virtual_time_clock < virtual-time-clock/database/migrations/2026_11_25_schedules.sql
mysql -u root virtual_time_clock < virtual-time-clock/database/migrations/2026_11_25_leaves.sql
mysql -u root virtual_time_clock < virtual-time-clock/database/migrations/2026_11_25_user_preferences.sql
```

| Migration | Adds |
|---|---|
| `2026_11_22_time_error_reports.sql` | Time error reports sent by employees |
| `2026_11_24_time_corrections.sql` | Time corrections and their audit trail |
| `2026_11_25_schedules.sql` | Schedules, schedule assignments and single-day changes |
| `2026_11_25_leaves.sql` | Leave and PTO records |
| `2026_11_25_user_preferences.sql` | Per-user preferences (language, light/dark mode) |

Every migration uses `CREATE TABLE IF NOT EXISTS`, so running one twice is harmless. The app also creates the time corrections, schedules, leave and preferences tables on first use if their migration was not run.

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
Break length, breaks per day, login lockout and session lifetime can also be changed in `.env` (see `config/config.php` for every option). Each agent's working hours come from their schedule (Management → Plan Schedules), not from `.env`. Languages, themes, schedules and leave need no extra `.env` settings.

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
│   ├── auth/             login, logout, current user, language and theme choice
│   ├── attendance/       clock actions, today, history, own schedule, own time errors
│   ├── admin/            attendance register, users, time errors, error reports,
│   │                     corrections, schedules and leave (JSON and CSV)
│   └── search.php        people search for the header
├── config/               app settings (.env reader) and database connection
├── database/             schema, seed data and migrations
├── lang/                 translations: fr.php (French), ar.php (Arabic)
├── public/               pages: dashboard, history, schedule, time errors, admin console, leave
│   └── assets/
│       ├── css/          light theme and right-to-left styles
│       └── js/           API helper, live clock and timers, theme switch
├── src/
│   ├── Helpers/          JSON responses, time formatting, validation, CSV export,
│   │                     translations, theme
│   ├── Middleware/       auth, admin role and CSRF checks
│   └── Services/         attendance, auth, users, time errors and reports,
│                         corrections, schedules, leave
└── views/                layout, header (search, language picker, theme toggle),
                          sidebar and login view
```

---

## How time is counted

Each day is a small state machine: **Not started → Working ⇄ On break → Finished**. Every transition is saved as a timestamped action, and the day's totals are calculated from those actions: worked time is the time from start to end minus the break. Because the raw actions are kept, the Time Errors report can replay them and point out any day where the stored totals don't add up.

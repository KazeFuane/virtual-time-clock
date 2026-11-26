# Putting ClockWise online for free

Two free options are described here:
- **Option A: AlwaysData** (below): PHP 8, MariaDB, HTTPS, SSH and git. Site at `https://<account>.alwaysdata.net`.
- **Option B: InfinityFree** (at the end): no credit card asked, but no terminal or git, so you upload files and use phpMyAdmin.

The app keeps its usual folder layout, so pages stay at `/virtual-time-clock/public/...` and the API at `/virtual-time-clock/api/...`, exactly like on your PC.
The `.htaccess` file in the project root blocks everything else (`.env`, `config/`, `src/`, `database/`, `.git/` ...) from the web.

> Screen and menu names on AlwaysData may be worded slightly differently from what is written here.

---

## 1. Create the account

1. Go to <https://www.alwaysdata.com> and sign up for the **Free** plan (100 MB, enough for this app).
2. The account name you choose becomes your web address: `https://<account>.alwaysdata.net`.

## 2. Pick PHP 8.3

**Environment → PHP**: choose PHP **8.3** (8.0 or newer works).

## 3. Create the database

1. **Databases → MySQL → Add a database**. Name it for example `<account>_clockwise`.
2. **Databases → MySQL → Users → Add a user**, give it a strong password and full rights on that database.
3. Write down:
   - Host: `mysql-<account>.alwaysdata.net`
   - Database name: `<account>_clockwise`
   - User and password you just created

## 4. Open a terminal (SSH)

**Remote access → SSH**: allow password login for your SSH user, then either
- open the web terminal from the AlwaysData admin, or
- from Windows PowerShell: `ssh <account>@ssh-<account>.alwaysdata.net`

## 5. Get the code

```bash
cd ~/www
git clone https://github.com/KazeFuane/virtual-time-clock.git
```

If the GitHub repository is **private**, git will ask for a username and password: use your GitHub username and a
[personal access token](https://github.com/settings/tokens?type=beta) with read-only "Contents" access to this repository as the password.

Make sure everything you want online is pushed to GitHub first (see "Updating the site" below).

## 6. Create the `.env` file (secrets live only here)

```bash
cd ~/www/virtual-time-clock
nano .env
```

Paste this and fill in your own database values (Ctrl+O, Enter, Ctrl+X to save):

```ini
DB_HOST=mysql-<account>.alwaysdata.net
DB_PORT=3306
DB_NAME=<account>_clockwise
DB_USER=<database user>
DB_PASSWORD=<database password>

APP_ENV=production
APP_TIMEZONE=Africa/Casablanca
APP_NAME=ClockWise
```

`APP_ENV=production` hides PHP error details from visitors. Never commit this file; it is already in `.gitignore`.

## 7. Create the tables and your admin account

```bash
cd ~/www/virtual-time-clock
mysql -h mysql-<account>.alwaysdata.net -u <database user> -p <account>_clockwise < database/schema.sql
php database/create-admin.php
```

`schema.sql` already contains every migration, so you don't need the files in `database/migrations/` on a new site.
`create-admin.php` asks for a username, email, name and password and creates the first administrator.

**Don't import `database/seed.sql` on the live site.** It adds the demo accounts (`admin / Admin2024!` ...) whose passwords are written in the README for anyone to read.

## 8. Send visitors from the home page to the app

```bash
echo 'RedirectMatch 302 ^/$ /virtual-time-clock/public/' > ~/www/.htaccess
```

Now `https://<account>.alwaysdata.net/` opens the sign-in page.

## 9. Turn on HTTPS only

**Web → Sites → (your site) → edit → SSL**: tick **Force HTTPS**. The session cookie is marked "secure" automatically when the site is served over HTTPS.

## 10. Check it

1. Open `https://<account>.alwaysdata.net/` and sign in with the admin you created.
2. Open `https://<account>.alwaysdata.net/virtual-time-clock/.env`: it must say **Forbidden**. If it shows your settings, stop and tell Claude.
3. Add your managers and employees from the Admin page.

---

## Updating the site

On your PC, commit and push as usual. Then on AlwaysData:

```bash
cd ~/www/virtual-time-clock
git pull
```

If the update added a new file in `database/migrations/`, run it once:

```bash
mysql -h mysql-<account>.alwaysdata.net -u <database user> -p <account>_clockwise < database/migrations/<new file>.sql
```

(Most features also create their table on first use.)

## Backups

From the AlwaysData admin, **Databases → MySQL** has a backup/export option. Download one now and then: this is where everybody's hours live.

---

## Option B: InfinityFree (no credit card asked)

InfinityFree is free with no credit card: PHP 8.3, MySQL, 5 GB, free HTTPS, a free `*.infinityfreeapp.com` address.
It has no terminal and no git, so you upload files (File Manager or FileZilla) and use phpMyAdmin for the database.
Your site will be at `https://<name>.infinityfreeapp.com` (or whichever free subdomain you pick).

1. **Sign up** at <https://www.infinityfree.com>, then **Create Account** (hosting account) and choose a free subdomain.
2. **Database**: in the control panel open **MySQL Databases**, create one (for example `clockwise`). Write down the
   **MySQL host** (like `sqlXXX.infinityfree.com`), the **database name** (like `if0_12345678_clockwise`),
   the **MySQL username** (like `if0_12345678`) and the **password** (shown in the account's details as the hosting/vPanel password).
3. **Tables**: click **phpMyAdmin** next to the database → **Import** → choose `database/schema.sql` from your PC → **Go**.
   Then import `database/seed.sql` the same way (this creates the roles and a first `admin` account).
4. **Files**: open **File Manager** (or connect with FileZilla using the FTP details on the account page), go into `htdocs/`,
   create a folder `virtual-time-clock` and upload the whole project into it, except `.git/` and your PC's `.env`.
   You should end up with `htdocs/virtual-time-clock/public/`, `htdocs/virtual-time-clock/api/`, and so on, plus the `.htaccess` file.
5. **`.env`**: in File Manager, create `htdocs/virtual-time-clock/.env` with:

   ```ini
   DB_HOST=sqlXXX.infinityfree.com
   DB_PORT=3306
   DB_NAME=if0_12345678_clockwise
   DB_USER=if0_12345678
   DB_PASSWORD=<your password>

   APP_ENV=production
   APP_TIMEZONE=Africa/Casablanca
   APP_NAME=ClockWise
   ```

6. **Home page redirect**: delete InfinityFree's default `htdocs/index2.html` (or `index.html`) if there is one, and create
   `htdocs/.htaccess` containing this single line:

   ```
   RedirectMatch 302 ^/$ /virtual-time-clock/public/
   ```

7. **HTTPS**: control panel → **SSL Certificates** → request a free certificate for your subdomain and wait until it is issued.
8. **Lock down the demo accounts straight away**: sign in as `admin` / `Admin2024!`, set a new password for `admin` on the
   Admin page, and deactivate the demo `manager` and `employee` accounts. Their passwords are public in the README.
9. **Check**: `https://<name>.infinityfreeapp.com/virtual-time-clock/.env` must say **Forbidden**.

**Updating**: upload the changed files again over the old ones (File Manager or FileZilla). If an update added a file in
`database/migrations/`, import it once in phpMyAdmin.

Limits to know: about 50,000 visits a day, PHP scripts stop after a few seconds, and the site may be suspended if it goes far over the limits. For a team clocking in and out this is plenty.

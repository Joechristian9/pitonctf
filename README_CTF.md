# PitonCTF: classroom CTF

A classroom CTF with **one game**. There is no sign-up page: the admin adds every student. Students log in, find `CTF{...}` flags in the challenge files on `/challenges`, submit them and race on `/scoreboard`. The built-in challenges are 3 warm-ups and 30 scored puzzles, 10 each in Crypto, Web and Forensics, worth 4100 points.

## Admin

Admins see **Admin** and **Students** in the navigation. Roles come from [spatie/laravel-permission](https://spatie.be/docs/laravel-permission): `admin` and `user` (student). The instructor account created by `db:seed` from `CTF_INSTRUCTOR_*` gets the `admin` role. To make someone else an admin, run `php artisan tinker --execute 'App\Models\User::where("email", "them@school.example")->sole()->syncRoles(["admin"]);'`.

- **The game:** it starts as a **Draft**, so students see "not started yet" and no challenges. Press **Start game** when class begins; students can play only while it is **Running**. **Lock game** freezes it: students can still look, but they can no longer submit flags or open hints. You can reopen a locked game. You can rename the game but not create a second one.
- **Challenges:** you enter the flag once. It is stored only as a SHA-256 hash and can never be shown again, so keep your own copy. When editing, leave the flag blank to keep it. Uploaded files go to `public/ctf/uploads/{challenge id}/`. Allowed types are txt, log, html, htm, css, js, json, csv, md, sql, png, jpg, jpeg, gif, svg, pdf, zip and pcap, at most 10 MB each. Uploaded HTML and JS run on the site's own domain, so only admins can upload.
- **Teams:** students play in teams of up to 4. Under **Teams**, create a team (unique name), add students who have no team yet, remove members, or delete a team (this deletes its points too). A team can play as soon as it has one member; a student without a team sees why they cannot play yet. Solves, the 8 tries and opened hints belong to the team, and each solve shows which member found it. Admins never join a team.
- **Students:**
    - **Add a student** with a password you choose. Production rules apply: 12+ characters, mixed case, numbers and symbols.
    - **Add a class list** by pasting one `Name, email` per line (the last comma or tab splits name from email, so `Christian, Joe, joechristian@school.example` works). Passwords are generated and shown **once**, so copy them before you leave the page. If any line is wrong, nobody is added and the error names the line.
    - **Reset password** generates a new one and shows it once. Password-reset emails do not work with `MAIL_MAILER=log`, so this is how students get back in.
    - **Delete** removes the account with its solves and tries. You cannot delete yourself.
    - Accounts you add are already verified, so no verification email is needed.
- **View as student** opens the challenges page as students see it, including a draft. Admins never appear on the scoreboard.
- Deleting a challenge also deletes its solves, tries and uploaded files.

## Warm-ups and tools

**Warm-ups first.** Three PITON-themed warm-ups (0 points, no try limit, free hints) teach how to play. Each one unlocks a category, so students cannot start that category before solving it:

| Warm-up                 | Students must                                                                                                                                                                                             | Unlocks   |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| 31 Welcome to PITON CTF | Find PITON's official page online and read its bio. **During class, the bio of PITON's Facebook page must contain flag 31 from `ctf-instructor/flags.php`, exactly as written** (capital letters matter). | Crypto    |
| 32 Membership Form      | Find the flag in the hidden document properties (Comments) of a PITON membership form `.docx`                                                                                                             | Web       |
| 33 PITON Chant          | Decode a PITON chant written in Morse code                                                                                                                                                                | Forensics |

Locked categories show only titles and points: no description, no files, and submissions are refused. Admins skip the warm-ups. Warm-ups never count on the scoreboard. To change which warm-up unlocks what, edit `unlocks_category` in `ctf-instructor/build_extra.php` and rebuild. Hints for every challenge are in `ctf-instructor/hints.php`. They are deliberately indirect: they nudge, they never name the tool or the steps. Warm-up hints point a little closer, so nobody stays locked out of a category. The verifier cannot open Facebook, so it reports warm-up 31 as SKIP: check the bio yourself before class.

**Everyday tools only.** The 30 scored challenges (10 Crypto, 10 Web, 10 Forensics, 4100 points) go from beginner (50 pts) to medium (250 pts). They need nothing beyond what a school PC already has:

| Tool                                                           | Used for                                                       |
| -------------------------------------------------------------- | -------------------------------------------------------------- |
| A browser (Chrome or Edge) with DevTools                       | Every Web challenge, and opening the `.har` file in 27         |
| [CyberChef](https://gchq.github.io/CyberChef/), in the browser | Decoding in the Crypto challenges and in 28                    |
| Notepad                                                        | Reading logs and spotting file "magic bytes" (24, 26, 28, 30)  |
| WinRAR or 7-Zip                                                | Archives (25 Box in a Box, 29 Locked Grades, 30 Poster Secret) |

Every name, password and IP in the files is made up. The IPs come from documentation ranges, and the domains end in `.example`. The Web practice pages are styled as a PITON student portal, and each one says in its footer that it is a practice page, not a real PITON system.

Rules students should know:

- **8 tries per challenge, per team.** Every submission by any member counts, right or wrong. After the 8th wrong one the challenge locks for the whole team. To unlock it, delete the team's row in the `attempts` table, which also resets its hint.
- **Hints cost half the points.** A hint stays hidden until a member clicks "Show hint" and confirms; then the whole team sees it. Solving afterwards earns half the challenge's points. Once a challenge is solved, its hint is free to read.

## How it fits together

| Path                                   | What it is                                                                                        | Upload to server?                                             |
| -------------------------------------- | ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| `ctf-instructor/`                      | `flags.php` (plain flags), `build_challenges.php`, `verify.php`, `ANSWER_KEY.md`                  | **Never.** Git-ignored.                                       |
| `public/ctf/`                          | Generated challenge files students open (static only, no server code)                             | Yes                                                           |
| `database/data/challenges.json`        | Generated challenge list with SHA-256 flag hashes (no plain flags)                                | Yes                                                           |
| `database/seeders/ChallengeSeeder.php` | Loads the JSON into the game, creating it as a draft if missing (upsert by number, safe to rerun) | Yes                                                           |
| `public/ctf/uploads/`                  | Challenge files uploaded from the admin panel                                                     | Created on the server; download it before redeploying over it |

The app only ever stores and compares hashes: `sha256(trim(input))` against `flag_hash`, with `hash_equals`.

## Run locally (XAMPP)

1. Start MySQL in XAMPP and create the database `pitonctf` (phpMyAdmin, or `C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE pitonctf"`).
2. In `.env` set:
    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=pitonctf
    DB_USERNAME=root
    DB_PASSWORD=
    CTF_INSTRUCTOR_EMAIL=     # optional: your account, created by db:seed
    CTF_INSTRUCTOR_PASSWORD=
    ```
3. Build and check the challenges, then set up the app:
    ```
    php ctf-instructor/build_challenges.php
    php ctf-instructor/verify.php
    php artisan migrate:fresh --seed
    npm run build
    composer run dev           # or: php artisan serve
    ```
4. Log in as the instructor (`CTF_INSTRUCTOR_*`), add students under **Students**, put them in teams of 4 under **Teams**, and press **Start game** under **Admin**, because a freshly seeded game is a draft. Students land on `/challenges` after logging in. Locally the seeder also creates "Demo Team" with `test@example.com` to `test4@example.com` (password `password`). This only happens when `APP_ENV=local`.

Tests: `php artisan test` (runs on in-memory SQLite and never touches `pitonctf`).

## Deploy to Hostinger (shared hosting)

Hostinger has PHP and MySQL but no Node, queue worker or Redis, so assets are built on your PC.

1. **On your PC:** `npm run build` (creates `public/build`).
2. **Upload everything EXCEPT** `ctf-instructor/`, `node_modules/`, `.env`, `.git/`, `tests/` (optional) and `storage/logs/*`.
   `vendor/` must be there. Either upload it, or run `composer install --no-dev --optimize-autoloader` over SSH.
3. **Point the domain at `public/`.** If hPanel lets you change the document root, set it to `.../PitonCTF/public`. If not (common on shared plans), put the project one level above `public_html` and replace `public_html` with the contents of `public/`. Then change every `__DIR__.'/../` in `public_html/index.php` to `__DIR__.'/../PitonCTF/` (three paths: `storage/framework/maintenance.php`, `vendor/autoload.php`, `bootstrap/app.php`). Also add `$app->usePublicPath(__DIR__);` on the line before `$app->handleRequest(...)`. Without it, files uploaded from the admin panel are saved into `PitonCTF/public/`, which the web server does not serve.
4. **Create `.env` on the server** (copy `.env.example`) and set at least:
    ```
    APP_NAME=PitonCTF
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://your-domain
    DB_CONNECTION=mysql
    DB_HOST=localhost         # from hPanel → Databases
    DB_DATABASE=u123_pitonctf
    DB_USERNAME=u123_piton
    DB_PASSWORD=...
    SESSION_DRIVER=database
    CACHE_STORE=database
    QUEUE_CONNECTION=sync     # no queue worker on shared hosting
    MAIL_MAILER=log           # or real SMTP from hPanel
    CTF_INSTRUCTOR_NAME="Your Name"
    CTF_INSTRUCTOR_EMAIL=you@school.example
    CTF_INSTRUCTOR_PASSWORD=a-long-unique-password
    ```
5. **Over SSH** in the project folder:

    ```
    php artisan key:generate --force
    php artisan migrate --seed --force
    php artisan config:cache
    php artisan route:cache
    php artisan permission:cache-reset
    ```

    After any later `.env` change, run `php artisan config:cache` again.

    The seeder creates your instructor account from `CTF_INSTRUCTOR_*` (already verified). Rerunning `php artisan db:seed --force` updates its name and password from `.env`. Once you have logged in, you can blank `CTF_INSTRUCTOR_PASSWORD` and rerun `config:cache`, so the password is not left in plain text in `.env` or the config cache.

Notes:

- With `APP_ENV=production`, passwords must be 12+ characters with mixed case, numbers and symbols, and must not appear in a known data breach. That's the kit's default. Tell students before class.
- The kit's email verification stays on. Challenges and the scoreboard only need a login, so students can play without verifying. With `MAIL_MAILER=log`, verification and password-reset emails go to `storage/logs` instead of being sent.

## Changing challenges

For your own games, use the admin panel. The steps below are only for the 33 built-in challenges, which come from the build script (challenges 1-9 in `build_challenges.php`, 10-30 and the warm-ups 31-33 in `build_extra.php`, checked by `verify.php` and `verify_extra.php`).

1. Edit `ctf-instructor/flags.php` and/or `ctf-instructor/build_challenges.php` (texts, hints, points, files).
2. Rebuild and check:
    ```
    php ctf-instructor/build_challenges.php
    php ctf-instructor/verify.php      # every line must say PASS
    ```
3. Locally run `php artisan db:seed --class=ChallengeSeeder`. On the server, upload `public/ctf/` and `database/data/challenges.json`, then run `php artisan db:seed --class=ChallengeSeeder --force`.

Good to know:

- Reseeding updates challenges by `number` and keeps existing solves.
- Changing a flag does not take points away from students who already solved the old one.
- If you rename or remove a challenge file, delete the old file from `public/ctf/` yourself. The builder only writes, it never deletes.
- Removing a challenge from the builder does not delete it from the database. Delete its row in phpMyAdmin, which also deletes its solves.

## Resetting scores

Clear all solves (keeps students and challenges):

- phpMyAdmin: `TRUNCATE TABLE solves; TRUNCATE TABLE attempts;`
- or SSH: `php artisan tinker --execute 'App\Models\Solve::truncate(); App\Models\Attempt::truncate();'`

## Removing a student

Deleting a student removes them from their team. The team keeps its solves (the "solved by" name is cleared) and can keep playing:

- phpMyAdmin: `DELETE FROM users WHERE email = 'student@example.com';`
- or SSH: `php artisan tinker --execute 'App\Models\User::where("email", "student@example.com")->delete();'`

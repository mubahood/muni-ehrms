# Production deployment — 3 October 2026

**Site:** https://muni-ehrms.ugnews24.info · **Host:** HostGator cPanel (`/home4/schooics/muni`, PHP 8.3)
**Released:** template alignment (`TEMPLATE_ALIGNMENT_PLAN.md`) and interface redesign (`UI_REDESIGN_PLAN.md`).

## 1. What the server logs showed, and what was done

| Problem in the logs | Cause | Fix |
|---|---|---|
| ~440 `Invalid webhook token attempt` from the device bridge (137.63.168.9), every working day | The server token was changed but the bridge in the field was not, so every punch was refused (401). The last punch stored was on 2 Feb 2026. | One shared check, `App\Support\WebhookAuth`, now used by both webhook controllers. It accepts `HIKVISION_WEBHOOK_TOKEN` plus any token in `HIKVISION_WEBHOOK_TOKENS_ACCEPTED`, and the bridge's token is listed there on the server. Refusals are logged at most once an hour per source, with a short fingerprint of the token, never the token itself. |
| 13 × `Unable to launch a new process` | The scheduler's `command()` tasks fork a new PHP process, and the account is capped at 25 processes. | Every scheduled task now runs inside the scheduler's own process (`call()`). |
| Scheduled tasks never ran on time | The server cron runs `schedule:run` every 20 minutes, but the tasks were set to `hourlyAt(1)`, `dailyAt('00:30')` and every 5 minutes, which that cron never reaches. | Events and today's attendance refresh run every 20 minutes. The end-of-day settlement runs at 00:20. |
| `Attempt to read property "id" on null` (`ApiResurceController`) | The user's id was read before checking that a user was signed in. | The check now comes first. |
| `The "--name" option does not exist` | A one-off manual `schedule:test --name=…`; Laravel 8 has no such option. | None needed. |
| Punches stored 2 hours late (found once the bridge got through) | The door terminal's clock reads 2 h ahead of Kampala and labels its times `+08:00` (Hikvision default zone). | `EventLog::deviceTimeToLocal()` compares the device time with the moment the server receives the punch; a whole-hour gap (±15 min) is treated as a clock setting error and removed, the original kept in `event_time_raw`. Once the terminal is set right the gap is 0 and nothing changes. The three punches already received were corrected. |
| `/calendar` returned 500 | A stale route to a method that never existed (found by the post-deploy page sweep). | Route removed. |

## 2. Steps

1. Backup to `~/muni_backup/20261003-083100/`: `db.sql.gz`, `code.tar.gz` and `env.bak`.
2. Compared every source file on the server with the release. The hardening edits made on the server on 29 Sep are all present in the release, and `vendor/` is identical, so `vendor/` was not shipped.
3. `artisan down`, then extract the release, add `HIKVISION_WEBHOOK_TOKENS_ACCEPTED` to `.env`, run `migrate --force` (12 migrations), rebuild the caches, and `artisan up`.
4. Data:
   - `leave:normalise-legacy` moved the one pending request onto the new workflow; it now waits for HR.
   - Two device events that had failed on a code bug were re-queued and processed.
   - `attendance:rebuild --from=2026-01-11` rebuilt 266 days from the clock-ins.
5. Seeding happens in the migrations themselves:
   - 24 Uganda public holidays.
   - The Dean and University Secretary roles.
   - The Muni University identity in place of the "Faras" placeholders.
   - **No demo data**: demo sign-ins and e-mail notifications are off on the server.

## 3. Verification

- `ehrms:verify --everyone`: all 15 checks pass (1,596 person-days recomputed independently; dashboard, chart and PDF totals agree).
- A signed-in GET sweep of every page for all six live accounts: 348 requests, no server errors.
- Login, assets and the HTTP→HTTPS redirect work. The webhook accepts the bridge's token and refuses a wrong one.
- Live: the bridge's next punches (16:38 EAT) were accepted (200), stored, matched to the employee and turned into attendance.

## 4. For HR / the administrator

- **Punches from 2 Feb to 3 Oct 2026 never reached the server.** The bridge sends in real time and does not resend. To recover them, re-export the event log from the terminals for that period.
- **Live staff have no Terminal ID (employee number).** Terminal ID "1" currently matches user 1 through the legacy fallback. Set each person's Terminal ID (Users → edit) so punches land on the right person.
- When the bridge is next updated, give it the main token and empty `HIKVISION_WEBHOOK_TOKENS_ACCEPTED`, then run `php artisan config:cache`.
- **Set the door terminal's time zone to UTC+03:00 and enable NTP.** The server corrects whole-hour errors automatically and logs a warning every 6 hours while it does, but a correct clock is better.
- Punches stored before 2 Feb were not changed: whether the clock was wrong then cannot be proven from the data.
- Rollback: restore `code.tar.gz` and `db.sql.gz` from the backup folder.

## 5. Follow-up, 4 October 2026

- Released the round-3 interface (charts, spreadsheet exports, forms); no new migrations.
- Production audit found missing reference data. Added `php artisan ehrms:ensure-baseline` (idempotent, `--dry-run` available, runs nightly at 00:40). It only fills gaps:
  - annual leave allocations for the current leave year (System settings days, nothing carried; Leave planning adjusts);
  - Uganda public holidays for a calendar year that has none (this year and next);
  - the Employee role for anyone with no role;
  - removal of role rows left by deleted accounts.
- First run on production: 6 allocations for 2026/27 (before this, nobody could apply for annual leave, because the balance was 0); 2 people given the Employee role (one of them had a pending leave request she could not open); 21 orphan role rows removed. Backup taken first: `~/muni_backup/20261004-045545-pre-baseline/`.
- Verified on production:
  - code identical to the release;
  - 0 pending migrations;
  - every scheduled job runs in-process without errors;
  - `ehrms:verify` passes;
  - 360 signed-in page loads with no errors;
  - no private file reachable over the web (`.env`, logs, source and docs all 404 or blocked).
- Still for HR: no faculties are defined on production yet, and 3 accounts have no department. Set these under Faculties and Employees so that Dean routing and department reports work.

## 6. Loopholes closed and demo sandbox, 4 October 2026 (afternoon)

**Root cause of the lost clock-ins.** The server `.env` line read `HIKVISION_WEBHOOK_TOKEN=S3GvTd4TfczpF6KADMIN_HTTPS=true`: `ADMIN_HTTPS=true` had been appended without a newline. That corrupted the token and left HTTPS links off. The line is now split, and the temporary `HIKVISION_WEBHOOK_TOKENS_ACCEPTED` workaround has been removed (the bridge matches the main token again). A backup of the old file is in `~/muni_backup/env.before-split-*`.

| Loophole | Fix |
|---|---|
| All 6 live accounts had guessable passwords (one test account had admin rights) | The 4 test accounts are deactivated (reversible). `admin` and Brenda must choose a new password at next sign-in. New passwords need at least 10 characters with letters and numbers, and must not contain the person's name or username. `php artisan ehrms:password-audit [--flag]` repeats the check. |
| Inactive accounts could still sign in | Only Active accounts sign in. |
| No brute-force protection | 5 failures lock that username and address pair for 1 minute; 30 failures lock the address for 10 minutes; lock-outs are audited. |
| Staff report PDFs downloadable without signing in (`/reports/*.pdf`) | Files moved to `storage/app/reports` and served only through `general-reports/{id}/file` (signed-in managers). |
| `/reports` page blank on live: the `public/reports` folder shadowed the route | Folder removed. |
| No browser security headers | X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and HSTS on every response. |
| Sign-in redirects over plain http | `ADMIN_HTTPS=true` now takes effect. |
| Terminal ID 2 ("Androa Hellen") matched to Brenda by the old id fallback | The fallback now requires the terminal's name to match. `ehrms:relink-events` detached the 2 wrong punches and rebuilt the affected days. |
| Placeholder department "Dante Frazier" | Deactivated. |
| Set-up gaps invisible | A "Needs attention" panel on the admin and HR dashboard lists weak passwords, staff without a Terminal ID or department, unmatched terminal people, missing faculties and terminal clock errors. It clears itself as each gap is fixed. |

**Demo sandbox** (`App\Services\DemoSandbox`, `config/demo.php`):
- **What it contains.** 15 `demo.*` accounts covering every role, in 2 faculties and 7 departments (codes prefixed `DEMO-`). It holds 90 days of simulated clock-ins and 20 leave requests at every stage. The password for all accounts is `Demo@2026`.
- **Isolation.** Every row is marked `is_demo`. `Scope`, the approver lists and queues, notifications, the audit log, lookups and the forms all keep the two worlds apart. A guard on the edit, update and delete URLs (`GuardsWorld`) stops address-bar tampering.
- **What demo accounts cannot do** (`AccessPolicy::DEMO_DENIED`): change settings or holidays, manage system users, see device events, run imports, open the older modules, use the demo controls, or change their password. They are never emailed.
- **Upkeep.** Today's clock-ins are topped up every 20 minutes, and the sandbox is rebuilt every Sunday at 01:00.
- **Admin controls.** Under Administration → Demo data the System Administrator can show or hide the login-page modal, delete accounts one by one or all together, and rebuild. The same is available from the command line: `ehrms:demo-seed [--fresh]`, `ehrms:demo-purge`, `ehrms:demo-top-up`.

**Verified on production:**
- `ehrms:verify` passes, for everyone and for the sandbox alone.
- 1,470 signed-in page loads across all 21 accounts produced no errors.
- A real browser signed in through the login modal and crawled the pages for 10 demo roles with no script errors.
- The bridge token is accepted.
- 115 automated tests pass, including 11 sandbox-isolation tests and 4 login-security tests.

**Update (4 Oct, evening):**
- The demo is now 71 staff in 3 faculties and 14 departments (15 of them with sign-ins). Attendance is about 96%, punctuality about 83%, and there are 37 leave requests.
- Real System Administrators can switch into it with **Explore the demo** (on the dashboard and on the Demo data page) and return with **Back to my account**.
- The password rule is now just a minimum of 4 characters, and the forced password changes were cleared.

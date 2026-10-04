# Muni University EHRMS — Template Alignment Plan

**Date:** 3 October 2026
**System:** `muni-ehrms` (Laravel 8 + laravel-admin, MySQL)
**Reference:** `staff_attendance` (Django 5.2 reference implementation of the same university process)
**Status:** Approved for implementation, phase by phase

---

## 1. Purpose

`staff_attendance` encodes Muni University's real HR processes — the leave application form and its approval route, the attendance rules, the report formats — and does so correctly. `muni-ehrms` is the system the University actually runs. This plan takes what the reference does right and that the live system is missing or gets wrong, and brings it across, without disturbing what already works in `muni-ehrms` (Hikvision bridge, vehicle and exit modules, event logs, imports).

The project proposal (`writeups/project_proposal.tex`) promised the University role-based dashboards, employee self-service (apply for leave, view own attendance), multi-level leave approval with balances and carry-forward, "integration with attendance for automatic leave marking", and role-based report access. Most of these do not exist yet. This plan delivers them.

---

## 2. What the audit found

Every finding below was observed in the running system or its data, not inferred.

### 2.1 Attendance gives wrong numbers

| # | Finding | Evidence | Effect |
|---|---|---|---|
| A1 | Any leave row blanks attendance, whatever its status | `Leave` has no status filter in `AttendanceProcessingService::calculateStatus`, `User::isAvailableOnDay`, EOD command. The only leave in the database is `pending`. | A pending or rejected request marks the person "On Leave" and suppresses their absences. |
| A2 | A clock-in on a leave day is thrown away | `isAvailableOnDay()` returns false on leave days → event marked *skipped*. | Someone who came in during leave is not shown as present. |
| A3 | Leave days get no record at all | `generate_attendance_records()` skips unavailable users. | "On leave" never appears in records, so leave days cannot be counted or reported. |
| A4 | Weekend absences on two staff records | Old report: 8 "absent" Sundays for one person. Cause found in Phase 2: those records (`admin`, Brenda Nakacwa) list Saturday and Sunday as personal work days. The form defaults to Monday–Friday. | The engine honours personal work days, so this is a data question for HR, not a code fix — flagged, not changed. |
| A5 | No public holidays | No table, no config. | Independence Day etc. count as absences. |
| A6 | A second capture seconds after arrival becomes the departure | Any scan after the first is `check_out_time`. | "Hours worked" ≈ 0 for people who were scanned twice at the door. |
| A7 | Flat one-hour lunch deduction above 4 hours; half-day hours "up to now" | `calculateHours()`, `calculateStatus()` | Hours are neither time on site nor a defined policy; they change depending on when the job runs. |
| A8 | Lateness compared to the second | `'08:30:30' > '08:30:00'` is late. | Template rule: 08:30:59 is on time, 08:31 is late. |
| A9 | Device events matched by database id | `EventLog::linkUser()` falls back to `users.id`; **0 of 10 users have an `employee_no`**. | Terminal ID "5" credits whichever user has id 5. |
| A10 | App runs in UTC, devices record Kampala time | `event_time` 18:11 vs `created_at` 15:12; `APP_TIMEZONE` unset locally (production already sets `Africa/Kampala`). | "Today" is yesterday between 00:00 and 03:00. |
| A11 | Records are mutated event by event | No way to rebuild a day. | Approving leave, recalling, or adding a holiday cannot correct past days. |

### 2.2 Leave management is a data-entry form, not a process

| # | Finding |
|---|---|
| L1 | No self-service: only an administrator can create leave, on someone's behalf. |
| L2 | No approval route. Workflow columns (`hod_remarks`, `hr_approved_at`, …) exist in the table but nothing uses them. |
| L3 | No entitlements, carry-forward or balance; no check against what remains. |
| L4 | No working-day count, no return date, no overlap check. |
| L5 | No notifications to approvers or applicant. |
| L6 | No withdraw, cancel or recall. |
| L7 | Leave types do not match the university form (6 generic types vs the form's 9). |
| L8 | No printable leave application form. |

### 2.3 Access control blocks the people the system is for

| # | Finding |
|---|---|
| R1 | `AdminRoleMiddleware` admits **only** the `admin` role to every page. HR, Heads of Department and employees can sign in but are sent straight back to the login page. |
| R2 | The `hod` and `hr` roles have no permissions at all. |
| R3 | No Dean or University Secretary role, though both sign the leave form. |
| R4 | No faculties; departments cannot be marked academic or administrative. |
| R5 | No data scoping: there is no notion of "my department" or "my own records". |

### 2.4 Dashboards and reports

| # | Finding |
|---|---|
| D1 | Dashboard title is a running clock (`TIME: 11:13:01`), in UTC. |
| D2 | No "today" view (who is in, late, absent, on leave right now). |
| D3 | Top-5 lists rank people with **0** days. |
| D4 | No employee dashboard (own calendar, rate, punctuality, balance). |
| P1 | **Letterhead prints another organisation's details**: "Namirembe Road", "recruitment@faras.com". |
| P2 | Report summary ignores the report's scope — a department report shows university-wide totals. |
| P3 | Leave counted as number of leave *rows*, not leave *days*. |
| P4 | Page numbers print as "Page" with no number; the footer overlaps the last table row. |
| P5 | No daily register, no leave form, no leave summary. |

### 2.5 Branding and interface

| # | Finding |
|---|---|
| B1 | Page titles are class names ("GeneralReport", "SystemConfiguration"). |
| B2 | Default laravel-admin palette: blue, orange, green and cyan buttons and badges beside the maroon brand. |
| B3 | Rounded multicolour KPI cards with decorative circles — inconsistent with the new square, white sign-in page. |
| B4 | Duplicate "Event Logs" menu entry; menu not organised by task or role. |
| B5 | Broken avatar image in the header; sidebar background stops short of long pages; laravel-admin version in the footer. |

---

## 3. Decisions

These shape everything that follows. Each is a deliberate choice, recorded so it can be revisited.

1. **Attendance is derived, not mutated.** A day's record is rebuilt from the raw clock-ins (`event_logs`) by one engine, as in the reference `process_day`. Event processing, the nightly job, leave decisions, recalls and holiday changes all call the same engine, so they can never disagree. Records corrected by HR, or imported from files, are never overwritten.
2. **Hours worked = time on site** (last capture − first capture). No lunch deduction. A capture within 30 minutes of the first (configurable) is a repeat at the door, not a departure. A day with no departure is credited as a **half day** (half the standard day, 8 h → 4 h). Today stays "in progress" until the day ends.
3. **Role model follows the leave form.** Roles: System Administrator, Human Resource, University Secretary, Faculty Dean, Head of Department, Employee. Faculties are added; a department is academic (belongs to a faculty) or administrative.
4. **Access is deny-by-default per role**, replacing "admin only". Each role is granted named areas; data is scoped (HoD → department, Dean → faculty, HR / US / Admin → university, Employee → self). This keeps the intent of the current lock-down — nothing is open unless granted — while letting the system serve its users.
5. **Leave route** (fixed when the request is submitted): academic staff HoD → Dean → HR → University Secretary; administrative staff HoD → HR → University Secretary. A HoD skips their own stage; a Dean skips HoD and Dean. A vacant HoD/Dean stage passes automatically and the trail says so; HR and the University Secretary are never skipped.
6. **Notifications are in-app always; e-mail only when `NOTIFY_EMAIL=true`.** The local `.env` points at a live SMTP server, and test data must never e-mail real people.
7. **Timezone `Africa/Kampala`** everywhere (production already uses it).
8. **One design language**: white surfaces, square corners, maroon `#800000` as the only accent, neutral greys, one typeface (Inter). Status colours are reserved for status (present, late, absent, leave) and used identically on screen and in PDFs.
9. **Not in this round:** Performance appraisal (Balanced Scorecard), staff photo cropping, payroll. The appraisal module is a full product in itself; it is planned as its own phase (§6) once the core is verified, rather than half-built alongside it.
10. **No production deployment** as part of this work. Everything is built and verified locally; deployment is a separate, explicit step.

---

## 4. Phases

Each phase ends with its acceptance checks passing before the next begins.

### Phase 0 — Safety and foundations

| Task | Detail |
|---|---|
| 0.1 Backups | Working tree, uncommitted diff and full database dump in `~/muni-ehrms-backups/2026-10-03-before-template-upgrade/`. **Done.** |
| 0.2 Timezone | `APP_TIMEZONE=Africa/Kampala` locally; config default `Africa/Kampala`. |
| 0.3 Test database | `muni_ehrms_test` cloned from the live schema (migrations alone do not build it — the base schema came from an SQL dump). PHPUnit runs there inside transactions; the development database is never touched by tests. |
| 0.4 E-mail guard | `NOTIFY_EMAIL` flag; default off. |

**Accept:** test suite runs green against `muni_ehrms_test`; a test that sends a notification produces no SMTP traffic.

### Phase 1 — Organisation and calendar

| Task | Detail |
|---|---|
| 1.1 Faculties | `faculties` (name, code, dean). `departments` gains `code`, `type` (academic / administrative), `faculty_id`. |
| 1.2 Roles | Add `dean` (Faculty Dean) and `us` (University Secretary). Users gain `faculty_id` (for Deans) — HoDs use `departments.hod_id`. |
| 1.3 Public holidays | `public_holidays` (date, name), CRUD for HR/Admin, Uganda's statutory holidays seeded for the current and next year. |
| 1.4 System configuration | Institution identity (name, office, address, phone, e-mail, website, motto) — **replacing the Faras details** — default late time, working days, standard day hours, repeat-capture window, leave-year start month, report footer. One settings page, not a CRUD list. |
| 1.5 Employee number | Every user gets an `employee_no` (Terminal ID). Device matching uses it first; the id fallback is kept only for users without one, so existing terminals keep working. |

**Accept:** a department can be academic in a faculty; a holiday stops a day counting as absent; the letterhead reads Muni University's real details.

### Phase 2 — Attendance engine

| Task | Detail |
|---|---|
| 2.1 `WorkCalendar` | Working day = configured weekday and not a holiday. One query per range. |
| 2.2 `AttendanceEngine::processDay(date, userIds?)` | Rebuilds records for a day from clock-ins, approved leave and the calendar: Present / Late (to the minute) / Absent / On Leave; first in, last out, capture count, minutes late, working-day flag, hours, half-day flag. A clock-in on a leave day counts as present. Non-working days get a record only if someone clocked in. |
| 2.3 Preserve corrections | `is_manual`, `corrected_by`, `corrected_at`, `correction_reason`. Manual and imported records are never rebuilt. |
| 2.4 Wire everything to the engine | Event processing, the nightly job, the hourly placeholder job, leave approval / cancel / recall, holiday changes. One code path. |
| 2.5 HR correction | HR/Admin correct a record with a mandatory reason; logged in the audit trail. |
| 2.6 Rebuild command | `php artisan attendance:rebuild --from= --to= [--user=]`. |

**Accept:** an engine test matrix (§5.1) passes — weekend, holiday, late by one minute, repeat capture, half day, today in progress, pending vs approved leave, clock-in during leave, manual record preserved, rebuild idempotent.

### Phase 3 — Access control and scoping

| Task | Detail |
|---|---|
| 3.1 Policy | `AccessPolicy`: a map of role → allowed areas; deny by default. Replaces the admin-only check in `AdminRoleMiddleware` and the hard-coded `PermissionChecker` paths. |
| 3.2 Scoping | `Scope::users($viewer)` used by every grid, dashboard and report: HoD → own department, Dean → own faculty, HR / US / Admin → all, Employee → self. |
| 3.3 Menu by role | Menu built from the same policy, grouped: *My work*, *Attendance*, *Leave*, *Reports*, *Organisation*, *Administration*. Duplicate entries removed. |

**Accept:** an access matrix test (§5.2) — every role × every area returns allowed or denied as specified; a HoD never sees another department's people in any list, dashboard or report.

### Phase 4 — Leave management

| Task | Detail |
|---|---|
| 4.1 Data | `leaves` extended: reference (`LV-2026-00001`), route, stage, working days, leave year, return date, acting officer, contact address and phone, source (application / HR entry), Section II snapshot, recall fields, decided at. `leave_actions` (the trail). `leave_entitlements` (user, leave year, days due, carried forward). |
| 4.2 Types | The form's nine: Annual, Sick, Study, Maternity, Paternity, Compassionate, Unpaid, Sabbatical, Special leave of absence; plus Official duty/travel and Other for HR entries only. |
| 4.3 Rules | Working days (weekends and holidays excluded), return date, no start in the past (HR may record past leave), overlap with other active leave, annual leave within one leave year and within the available balance (pending requests reserve days). Live day counter and balance on the form. |
| 4.4 Apply | *My leave → Apply*: Section I of the form, prefilled from the staff record. |
| 4.5 Approve | *Leave approvals* queue with count in the menu. HoD/Dean recommend or not; HR verifies and freezes the Section II computation; University Secretary gives the final decision. Reason required to decline. Nobody acts on their own request; only the current stage's officers can act. |
| 4.6 Track | Request page: progress stepper (done / current / waiting / skipped / declined), full trail with comments and who holds it now. |
| 4.7 Withdraw / cancel / recall | Applicant withdraws while pending; HR cancels approved leave not yet started; HR or University Secretary recalls from leave with a resume date — unused days restored, attendance rebuilt from that date. |
| 4.8 Leave planning | HR sets days due and carried forward per person per leave year; bulk set per department; carry forward last year's unused balance up to a cap. |
| 4.9 HR record | HR records leave approved on paper or official travel; counts against the balance. |
| 4.10 Notifications | Database notifications; bell with unread count in the header; e-mail when enabled. |
| 4.11 Leave form PDF | The university form, Sections I–III, filled from the record and trail. |

**Accept:** workflow test matrix (§5.3) — every route, every skip, every decline point, withdraw, cancel, recall, balance exhaustion, overlap, self-approval refused.

### Phase 5 — Dashboards

| Task | Detail |
|---|---|
| 5.1 Organisation dashboard | Today: in, late, absent (not in yet), on leave, with the list behind each number. This month: daily present / leave / absent. This week: on time / late / absent by day. Top absentees and late arrivals over 30 days (people with 0 are not listed). Filter by faculty / department, limited by scope. |
| 5.2 My dashboard | Month statistics, attendance rate, punctuality, average arrival time, colour-coded calendar, 30-day arrival chart against own late time, leave balance and recent requests, link to own report. Every role has it. |
| 5.3 Approver panel | "Waiting for you" list for HoD, Dean, HR, University Secretary. |

**Accept:** every number on the dashboards equals the same number computed independently by the reconciliation command (§5.4).

### Phase 6 — Reports

One shared letterhead partial and stylesheet for every PDF (logo, institution, office, address and contacts, maroon rule; footer with "Generated <date> by <name>", confidentiality line and "Page X of Y"). Correct pagination in dompdf (`isPhpEnabled`), repeated table headers, no overlapping footer.

| Report | Contents |
|---|---|
| 6.1 Individual attendance | Any period: per-day first seen, last seen, hours (half days marked), status; totals, rate, punctuality, average arrival. |
| 6.2 Attendance summary | Department / faculty / administrative units / university, any period: KPI strip, proportion bar, table grouped by department with per-person working days, present, late, absent, leave, minutes late, rate. |
| 6.3 Daily register | One day: every person in scope with arrival, departure, status. |
| 6.4 Leave form | §4.11. |
| 6.5 Leave summary | Requests and days by type and status for a period; balances by person. |

Reports respect scope. The existing *Attendance Reports* history page keeps working and gains the new types.

**Accept:** report totals equal dashboard totals equal reconciliation totals for the same scope and period; visual review of every report against the reference.

### Phase 7 — Branding and interface

| Task | Detail |
|---|---|
| 7.1 Admin skin | Rewrite `skin-muni`: white content, square corners, maroon header and active state, neutral sidebar; buttons, badges, tables, forms, filters and pagination in the house style. |
| 7.2 Titles | Human titles on every page ("Attendance records", "Leave approvals", …). |
| 7.3 Header | Bell, name and role, working avatar (initials fallback); no version footer. |
| 7.4 E-mails | Notification e-mails on the same letterhead. |
| 7.5 Responsive | Usable at 360 px. |

**Accept:** screenshot review of every page at 1440 px and 390 px.

### Phase 8 — Audit trail

Sign-ins (including failed attempts) with IP, attendance corrections, leave decisions, report downloads, configuration changes. Viewable by Admin and HR.

### Phase 9 — Demonstration data and verification

| Task | Detail |
|---|---|
| 9.1 `ehrms:demo-seed` | Faculties and departments of Muni University, ~60 staff with employee numbers, one login per role (and per HoD/Dean), 90 days of realistic clock-ins (punctual, chronic late, frequent absentee, no sign-out, repeat captures), holidays, entitlements, leave in every state. Everything tagged so it can be removed. |
| 9.2 `ehrms:demo-purge` | Removes exactly the demo data and nothing else. |
| 9.3 `ehrms:verify` | Recomputes attendance and leave figures independently from raw data and compares with records, dashboards and report data; prints any mismatch. |

---

## 5. Test plan

### 5.1 Attendance engine (unit/feature)

Weekday on time · late by exactly one minute (08:31) · 08:30:59 on time · weekend with and without clock-in · public holiday · single capture (half day) · repeat capture within window (half day) · capture after window (full hours) · today in progress · absent on working day · pending leave (still absent) · approved leave (on leave) · clock-in during approved leave (present) · recalled leave after resume date (absent if not in) · manual correction survives rebuild · imported record survives rebuild · rebuild twice gives identical rows · user before start date not expected · inactive user not expected.

### 5.2 Access matrix

Six roles × every area (dashboard, my dashboard, records, correct record, employees, departments, faculties, holidays, configuration, users, leave apply, approvals, all leave, planning, record leave, reports by scope, audit log, event logs, imports). Plus: HoD scoping in grids, dashboard and reports; employee sees only self.

### 5.3 Leave workflow

Academic route, administrative route, HoD applicant, Dean applicant, vacant HoD, vacant Dean, decline at each stage (reason required), wrong user cannot act, applicant cannot act on own request, withdraw (only while pending, only applicant), cancel (only approved, not started), recall (resume date bounds, days restored, attendance rebuilt), annual balance exhausted, pending reserves days, balance re-checked at HR and US stages, overlap refused, past start refused for applicant but allowed for HR, notifications sent to the right people at each step.

### 5.4 Reconciliation

For every person and every day in the demo period: status counts from records = counts from engine recomputation; for every scope: dashboard figures = report figures = recomputed figures; leave days taken = sum of approved working days; balance = due + carried − taken.

### 5.5 Visual

Every page and every PDF reviewed as a screenshot, desktop and phone.

---

## 6. Later phase — Performance planning and appraisal

The reference implements the Muni University Individual Balanced Scorecard end to end (plan under four perspectives, mid-year review, self-appraisal, supervisor scoring, behavioural assessment, improvement plan, countersignature, HR verification, Responsible Officer sign-off, PDF, statistics). It is promised in the proposal (Module 4). It depends on the role model, scoping, notifications and PDF framework built here, and should follow once this round is verified in use.

---

## 7. Risks

| Risk | Mitigation |
|---|---|
| Rebuilding attendance changes historical figures | Expected — the old figures were wrong (§2.1). Rebuild is per date range and logged; backups exist. |
| Existing terminals send IDs that match user ids | id fallback kept for users without `employee_no`; demo users all have one. |
| Uncommitted local changes in the repository | Full diff backed up; work is additive; nothing is reverted. |
| laravel-admin conventions limit custom UI | Workflow screens are Blade views inside laravel-admin's `Content`, so they share the shell, auth and menu. |
| E-mail to real addresses during testing | `NOTIFY_EMAIL` off; demo users use `@demo.muni.ac.ug`. |

---

## 8. Progress

| Phase | Status |
|---|---|
| 0 Safety and foundations | Done — backups, Kampala time, isolated test database, e-mail guard |
| 1 Organisation and calendar | Done — faculties, department types, Dean and University Secretary roles, public holidays, settings page, real letterhead details |
| 2 Attendance engine | Done — `AttendanceEngine`, `WorkCalendar`, HR corrections, `attendance:rebuild` |
| 3 Access control | Done — `AccessPolicy` (deny by default), `Scope`, menu per role |
| 4 Leave management | Done — apply, route, approvals, Section II, withdraw / cancel / recall, planning, HR record, notifications, leave form PDF, legacy adoption |
| 5 Dashboards | Done — organisation dashboard (scoped, filterable), personal dashboard for everyone |
| 6 Reports | Done — summary, individual, daily register, leave report, leave form; archived reports use the same builder |
| 7 Branding | Done — house style (`public/css/ehrms.css`), header, sidebar, titles, favicon, PDFs; responsive to 390 px |
| 8 Audit trail | Done — sign-ins (incl. failed), corrections, leave decisions, report downloads, settings |
| 9 Demo data and verification | Done — `ehrms:demo-seed`, `ehrms:demo-purge`, `ehrms:verify` |

### Verification at hand-over (3 October 2026)

- PHPUnit: 82 tests, 486 assertions, all passing (`muni_ehrms_test`).
- `ehrms:verify`: 15 checks pass; 7,018 person-days recomputed independently from the clock-ins match the stored records exactly. Sabotage tests (moved clock-in, relabelled absence, miscounted request) are each caught.
- Every menu page for all six roles loads without server or browser errors.
- `php artisan route:cache` succeeds.

### Running it

```bash
php artisan migrate                         # 13 new migrations
php artisan leave:normalise-legacy --dry-run # then without --dry-run: adopts old leave rows
php artisan attendance:rebuild --from=YYYY-MM-DD   # rebuild history under the corrected rules
php artisan ehrms:verify --everyone         # confirm everything adds up
```

Demo (never on production): `php artisan ehrms:demo-seed`, sign in as demo.admin / demo.us / demo.hr / demo.dean / demo.hod / demo.employee (password `Demo@2026`), remove with `php artisan ehrms:demo-purge`.

### Found along the way, for a decision

1. **Vendor code was edited in place.** `vendor/encore/laravel-admin/src/Auth/Database/Administrator.php` rebuilds `name` from first and last name on every save, limits mass-assignment, and on create may reset the new user's password and e-mail it. `vendor/.../partials/header.blade.php` was also edited. A `composer update` would silently undo all of it; it should move into `app/`.
2. **Two staff records expect weekend work** (`admin` / Jonas Lane, Brenda Nakacwa): they list Saturday and Sunday as personal work days, so they show weekend absences. HR should confirm.
3. **The leave rules now count only approved leave.** History rebuilt under the new rules will differ from the old figures — intentionally.
4. **Performance appraisal (Balanced Scorecard)** remains the next phase (§6).

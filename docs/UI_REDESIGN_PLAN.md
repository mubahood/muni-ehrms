# Muni University EHRMS — Interface Redesign (round 2)

**Date:** 3 October 2026 · **Follows:** `TEMPLATE_ALIGNMENT_PLAN.md` (functionality, already verified)
**Scope:** look, feel and interaction of the whole system. No change to rules, figures or access.

---

## 1. The brief

| # | Asked for | What it means here |
|---|---|---|
| 1 | Login: less padding, fewer boxes, more solid | Full-height split screen: a solid maroon identity half and a tight white form half. No card, no box around the demo accounts. |
| 2 | Sidebar and header in the primary colour; reactive, modern hover | Maroon header and maroon sidebar (one continuous brand frame), white text, animated hover and active states. |
| 3 | Compact, flat, well organised; no boxes inside boxes | Smaller type and spacing scale; one level of surface only; sections divided by rules, not nested borders. |
| 4 | Better dashboard | Denser, more informative first screen (see §5). |
| 5 | Modals where necessary | Leave decisions, withdraw / cancel / recall, confirmations — in modals, without leaving the page. |
| 6 | Small, clear, bold form fields; AJAX dropdowns | 32 px controls, 600-weight labels and values; people pickers that search the server as you type. |
| 7 | Powerful CSS and JS, seamless | One stylesheet and one script for the whole system, working with laravel-admin's PJAX page loads. |

## 2. Design system v2

**Scale (compact).** Base text 13 px. Controls 32 px high. Spacing steps 4 / 8 / 12 / 16 / 20 px — no gap larger than 20 px inside content. Page gutter 20 px desktop, 12 px phone.

**Colour.** Maroon `#800000` frame (header + sidebar); darker `#5c0000` for depth; white content canvas; greys for structure; status colours unchanged (on time, late, absent, leave).

**Surfaces.** One level: a *section* is a white block with a 1 px border. Inside it: rules, rows, and stat cells — never another bordered box. Square corners everywhere.

**Type.** Inter. Labels 11.5 px, 600, uppercase tracking for section titles; values and inputs 13 px, 500–600 weight so fields read clearly.

**Motion.** 120–160 ms ease for hover, focus, menu and modal; nothing decorative; honours `prefers-reduced-motion`.

## 3. Components

| Component | Behaviour |
|---|---|
| Header | Maroon bar, crest + name, bell with live count, avatar menu. |
| Sidebar | Maroon; group labels; items slide a white bar in on hover, solid lighter maroon when active; count badges; scope note at the foot; collapses to an overlay on phones. |
| Section | `.ehr-sec` — head (title + tools) and body; flat. |
| Stat strip | `.ehr-stats` — cells divided by rules with a coloured top edge; clickable where it leads somewhere. |
| Modal | Bootstrap 3 modal restyled: square, maroon header line, compact body, footer actions. Opened declaratively (`data-modal`) and from code (`EHR.confirm`, `EHR.form`). |
| AJAX form | `form[data-ajax]` posts in the background, shows field errors in place, toasts the result, then refreshes the page section through PJAX. |
| People picker | `select[data-people]` → Select2 searching `/lookup/people` (results limited to the viewer's scope). |
| Date picker | `input[data-date]` → flatpickr, compact, Monday-first, en-GB format. |
| Buttons | 32 px; primary maroon, secondary outline, danger outline; loading spinner state while a request runs. |

## 4. Login

Split screen, full height, no card. Left (≈ 42 %): solid maroon, crest, institution name, system name, three capability lines, footer line. Right: white, form centred in a 340 px column — title, two fields, remember + sign-in, then the test accounts as a flat two-column list separated by rules. Phones: maroon band with crest on top, form below.

## 5. Dashboard

1. Greeting line with scope and live clock; filters inline on the right.
2. **Today** stat strip: expected · in on time · late · not in yet · on leave · in so far %.
3. **Month** chart (stacked by working day) beside a compact month panel (rate, punctuality, minutes lost, half days, with the proportion bar).
4. **Departments league**: each department's attendance rate this month as a horizontal bar, worst first — where a manager should look.
5. Late today · Not in yet · Away now / next 14 days · Waiting for you — compact lists.
6. Most absences / most late (30 days).

## 6. Where modals and AJAX are used

| Place | Interaction |
|---|---|
| Leave request page | Recommend / Verify / Approve, Do not approve, Withdraw, Cancel, Recall — each a modal; posted by AJAX; page refreshes in place. |
| Leave approvals | "Decide" on any row opens the decision modal with the request summary; the row leaves the queue without a reload. |
| Record leave, Reports (individual), Apply (left in charge) | People picker with server search. |
| All date inputs on EHRMS pages | flatpickr. |
| Bell | Mark all read without a reload. |

## 7. Tracking

| Step | Status |
|---|---|
| Plan | Done |
| Design system v2 (`public/css/ehrms.css` rewrite) | Done — compact scale, maroon frame, flat sections, modal / picker / busy styles |
| Header and sidebar | Done — maroon header and sidebar, animated hover and active bar, live clock, white badges |
| Login | Done — full-height split, no card, flat test-account list, show-password, busy button |
| `public/js/ehrms.js` | Done — `EHR.modal` / `EHR.confirm` / `EHR.toast`, `form[data-ajax]` with inline errors, Select2 people search, flatpickr dates, count-up, PJAX re-init, modal z-index fix |
| Server: JSON leave actions, apply and record leave; `/lookup/people` (scoped) | Done |
| Leave pages to modals + AJAX | Done — request page actions in modals; approvals queue decides in place (row leaves, counts update) |
| Dashboard | Done — greeting title, inline filters, today strip, decision queue, month chart + summary, department league, tabbed today lists, week, 30-day lists |
| laravel-admin grids, filters and forms, compact | Done (styled through `ehrms.css`) |
| Tests, crawl, screenshots at 1440 and 390 px | Done — 84 tests / 505 assertions; `ehrms:verify` passes; every page for six roles loads without server or browser errors; a real browser drove an approval end to end |

---

## 8. Round 3 — final mastering (4 October 2026)

| Area | What changed |
|---|---|
| Charts | ApexCharts 3.54 (vendored in `public/vendor/apexcharts`), driven declaratively: the server writes `data-chart` JSON, `ehrms.js` applies one house theme (Inter, square tooltips, 2 px gaps between stacked segments, rounded data ends, PJAX clean-up). Status colours validated for colour-blind separation and 3:1 contrast; late is now `#b97b10`. |
| Dashboard | Weekend and holiday aware: the strip describes the last working day and says so. Daily attendance (month / last 6 weeks switch, working days only, Mondays labelled), month donut with the attendance rate at its centre, weekly attendance and punctuality (12 weeks), arrival-time distribution with the late line, clickable department league with a 95% target tick, ranked lists with avatars and proportion bars. |
| My attendance | Arrival chart redrawn as bars from the late time (early below, late above, clock-time axis); new hours-on-site chart with the full-day line. |
| Reports | Every report also downloads as a spreadsheet (CSV, UTF-8 for Excel, formula-safe). New periods: this year to date, leave year to date. Daily register defaults to the last working day. Summary PDF gains a day-by-day strip and a highlights page (department bars, lowest attendance, most late). |
| Forms and grids | Submit / Reset only (no "continue editing" checkboxes); "Enter …" placeholders; decorative input icons removed; filter and column buttons in house colours; quick filter chips (period, status) above attendance records. |
| Checks | 97 tests / 539 assertions (new: weekend dashboard, charts equal the official totals, spreadsheet scope and formula safety); `ehrms:verify` passes; every page for 84 users loads without a server error; browser crawl clean. |

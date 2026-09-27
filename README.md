# WanderLink CRM

A working prototype of a **Customer Relationship Management System (CRMS)** for *WanderLink Travel*, a fictional independent travel agency in Nairobi. Built for **BIS541 Information Systems Management (CDU), Assignment 2**, so that every part of the written report can be demonstrated and screenshotted from a running system.

**Stack:** Laravel 12 · PHP 8.3+ · Livewire 3 + Volt · Tailwind CSS 4 · Alpine.js · Chart.js · spatie/laravel-permission · spatie/laravel-activitylog · SQLite (MySQL-compatible) · Pest

---

## 1. Setup

Requirements: PHP 8.3+ (with `pdo_sqlite`), Composer 2, Node 20+.

```bash
composer install && npm install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && npm run build && php artisan serve
```

Open <http://localhost:8000>. `composer install` creates `database/database.sqlite` automatically. To start again with fresh demo data, run `php artisan migrate:fresh --seed`.

Optional background jobs. The scheduler runs the scheduled reports, the daily automation and the nightly backup:

```bash
php artisan schedule:work          # or add `php artisan schedule:run` to cron every minute
```

### Demo logins

Every account uses the password `password`. The login page also has one-click demo buttons.

| Role | Person | Email |
|---|---|---|
| Owner | David Mwangi | `owner@wanderlink.test` |
| Manager | Faith Njoroge | `manager@wanderlink.test` |
| Consultant | Achieng Otieno | `consultant@wanderlink.test` |
| Consultant | Brian Kiprop · Mercy Wambui · Kevin Omondi | `brian@` · `mercy@` · `kevin@wanderlink.test` |
| Marketing | Zawadi Mutua | `marketing@wanderlink.test` |
| Support | Grace Wekesa | `support@wanderlink.test` |

Owners and managers can preview every role's dashboard with the **Executive / Consultant / Marketing / Support** switcher.

### Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Runs the Pest suite (86 tests) |
| `php artisan crm:run-scheduled-reports [--all]` | Generates due MIS reports and "emails" them. Mail uses the `log` driver, so check `storage/logs/laravel.log` |
| `php artisan crm:daily` | Updates booking statuses, creates feedback-request and re-booking tasks, runs lifecycle rules (including inactive after 18 months) |
| `php artisan crm:backup` | Dumps the database to `storage/app/backups/wanderlink-YYYYmmdd-His.sqlite` |

---

## 2. Feature-to-assignment mapping

| Assignment requirement | Where it appears in the system |
|---|---|
| Description of the business | Seed data (staff, suppliers, destinations), login page and the business panel on **About the system** (`/about-system`) |
| Data sources and data types | **Data sources and data types** table on `/about-system`. It is generated from the live models and schema, and classifies each source as structured, semi-structured or unstructured, and as internal or external |
| Why customer information matters | **Customer 360** (timeline, lifetime value, NPS, preferences), automatic **lifecycle** stages, role dashboards, and the "why it matters" statistics on `/about-system` (for example, the share of revenue that comes from repeat customers) |
| Steps to build the CRMS | The phased build order in *Section 5* below, which the report reuses as its implementation methodology |
| Who benefits | **Stakeholder benefits matrix** on `/about-system`, plus one dashboard per role |
| Framework | SVG **CRM process framework** on `/about-system`: Acquire → Develop → Serve → Retain → Analyse, with a loop back to Acquire |
| MIS reports: real-time | **Dashboard**. KPIs and charts refresh every 30 seconds (`wire:poll.30s`) |
| MIS reports: scheduled | **Scheduled reports** screen and the `crm:run-scheduled-reports` command, which runs hourly on the scheduler |
| MIS reports: ad-hoc | **Report builder**. Choose an entity, group-by, filters and date range; output is a chart and table with CSV export and "Save as scheduled report" |
| Security and governance (Murray's criteria) | See *Section 4* below: **Staff & roles**, **Audit trail**, **Backups**, and the privacy tools on each customer |

### Module map

| Module | Screens | CRM concept |
|---|---|---|
| Customer 360 | Customers (filters, saved views), customer profile (Timeline, Trips, Preferences, Documents, Contacts; slide-over quick actions) | Single customer view |
| Sales Force Automation | Pipeline Kanban and list, quote builder, printable quote, bookings, payments | Develop / convert |
| Follow-ups | **My Day**: due and overdue tasks, departures within 7 days, passports expiring within 6 months, birthdays | Follow-up discipline |
| Service & Support | Ticket inbox (SLA countdowns), ticket detail (notes, resolution), feedback and NPS, public signed feedback form | Serve / service recovery |
| Marketing | Segment builder (live counts), campaign composer (merge fields, preview), campaign results (opens, bookings, ROI) | Retain / analytical CRM |
| Suppliers | Directory with commission, rating, bookings volume and margin | Partner relationships |
| Reports | Dashboard, report builder, scheduled reports | MIS: real-time, ad-hoc, scheduled |
| Governance | Staff & roles (permission matrix), audit trail, backups | Murray's criteria |

### Automation rules (visible in the data)

- **Lifecycle:** lead → customer on the first booking; → repeat on the second booking; → VIP at 5 or more bookings or KES 1,000,000 lifetime value; → inactive after 18 months with no activity (`LifecycleService`). A stage is never demoted automatically.
- **Tasks:** sending a quote creates a follow-up task due in 2 days. Feedback is requested 3 days after a trip ends, and a re-booking is suggested 11 months after it (`QuoteService`, `TaskAutomationService`).
- **Pipeline:** enquiries with no activity for 3 or more days get an amber "nudge". Moving a deal to Lost requires a reason.
- **Campaigns:** only customers with `marketing_consent = true` are ever messaged. This is enforced in `CampaignService`, whatever rules the segment contains.

---

## 3. Screenshot checklist for the report

| # | Screen | How to get there |
|---|---|---|
| 1 | Owner dashboard | Log in as `owner@`, go to **Dashboard** |
| 2 | Customer 360 | **Customers**, then open a VIP (click the VIP chip) |
| 3 | Pipeline Kanban | Log in as `consultant@`, go to **Pipeline** |
| 4 | Quote builder | Pipeline, open a *Quoted* card, then open its quote |
| 5 | My Day | Log in as `consultant@`, go to **My Day** (try it at phone width too) |
| 6 | Ticket inbox | Log in as `support@`, go to **Tickets** |
| 7 | Segment builder | Log in as `marketing@`, go to **Segments** and pick a segment |
| 8 | Campaign results | **Campaigns**, then *Corporate travel desk launch* |
| 9 | Ad-hoc report | **Report builder**, then "Revenue by destination" or "Why we lose deals" |
| 10 | Framework page | **About the system** |

Tip: the theme toggle in the top bar switches light, dark and system themes, and every screen works at 375px wide.

---

## 4. Security, governance and privacy

Mapped to **Murray's criteria**:

| Criterion | Implementation |
|---|---|
| **Security** | Role-based access control (spatie/laravel-permission) with a single permission matrix (`app/Support/Permissions.php`). The sidebar hides modules a role cannot use. Row-level visibility means consultants see only their own customers, enquiries and bookings (`VisibleToUser` scope + policies). Passport numbers are **encrypted at rest** (`encrypted` cast). CSRF protection covers every form and Livewire request, and login is **rate limited** (5 attempts). Deactivated staff cannot sign in, and the public feedback form uses **signed, expiring URLs** |
| **Accountability** | spatie/laravel-activitylog records who changed what and when on customers, enquiries, quotes, bookings, tickets, campaigns, suppliers and users. The **Audit trail** screen filters by user or record type. Consent changes are timestamped, and every passport reveal and data export is logged |
| **Backup** | `php artisan crm:backup` runs nightly at 02:00 via the scheduler. An owner-only **Backups** screen lists and downloads the backups |
| **Ease of use** | Design-thinking personas (Achieng, David, Grace); Ctrl/⌘+K command palette; slide-over panels; toasts with undo; teaching empty states; progressive disclosure ("More details"); dark mode; WCAG AA contrast; status always shown as colour plus icon plus text; keyboard focus rings; mobile layouts |
| **Privacy** | Marketing consent is captured with a date and enforced in code. **Subject access:** a JSON export of everything held about a customer. **Erasure:** soft-delete then anonymise, keeping financial records for tax and audit |

### Privacy law note

The prototype is designed around two data protection regimes relevant to the agency's customers:

- **Kenya Data Protection Act, 2019:** the lawful processing and consent principles (s.25, s.30, s.32); the right of access (s.26), available as a one-click JSON export; the right to rectification and erasure (s.40), available as anonymise; direct marketing only with consent (s.37), enforced in `CampaignService`; and security safeguards (s.41), covered by encryption, RBAC and audit logs.
- **Australian Privacy Principles** (Privacy Act 1988), relevant to Australian clients: APP 7 (direct marketing and opt-in consent), APP 11 (security of personal information), APP 12 (access) and APP 13 (correction).

This is a teaching prototype, not legal advice. A production deployment would add a published privacy notice, retention schedules, breach-notification procedures and registration with the Office of the Data Protection Commissioner (ODPC).

---

## 5. How it was built (implementation methodology)

1. Scaffold Laravel, Breeze (Livewire), Tailwind, the spatie packages, the layout shell, sidebar, dark mode and command palette
2. Migrations, enums, models, relationships, factories and seeders
3. Customer 360
4. Pipeline, quotes and bookings
5. Tasks and "My Day"
6. Service tickets and feedback
7. Segments and campaigns
8. Suppliers
9. Dashboards and reports: real-time, scheduled and ad-hoc
10. Admin, governance, backup and privacy tools
11. The `/about-system` framework page
12. Tests, README, and a UX pass (empty states, loading states, mobile and dark-mode checks)

### Code tour

- `app/Enums`: backed enums for every status and type, each with a label, colour and icon
- `app/Services`: business logic (`LifecycleService`, `QuoteService`, `PipelineService`, `SegmentService`, `CampaignService`, `ReportService`, `TaskAutomationService`, `TicketService`, `PrivacyService`, `BackupService`, `BookingService`, `CustomerService`)
- `app/Policies`: a policy per main model, with row-level rules
- `resources/views/livewire/pages`: Volt page components, kept thin
- `database/seeders/DemoDataSeeder.php`: realistic data from a fixed random seed, generated relative to today so dashboards always look current (150 customers, 222 enquiries, ~90–110 bookings, 500+ interactions, 25 tickets, 60 feedback entries, 20 suppliers, 5 campaigns)
- `tests/Feature`: Pest tests for lifecycle promotion, quote-to-booking conversion, campaign consent, role access, segment rules, the scheduled report command, privacy and backup, UI flows, and a smoke test of every screen for every role
- `Model::preventLazyLoading()` is on outside production, so N+1 queries fail loudly in development and tests

---

## 6. Known limitations and future enhancements

- **Messaging is simulated.** Email uses the log driver; SMS and WhatsApp sends and opens are simulated. *Next:* the **WhatsApp Business API** (Meta Cloud API) for two-way chat logged to the timeline, plus an SMS gateway such as Africa's Talking.
- **Payments are recorded manually.** *Next:* **M-Pesa Daraja** STK Push and C2B callbacks to reconcile payments automatically.
- **Supplier rates are typed in.** *Next:* a **GDS / airline API** integration (e.g. Amadeus, Sabre or NDC) for live fares and availability.
- **No AI yet.** *Next:* **AI next-best-offer** that suggests destinations from preferences and history; lead scoring; sentiment analysis of feedback comments.
- Currency conversion uses fixed indicative rates. There is a single-branch, single-tenant setup and no customer self-service portal. Pages are English only.
- Automated browser tests are limited to smoke and flow checks; accessibility has been checked manually rather than through a full audit.

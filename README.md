# HRMS — Human Resource & Payroll Management

A complete HR system for Malaysian companies: employees, attendance, leave, payroll with EPF / SOCSO / EIS / PCB,
recruitment, performance, training, assets, meetings, documents and contracts — in one web app.

**Live demo:** <https://ui.staging.oriclabdev.com/hrms>

| Role     | Email                | Password   |
| -------- | -------------------- | ---------- |
| Company  | company@example.com  | `Zx123456` |
| HR       | hr@example.com       | `Zx123456` |
| Employee | employee@example.com | `Zx123456` |

Built with Laravel 13, Inertia 3, React 19, Tailwind CSS 4 and shadcn/ui.

---

## Features

### Made for Malaysia

- Amounts in Ringgit (RM), dates as dd/mm/yyyy, Asia/Kuala_Lumpur time zone, weeks starting Monday.
- Statutory payroll components: **EPF (KWSP)**, **SOCSO (PERKESO)**, **EIS (SIP)** and **PCB** monthly tax deduction.
- **MyKad (NRIC)** numbers for citizens and PRs — validated and formatted as `YYMMDD-PB-####` — or passport numbers for foreign staff.
- Branch-specific public holidays, Malaysian addresses and postcodes, LHDN income tax numbers.
- Four interface languages: **English, Bahasa Melayu, 中文 and العربية** (with right-to-left layout).

### Dashboard & calendar

- Separate dashboards for company, HR and employees: payroll this month, headcount, branches, attendance rate,
  pending leave, open jobs, today's birthdays and who is on leave.
- Employee clock-in / clock-out from their dashboard.
- Company calendar with holidays, approved leave, meetings and birthdays.
- Quick link to the public career page.

### Organisation structure

- Branches, departments (per branch) and designations (per department).
- Organization chart built from each employee's manager.
- Holidays per branch, with a calendar view.
- Announcements — company-wide or targeted to branches/departments, featured and high-priority flags,
  attachments, and read statistics per audience.
- Award types and awards; document types.

### Employees

- Five-step **Create Employee wizard** — Personal, Employment, Contact, Banking, Documents — validated
  step by step on the server (Laravel Precognition).
- Profile photo, MyKad / passport, emergency contact, bank account and tax number.
- Uploaded identity, address and education documents per document type, required types enforced.
- Employee profile page with tabs for employment, contact, banking, certifications and documents.
- CSV import (with a template and row-by-row error report) and CSV export.
- List and grid views, filters by branch/department/designation, status tabs.

### Attendance

- Monthly attendance grid (present, absent, half day, leave, holiday), editable per day.
- Shifts with working hours and breaks; employee shift assignment.
- Attendance policies (grace period, overtime rules).
- Attendance regularization requests with approval.
- Biometric attendance import and time entries.
- CSV import/export of attendance records.

### Leave

- Leave types and leave policies (entitlement, accrual, carry forward).
- Leave applications counting only working days that aren't holidays, with approve/reject.
- Leave balances per employee and a leave calendar.

### Recruitment

- Job postings, categories, types and locations; public **career page** with online applications.
- Candidates, candidate sources, custom application questions.
- Interview types, rounds, scheduling and interviewer feedback; candidate assessments.
- Offer letters generated from offer templates with placeholders.
- Candidate onboarding with checklists and checklist items.

### Employee lifecycle

- Promotions and transfers (with approval and supporting documents).
- Warnings with improvement plans and employee acknowledgment.
- Complaints (including anonymous) with assignment and follow-up.
- Business trips with advances and expenses.
- Resignations and terminations, with exit interviews.

### Performance

- Goal types and employee goals with progress tracking.
- Performance indicator categories and indicators.
- Review cycles, scheduled employee reviews and a conduct-review page rating each indicator.

### Training

- Training types, programs and sessions.
- Employee enrolment, attendance, results, certifications and assessments.

### Payroll

- Salary components — earnings or deductions, fixed or percentage of basic, lockable.
- Employee salaries with a **payroll calculation** page (earnings, deductions, attendance summary).
- Monthly payroll runs: process, review and complete; payslips per employee, browsable by month.

### Assets

- Asset types and assets; assign to and return from employees.
- Asset dashboard: status breakdown, assets by type, maintenance schedule, 12-month value trend,
  depreciation summary and recent assets.
- Straight-line depreciation report with export.

### Meetings

- Day agenda with a month calendar and per-day summary.
- Meeting types, rooms, attendees and status changes.
- Meeting minutes and action items with progress updates.

### Documents & contracts

- HR documents in categories (with icons, mandatory/optional), versions, expiry and download counts.
- Document acknowledgments — assign documents to employees and track who has read them.
- Document templates and contract templates with placeholders, preview for any employee and PDF download.
- Contract types and employee contracts (draft, pending approval, active, expired, terminated, renewed).

### Website & system

- Public landing page (editable sections), custom pages and newsletter subscribers.
- Contact messages with replies.
- Media library.
- Users, roles and fine-grained permissions (every page and action).
- Login history.
- Settings: brand (title, footer, theme colour), system (language, date/time format, time zone,
  working days), currency, email server and editable email templates.
- In-app **User Manual**.
- Light and dark mode.

---

## Getting started

Requirements: PHP 8.4, Composer, Node 22, MySQL 8 (or SQLite).

```bash
git clone https://github.com/pasupathy-manikam-jr/hrms.git
cd hrms
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Sign in with one of the demo accounts above. `php artisan migrate --seed` loads a full set of Malaysian sample data.

## Checks

```bash
composer ci:check   # lint, formatting, TypeScript, PHPStan and the test suite
```

## Deployment

Servers never run Node. On every push to `main`, GitHub Actions builds the front-end and publishes a `deploy`
branch (`main` + compiled assets); the server then runs:

```bash
cd ~/hrms && bash scripts/deploy.sh
```

# Plan — Salary & Benefits Benchmark: upload & analytics

Source workbook: **Malaysia Salary & Benefits Benchmark Survey 2025/2026** (MY HR Outsource Services with
Malaysia HR Forum). Samples reviewed: Client A (manufacturing, 200-499 staff, 18 roles), Client B (IT, 50-99, 15
roles), Client C (logistics MNC, 500-999, 15 roles).

## 1. How it will work

There are **no online survey forms**. Participating companies fill in the Excel workbook as today and send it in.
The **admin uploads the completed workbooks** into HRMS; the system reads, checks and stores them, and turns them
into **analytics the admin can slice by any category** (industry, state, company size, job family, level…) and
export as reports.

```
Completed .xlsx files ──► Upload (one or many) ──► Validate & preview ──► Accept
                                                                           │
                          Reports & exports ◄── Analytics explorer ◄── Survey database
```

## 2. What each workbook contains (identical layout in all samples)

| Sheet                          | Read as                                                                                                                                                                                                                                       |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Company Profile (B3:B11)    | One **participant**: name, industry, HQ state, headcount band, revenue band, ownership, locations, listed status, union                                                                                                                       |
| 2. Salary Data (rows 6-64)     | One **salary row** per standard job title + level: headcount (total/male/female), tenure buckets, experience required, shift-based, min/max/median/avg male/avg female base, guaranteed bonus months, 8 allowance types + "other" description |
| 3. Benefits (rows 5-30)        | One **benefit answer** per item: "same for all levels?", company-wide value, or Non-Exec/Executive and Managerial & Above values, and remarks                                                                                                 |
| 4. Attrition & Hiring (B3:B23) | One **attrition record**: attrition %, new-hire attrition %, retirements, terminations, non-renewals, hardest-to-hire/retain ranks, time-to-fill, headcount plan, reasons for leaving, retrenchment                                           |
| 5. Consent (B5:B8)             | Consent Yes/No, authorised name, designation, date                                                                                                                                                                                            |
| Job Catalogue / Lookups        | Reference data: 420 standard job titles (code, industry, family, level, MASCO), and the 15 dropdown lists                                                                                                                                     |

## 3. Upload & validation

- **Upload** one or many `.xlsx` files at once (drag and drop), tagged to a **survey cycle** (e.g. 2025/2026).
- Each file is read by cell position and checked; the admin sees a **preview per file**: company, industry,
  size, number of roles, and a list of problems.
- **Errors** (file rejected until fixed): not the survey template, consent ≠ Yes, missing company
  name/industry/state/size, a salary row with an unknown job title or level, male + female ≠ total, tenure
  buckets ≠ total, min > median or median > max, negative numbers, a dropdown answer that isn't in the list.
- **Warnings** (accepted, flagged): salary outliers (e.g. median over 3× the market median for that role),
  attrition over 100%, remarks left as the template's placeholder text (cleaned automatically), missing
  optional answers.
- Patterns seen in the samples and handled:
    - The template's **"Ex." example row** is always present in row 5 — skipped.
    - **Single-incumbent roles** have min = max = median filled (the FAQ says to leave min/max blank) — treated as
      one data point, not as a salary range.
    - **Allowances entered as 0** mean "not paid"; blanks mean "not answered".
    - Auto columns (averages, checks, totals) are **recalculated** by the system, not trusted from the file.
    - Company names are normalised; **re-uploading the same company in the same cycle replaces** its previous
      submission (with a confirmation), so corrections are easy.
- The original file is kept (private storage) and can be downloaded again from the participant's page.

## 4. Analytics explorer — slice and dice

One analytics page with **filters that apply to every chart and table**:

| Filter                                      | Values                                        |
| ------------------------------------------- | --------------------------------------------- |
| Survey cycle                                | 2025/2026 (and later years, for year-on-year) |
| Industry                                    | 26 industries                                 |
| State (HQ)                                  | 16 states / territories                       |
| Company size                                | 1-49 … 1,000+                                 |
| Revenue band, ownership, listed, unionised  | From the profile                              |
| Job family / standard job title / job level | 36 families · 420 titles · 5 levels           |
| Gender                                      | All / Male / Female (salary views)            |

Report views (tabs):

1. **Salary benchmarks** — per job title and level: number of companies, incumbents, base salary **P25 /
   median / P75 / average**, min–max range, guaranteed bonus (months), average allowances and **total cash**
   (base + allowances + bonus ÷ 12). Choice of _company-weighted_ (each company counts once) or
   _incumbent-weighted_ (weighted by headcount).
2. **Gender pay** — average male vs female base per role/level/industry, pay gap %, gender mix.
3. **Allowances** — prevalence (% of companies paying) and average amount for each of the 8 types; "other"
   allowance descriptions listed.
4. **Benefits** — % of companies offering each benefit, typical values (median annual leave, medical leave,
   paternity days, EPF employer rate, insurance limits, dental/optical amounts) by tier; variable bonus payout
   and 2027 target bonus %; incentive and ESS prevalence; flexible work.
5. **Attrition & hiring** — median voluntary and new-hire attrition, retirements/terminations/non-renewals per
   100 staff, time-to-fill; **hardest functions to hire and retain** (ranked, weighted 3-2-1); headcount plans
   (growing/stable/reducing %); % citing pay as a reason; retrenchment rate, drivers and above-statutory %.
6. **Workforce profile** — tenure distribution, experience required to hire, share of shift-based roles.
7. **Participants** — list of uploaded companies with profile, roles, status and warnings.

Every chart has its table beside it, and "compare to" lets the admin put two cuts side by side
(e.g. Manufacturing vs Technology / IT, or Selangor vs Penang).

**Confidentiality rule:** a statistic is only shown when at least **3 companies** contribute (a common survey
rule; adjustable). Otherwise the cell shows "insufficient data", so no company's pay can be identified.
Individual participants' figures are only visible on their own participant page, to the admin.

## 5. Exports

- **Excel** (per view, with the current filters) — for the admin's own report writing.
- **PDF report** — cover, methodology (sample size, cuts, confidentiality rule), and the selected views with
  charts; branded with Settings → Brand.
- **Participant feedback sheet** (later) — per company: their pay per role against the market median.

## 6. Data model

| Table                 | Purpose                                                                                  |
| --------------------- | ---------------------------------------------------------------------------------------- |
| `survey_cycles`       | Name (2025/2026), status (open/closed), confidentiality threshold                        |
| `survey_participants` | One per company per cycle: profile fields, consent details, original file path, warnings |
| `survey_salary_rows`  | One per participant + job title + level: all Salary Data columns                         |
| `survey_benefits`     | One per participant + benefit item: same-for-all, company value, tier values, remarks    |
| `survey_attrition`    | One per participant: all Attrition & Hiring answers                                      |
| `benchmark_jobs`      | The 420-job catalogue                                                                    |
| Lookup lists          | PHP enums/constants for industries, states, bands, levels, families, etc.                |

Analytics are computed with SQL on these tables (percentiles in PHP over the filtered rows), and cached per
cycle + filter set — small data (a few hundred companies × ~20 roles).

## 7. Screens & navigation

A new sidebar group **"Benchmark Survey"** (admin/Company role only):

- **Cycles** — create a cycle, set the confidentiality threshold, close a cycle.
- **Uploads** — drop files, see validation results, accept or reject.
- **Participants** — table of companies with filters; each opens a read-only view of the whole submission and
  its original file.
- **Analytics** — the explorer in §4.
- **Job Catalogue** — searchable reference of the 420 standard titles.

## 8. Delivery steps

1. Catalogue & lookups: `benchmark_jobs` + lists (extracted from the workbook), Job Catalogue page.
2. Cycles, participants and survey tables; permissions (`manage-benchmark-survey`), sidebar group.
3. Workbook reader + validator (errors/warnings) with tests on the three samples (A, B, C must import cleanly).
4. Upload screen with preview, accept/replace.
5. Participants list and participant detail page.
6. Analytics explorer: filters + salary benchmarks first, then gender pay, allowances, benefits, attrition,
   workforce.
7. Confidentiality threshold, caching.
8. Excel and PDF exports.
9. Seed demo data (the three samples plus generated participants so analytics show real cuts), translations,
   User Manual chapter, deploy to staging.

## 9. Decisions needed

1. **Package:** reading `.xlsx` needs a PHP library — `phpoffice/phpspreadsheet` (also used for Excel export).
   OK to add? PDF export would need a second one (e.g. `barryvdh/laravel-dompdf`) — OK?
2. **Confidentiality threshold:** minimum 3 companies per statistic (common) — or 5?
3. **Where it lives:** inside this HRMS as a new module (recommended, reuses users/roles/settings), or a
   separate app?
4. **Public repo:** the organisers' template, catalogue and the sample client files — keep them out of the
   public GitHub repo (upload privately on the server)? The three samples look fictional ("Client A/B/C") —
   OK to use them as seed/test data?
5. **Weighting default** for salary statistics: company-weighted or incumbent-weighted?

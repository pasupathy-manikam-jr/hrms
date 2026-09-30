import { Head } from '@inertiajs/react';
import {
    Banknote,
    BookOpen,
    Boxes,
    Building2,
    CalendarCheck,
    CalendarDays,
    ChartNoAxesColumn,
    Clock,
    FileText,
    LifeBuoy,
    Rocket,
    Settings,
    ChartBar,
    UserCog,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, userManual } from '@/routes';

/** A topic's explanation, optionally followed by numbered steps. */
type Topic = { title: string; body: string; steps?: string[] };

type Chapter = {
    id: string;
    title: string;
    icon: LucideIcon;
    intro: string;
    topics: Topic[];
};

/**
 * The HRMS user guide. Everyone who can sign in may read it; each chapter notes which role usually
 * does the work, and menus a user lacks permission for are simply hidden from their sidebar.
 */
const CHAPTERS: Chapter[] = [
    {
        id: 'getting-started',
        title: 'Getting Started',
        icon: Rocket,
        intro: 'How to sign in, find your way around and set up your own account.',
        topics: [
            {
                title: 'Signing in',
                body: 'Open the HRMS address in your browser and sign in with the email and password your HR team gave you. Use “Forgot password?” on the sign-in page to set a new password by email.',
            },
            {
                title: 'Roles',
                body: 'There are three standard roles. Company (administrator) manages everything, including settings. HR manages employees, attendance, leave, payroll and recruitment. Employee sees their own profile, attendance, leave, payslips, trainings and documents. Menus you are not allowed to use do not appear in your sidebar.',
            },
            {
                title: 'Finding your way',
                body: 'The sidebar groups every module. Only one group stays open at a time, and “Search menu…” at the top of the sidebar finds any page by name. The dashboard shows today’s attendance, leave, birthdays, open jobs and payroll at a glance.',
            },
            {
                title: 'Language and appearance',
                body: 'Use the language button at the top of every page to switch between English, Bahasa Melayu, 中文 and العربية. The moon/sun button switches between light and dark mode. Your choice is remembered for next time.',
            },
            {
                title: 'Your profile',
                body: 'Open the menu under your name (top right) to update your profile photo, name, email and password, and to turn on two-factor authentication.',
            },
        ],
    },
    {
        id: 'organisation',
        title: 'Organisation Structure',
        icon: Building2,
        intro: 'Set up the company before adding people. Usually done by the Company or HR role.',
        topics: [
            {
                title: 'Branches, departments and designations',
                body: 'Create your branches first (for example Kuala Lumpur HQ, Penang, Johor Bahru), then the departments inside each branch, then the designations (job titles) inside each department. Employees are always placed in a branch, department and designation.',
            },
            {
                title: 'Holidays',
                body: 'Organization Structure → Holidays lists public holidays per branch, since Malaysian state holidays differ. Holidays are skipped when working days and leave are counted. Switch to the calendar view to see the year at a glance.',
            },
            {
                title: 'Announcements',
                body: 'Post company-wide news or target specific branches and departments, mark it Featured or High Priority, and attach a file. Each announcement shows how many of its audience have opened it (Statistics).',
            },
            {
                title: 'Organization chart',
                body: 'Shows reporting lines automatically from each employee’s “Reports to” manager.',
            },
        ],
    },
    {
        id: 'employees',
        title: 'Employees',
        icon: Users,
        intro: 'Keep one record per person, with their job, pay details and documents.',
        topics: [
            {
                title: 'Adding an employee',
                body: 'Click “Add Employee” to open a five-step form: Personal (name, email, phone, date of birth, gender, MyKad or passport number and photo), Employment (branch, department, designation, shift, manager and joining date), Contact (address and emergency contact), Banking (bank account and income tax number) and Documents (identity proof, address proof, certificates). Use Next and Previous to move between steps; when you save, any problems are shown on the step where they are. A sign-in account is created for the employee at the same time.',
            },
            {
                title: 'Importing and exporting',
                body: 'Use Import to add many employees from a CSV file — download the template first so the columns match. Rows with problems are skipped and listed with the reason. Export downloads the current list, including any filters you applied.',
            },
            {
                title: 'Employee profile',
                body: 'Click the eye icon on any row to open the profile: personal and job details, salary, attendance, leave, documents, assets and history such as promotions and transfers.',
            },
        ],
    },
    {
        id: 'attendance',
        title: 'Attendance',
        icon: Clock,
        intro: 'Daily clock-in and clock-out, shifts and attendance rules.',
        topics: [
            {
                title: 'Clocking in and out',
                body: 'Employees clock in and out from the dashboard. Late arrivals, early departures and overtime are worked out from the employee’s shift.',
            },
            {
                title: 'Attendance records',
                body: 'The monthly grid shows every employee’s day (P present, A absent, HD half day, L on leave, H holiday). HR can click a day to add or correct a record, and import or export records as CSV.',
            },
            {
                title: 'Shifts, policies and regularizations',
                body: 'Shifts set working hours and breaks. Attendance Policies set grace periods and overtime rules. Employees who forgot to clock in submit an Attendance Regularization, which HR approves or rejects.',
            },
        ],
    },
    {
        id: 'leave',
        title: 'Leave',
        icon: CalendarCheck,
        intro: 'Leave types, entitlements and approvals.',
        topics: [
            {
                title: 'Leave types and policies',
                body: 'Create leave types such as Annual, Medical, Maternity or Emergency Leave, then Leave Policies that set how many days each type gives and whether unused days carry forward.',
            },
            {
                title: 'Applying for leave',
                body: 'Employees apply from Leave Applications by choosing the type and dates; only working days that are not holidays are counted. HR or the manager approves or rejects the application with a comment.',
            },
            {
                title: 'Balances and calendar',
                body: 'Leave Balances shows what each employee has taken and what remains. The Leave Calendar shows who is away on any day.',
            },
        ],
    },
    {
        id: 'recruitment',
        title: 'Recruitment',
        icon: UserPlus,
        intro: 'From job posting to a new employee’s first day.',
        topics: [
            {
                title: 'Job postings and the career page',
                body: 'Publish a job posting and it appears on your public Career Page (linked from the dashboard), where applicants can apply online. Job categories, types and locations keep postings organised.',
            },
            {
                title: 'Candidates and interviews',
                body: 'Every application becomes a candidate. Move candidates through the stages, schedule interviews by round, and collect interviewer feedback and assessments.',
            },
            {
                title: 'Offers and onboarding',
                body: 'Create an offer from an Offer Template — placeholders such as the candidate’s name, salary and start date are filled in for you. Once accepted, use Candidate Onboarding and checklists to prepare the new joiner.',
            },
        ],
    },
    {
        id: 'lifecycle',
        title: 'Employee Lifecycle',
        icon: UserCog,
        intro: 'Records of what happens to an employee during their time with the company.',
        topics: [
            {
                title: 'Awards, promotions and transfers',
                body: 'Record awards, promotions (with the new designation and salary change) and transfers between branches or departments. Approving a promotion or transfer updates the employee’s record.',
            },
            {
                title: 'Warnings, complaints and trips',
                body: 'Issue warnings (with an optional improvement plan and the employee’s acknowledgment), handle complaints, and manage business trips with advances and expenses.',
            },
            {
                title: 'Resignations and terminations',
                body: 'Record resignations and terminations with notice periods and supporting letters, and note the exit interview when it is done.',
            },
        ],
    },
    {
        id: 'performance',
        title: 'Performance & Training',
        icon: ChartNoAxesColumn,
        intro: 'Goals, reviews and staff development.',
        topics: [
            {
                title: 'Goals and reviews',
                body: 'Set employee goals and update their progress. Schedule employee reviews within a review cycle, then conduct the review by rating each performance indicator.',
            },
            {
                title: 'Training',
                body: 'Plan training programs and sessions, enrol employees, and record attendance, results and feedback. Training Types group programs by branch and department.',
            },
        ],
    },
    {
        id: 'payroll',
        title: 'Payroll',
        icon: Banknote,
        intro: 'Monthly pay in Ringgit, with Malaysian statutory deductions.',
        topics: [
            {
                title: 'Salary components',
                body: 'Components are earnings (Housing, Transport, Cost of Living allowances…) or deductions (EPF/KWSP, SOCSO/PERKESO, EIS/SIP, PCB). Each is a fixed amount or a percentage of basic salary, and can be switched off with the lock icon.',
            },
            {
                title: 'Employee salaries',
                body: 'Give each employee a basic salary and the components that apply to them. The chart icon opens the Payroll Calculation page with that month’s earnings, deductions and attendance summary.',
            },
            {
                title: 'Payroll runs and payslips',
                body: 'Create a payroll run for the month, process it to calculate every payslip, then complete it. Employees see their own payslips; Payslips shows one month at a time — pick the month at the top.',
            },
        ],
    },
    {
        id: 'assets',
        title: 'Assets',
        icon: Boxes,
        intro: 'Company equipment, who has it, and what it is worth.',
        topics: [
            {
                title: 'Assets and assignment',
                body: 'Register assets by type, assign them to employees and record their return. The Asset Dashboard shows status, maintenance schedules and recent activity.',
            },
            {
                title: 'Depreciation',
                body: 'Book value is worked out automatically from purchase cost, salvage value and useful life. The Depreciation Report can be filtered and exported.',
            },
        ],
    },
    {
        id: 'meetings',
        title: 'Meetings',
        icon: CalendarDays,
        intro: 'Schedule meetings and follow up on decisions.',
        topics: [
            {
                title: 'Scheduling',
                body: 'Pick a day on the calendar to see its agenda. Schedule a meeting with a type, room, organizer and attendees; the refresh icon changes its status (Scheduled, In Progress, Completed, Cancelled).',
            },
            {
                title: 'Minutes and action items',
                body: 'Record minutes during the meeting and create action items with an owner and due date. Use Update Progress to move an action item to In Progress or Completed.',
            },
        ],
    },
    {
        id: 'documents',
        title: 'Documents & Contracts',
        icon: FileText,
        intro: 'Company policies, letters and employment contracts.',
        topics: [
            {
                title: 'HR documents and acknowledgments',
                body: 'Upload policies and handbooks into categories. For documents that need acknowledgment, use Acknowledgments → Assign Document; employees then confirm they have read it, and you can see who is still outstanding.',
            },
            {
                title: 'Templates',
                body: 'Document and contract templates contain placeholders such as {{employee_name}} or {{start_date}}. Preview a template for any employee to see it filled in, or download it as a PDF.',
            },
            {
                title: 'Employee contracts',
                body: 'Create contracts per employee with dates and salary. The ⋯ menu lets you view, edit, update the status (Draft, Pending Approval, Active, Expired, Terminated, Renewed) or delete a contract.',
            },
        ],
    },
    {
        id: 'benchmark',
        title: 'Salary Benchmark Survey',
        icon: ChartBar,
        intro: 'Turn the Salary & Benefits Benchmark Survey workbooks that participating companies send in into pooled market data you can cut by industry, state, size, job and more. There is no online form: companies fill in the Excel workbook and you upload it. Menu: Benchmark Survey → Salary Benchmark (Company role only).',
        topics: [
            {
                title: '1. How it works',
                body: 'Every survey edition is a cycle. A cycle is built from the blank survey template, which supplies the standard job catalogue and every answer list. You then upload each company’s completed workbook into the cycle. Each company’s answers are stored separately and kept private; Analytics pools them into market figures.',
                steps: [
                    'Create the cycle and upload the blank template (once per edition).',
                    'Upload the completed workbooks as they arrive; fix and re-upload any that are rejected.',
                    'Open Analytics, choose your filters and read or export the results.',
                ],
            },
            {
                title: '2. Create a survey cycle',
                body: 'Go to Salary Benchmark → Survey Cycles and choose Add Cycle.',
                steps: [
                    'Name: the edition, for example 2025/2026.',
                    'Status: Open while you are collecting workbooks, Closed when the edition is final.',
                    'Min. Companies per Figure: the confidentiality rule (3 is the usual choice). A salary, percentage or median is hidden unless at least this many companies contribute to it.',
                    'Blank Survey Template: the unfilled .xlsx the companies were sent. The system reads its hidden Lookups sheet — the 420 standard job titles and every dropdown list — and shows the job count in the list.',
                    'Save. To use a corrected template later, edit the cycle and upload it again; the catalogue and lists are replaced.',
                ],
            },
            {
                title: '3. Upload completed workbooks',
                body: 'Go to Participants, check that the right cycle is selected in the Cycle filter, and choose Upload Workbooks. You can select one file or many at once (up to 50 files, 10 MB each). Each file gets a result:',
                steps: [
                    'Imported — the company has been added to the cycle.',
                    'Replaced — the company was already in the cycle and “Replace existing submissions” was ticked, so its previous data was overwritten.',
                    'Skipped — the company is already in the cycle. Tick “Replace existing submissions” and upload again if this file is a correction.',
                    'Rejected — the file has errors (listed under it) and nothing was saved. Ask the company to fix them, or fix them yourself, and upload again.',
                ],
            },
            {
                title: '4. What the upload checks',
                body: 'Companies are recognised by name, ignoring case and endings like Sdn Bhd, Berhad or (M), so “Client A Sdn Bhd” and “CLIENT A SDN. BHD.” are the same company. The template’s “Ex.” example row and its own formula columns are ignored; averages and totals are recalculated. A file is rejected when:',
                steps: [
                    'It is not the survey workbook, or consent in “5. Consent & Submission” is not “Yes”.',
                    'Company name, industry, state or number of employees is missing, or an answer is not one of the dropdown options.',
                    'A salary row uses a Standard Job Title that is not in the catalogue, or has no job level or median salary.',
                    'Male + Female does not equal Total Headcount, or the tenure columns do not add up to it.',
                    'Minimum salary is above the median, maximum is below it, or a number is negative or not a number.',
                    'The same job title and level appear twice.',
                ],
            },
            {
                title: '5. Warnings',
                body: 'Warnings do not stop a file; it is imported and the warning count shows in the Participants list and on the company’s page. Examples: an average male or female salary missing although there are staff of that gender, more than 6 months of guaranteed bonus, an attrition rate above 100%, the same job family ranked twice, or retrenchment “Yes” without a driver. Remarks left as the template’s grey guidance text are cleaned out automatically.',
            },
            {
                title: '6. Each company’s results',
                body: 'Participants lists every company in the cycle with its industry, state, size, number of roles and staff. Filter by industry, state or size, or search by name. The eye icon opens the company’s page:',
                steps: [
                    'Salary Data: each role’s median base pay next to the market median for the same job and level, and how far above or below the market it is. The market figure only appears once enough companies report that role.',
                    'Benefits, Attrition & Hiring and Company Profile: everything the company answered, plus any warnings.',
                    'Original Workbook downloads the file exactly as it was uploaded. Delete removes the company and its file from the cycle.',
                ],
            },
            {
                title: '7. Analytics — slice and dice',
                body: 'Analytics pools every company in the chosen cycle. Every filter applies to every view, so you can combine them freely (for example Manufacturing + Selangor + Executive level). Clear Filters resets everything except the cycle.',
                steps: [
                    'Company filters: industry, state, company size, revenue band, ownership, listed status, unionised workforce.',
                    'Job filters: job family, standard job title, job level.',
                    'Gender: shows average male or female pay instead of the median.',
                    'Weighting: company-weighted (default — each company counts once) or incumbent-weighted (larger employers count more, by headcount).',
                ],
            },
            {
                title: '8. The analytics views',
                body: 'The cards at the top show how many companies, roles and employees are in your cut, and the confidentiality rule. The tabs:',
                steps: [
                    'Salary Benchmarks: per job and level — companies, incumbents, P25, median, P75 and average base pay, average allowances, guaranteed bonus months and total monthly cash (median + allowances + bonus ÷ 12).',
                    'Gender Pay: average male and female pay by job level and job family, the pay gap (positive = women paid less) and the female share of staff.',
                    'Allowances: for each of the eight allowance types, the share of companies paying it and the average and median amount where paid, plus the “other” allowances described.',
                    'Benefits: the share of companies offering each benefit (overall and by tier) and median values such as leave days, EPF rate, medical limits and bonus months.',
                    'Attrition & Hiring: median attrition rates, exits per 100 staff, time to fill, the hardest job families to hire and retain (ranked 1st = 3 points, 2nd = 2, 3rd = 1), headcount plans, pay as a reason for leaving and retrenchment.',
                    'Workforce: tenure mix, experience required to hire, headcount by level and the share of shift-based roles.',
                    'Participants: how many companies are in each industry, state, size and ownership type.',
                ],
            },
            {
                title: '9. “Insufficient data”',
                body: 'When fewer companies than the cycle’s minimum contribute to a figure, it shows “Insufficient data” instead of a number, so no single company’s pay can be worked out. Narrow cuts hide more figures; widen the filters or collect more workbooks. You can change the minimum on the cycle, but keep it at 3 or more for anything shared outside your team.',
            },
            {
                title: '10. Exporting reports',
                body: 'Export Excel downloads the current cut as a workbook with one sheet per view. PDF Report downloads a landscape report with a cover page (sample size, confidentiality rule and weighting), the filters used, and every view as a table. Both follow the filters currently selected and apply the same confidentiality rule.',
            },
            {
                title: '11. Job Catalogue',
                body: 'Job Catalogue lists the standard job titles of the selected cycle with their code, job family, industry, typical level and MASCO reference. Use it to answer companies’ questions about which Standard Job Title to choose.',
            },
        ],
    },
    {
        id: 'settings',
        title: 'Settings',
        icon: Settings,
        intro: 'Company-wide options. Only the Company role can change these.',
        topics: [
            {
                title: 'Brand',
                body: 'Set the title shown in the sidebar and browser tab, the footer text and the theme colour.',
            },
            {
                title: 'System',
                body: 'Choose the default language, date format (for example 31/12/2026), time format, first day of the week, time zone (Asia/Kuala_Lumpur) and working days.',
            },
            {
                title: 'Currency and email',
                body: 'Set the default currency (RM) and how amounts are shown, and the mail server used to send notifications. Email Templates let you edit the wording of each notification.',
            },
            {
                title: 'Users and roles',
                body: 'Users lists everyone who can sign in. Roles control exactly which pages and actions each role may use.',
            },
        ],
    },
    {
        id: 'help',
        title: 'Tips & Help',
        icon: LifeBuoy,
        intro: 'Common questions.',
        topics: [
            {
                title: 'I can’t see a menu or button',
                body: 'Your role does not include that permission. Ask your administrator to update your role under Users → Roles.',
            },
            {
                title: 'Filtering and exporting lists',
                body: 'Every list has a search box, filters and status tabs; “Filters” holds extra options and “Clear Filters” resets them. Exports include whatever filters are applied.',
            },
            {
                title: 'Getting help',
                body: 'Contact your HR department or system administrator for account access, corrections to your records, or questions about your pay and leave.',
            },
        ],
    },
];

export default function UserManual() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('User Manual')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="User Manual"
                    description="A guide to every part of the HRMS, from setting up the company to running payroll."
                />

                <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <nav
                        aria-label={t('Contents')}
                        className="h-fit rounded-xl border bg-card p-4 shadow-sm lg:sticky lg:top-4"
                    >
                        <div className="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <BookOpen className="size-4" /> {t('Contents')}
                        </div>
                        <ol className="grid gap-0.5 text-sm">
                            {CHAPTERS.map(({ id, title, icon: Icon }) => (
                                <li key={id}>
                                    <a
                                        href={`#${id}`}
                                        className="flex items-center gap-2 rounded-md px-2 py-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    >
                                        <Icon className="size-4 shrink-0" />
                                        {t(title)}
                                    </a>
                                </li>
                            ))}
                        </ol>
                    </nav>

                    <div className="grid gap-6">
                        {CHAPTERS.map(
                            ({ id, title, icon: Icon, intro, topics }) => (
                                <section
                                    key={id}
                                    id={id}
                                    className="scroll-mt-4 rounded-xl border bg-card p-6 shadow-sm"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </span>
                                        <div>
                                            <h2 className="text-lg font-semibold">
                                                {t(title)}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {t(intro)}
                                            </p>
                                        </div>
                                    </div>
                                    <dl className="mt-5 grid gap-4">
                                        {topics.map((topic) => (
                                            <div key={topic.title}>
                                                <dt className="font-medium">
                                                    {t(topic.title)}
                                                </dt>
                                                <dd className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                                    {t(topic.body)}
                                                    {topic.steps && (
                                                        <ol className="mt-2 grid list-decimal gap-1 ps-5">
                                                            {topic.steps.map(
                                                                (step) => (
                                                                    <li
                                                                        key={
                                                                            step
                                                                        }
                                                                    >
                                                                        {t(
                                                                            step,
                                                                        )}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ol>
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

UserManual.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'User Manual', href: userManual() },
    ],
};

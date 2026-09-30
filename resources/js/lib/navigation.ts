import {
    BookOpen,
    LifeBuoy,
    Building2,
    Calendar,
    CalendarDays,
    ChartNoAxesColumn,
    Clock,
    Banknote,
    ChartBar,
    FileText,
    Globe,
    Image,
    LayoutGrid,
    Package,
    RefreshCw,
    Settings,
    UserPlus,
    Users,
} from 'lucide-react';
import { dashboard, mediaLibrary, settings, userManual } from '@/routes';
import benchmark from '@/routes/benchmark';
import calendar from '@/routes/calendar';
import career from '@/routes/career';
import contacts from '@/routes/contacts';
import currencies from '@/routes/currencies';
import hr from '@/routes/hr';
import landingPage from '@/routes/landing-page';
import meetings from '@/routes/meetings';
import newsletters from '@/routes/newsletters';
import roles from '@/routes/roles';
import users from '@/routes/users';
import type { NavSection } from '@/types';

// Mirrors the WorkDo HRM demo's company sidebar.
// Each entry carries the permission its route requires (see routes/hrm.php); NavMain hides the rest.
export const navigation: NavSection[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                permission: 'manage-dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Calendar',
                href: calendar.index(),
                permission: 'view-calendar',
                icon: CalendarDays,
            },
        ],
    },
    {
        title: 'Workforce Management',
        items: [
            {
                title: 'Employees',
                href: hr.employees.index(),
                permission: 'manage-employees',
                icon: Users,
            },
            {
                title: 'Organization Chart',
                href: hr.organizationChart.index(),
                permission: 'manage-organization-chart',
                icon: Building2,
            },
            {
                title: 'Organization Structure',
                icon: Building2,
                children: [
                    {
                        title: 'Branches',
                        href: hr.branches.index(),
                        permission: 'manage-branches',
                    },
                    {
                        title: 'Departments',
                        href: hr.departments.index(),
                        permission: 'manage-departments',
                    },
                    {
                        title: 'Designations',
                        href: hr.designations.index(),
                        permission: 'manage-designations',
                    },
                    {
                        title: 'Holidays',
                        href: hr.holidays.index(),
                        permission: 'manage-holidays',
                    },
                    {
                        title: 'Announcements',
                        href: hr.announcements.index(),
                        permission: 'manage-announcements',
                    },
                    {
                        title: 'Award Types',
                        href: hr.awardTypes.index(),
                        permission: 'manage-award-types',
                    },
                    {
                        title: 'Document Types',
                        href: hr.documentTypes.index(),
                        permission: 'manage-document-types',
                    },
                ],
            },
            {
                title: 'Attendance',
                icon: Clock,
                children: [
                    {
                        title: 'Attendance Records',
                        href: hr.attendanceRecords.index(),
                        permission: 'manage-attendance-records',
                    },
                    {
                        title: 'Timesheet',
                        href: hr.timeEntries.index(),
                        permission: 'manage-time-entries',
                    },
                    {
                        title: 'Biometric Attendance',
                        href: hr.biometricAttendance.index(),
                        permission: 'manage-biometric-attendance',
                    },
                    {
                        title: 'Attendance Regularizations',
                        href: hr.attendanceRegularizations.index(),
                        permission: 'manage-attendance-regularizations',
                    },
                    {
                        title: 'Shifts',
                        href: hr.shifts.index(),
                        permission: 'manage-shifts',
                    },
                    {
                        title: 'Attendance Policies',
                        href: hr.attendancePolicies.index(),
                        permission: 'manage-attendance-policies',
                    },
                ],
            },
            {
                title: 'Leave Management',
                icon: CalendarDays,
                children: [
                    {
                        title: 'Leave Applications',
                        href: hr.leaveApplications.index(),
                        permission: 'manage-leave-applications',
                    },
                    {
                        title: 'Leave Balances',
                        href: hr.leaveBalances.index(),
                        permission: 'manage-leave-balances',
                    },
                    {
                        title: 'Leave Types',
                        href: hr.leaveTypes.index(),
                        permission: 'manage-leave-types',
                    },
                    {
                        title: 'Leave Policies',
                        href: hr.leavePolicies.index(),
                        permission: 'manage-leave-policies',
                    },
                ],
            },
        ],
    },
    {
        title: 'Talent & Growth',
        items: [
            {
                title: 'Recruitment',
                icon: UserPlus,
                children: [
                    {
                        title: 'Job Postings',
                        href: hr.recruitment.jobPostings.index(),
                        permission: 'manage-job-postings',
                    },
                    {
                        title: 'Candidates',
                        href: hr.recruitment.candidates.index(),
                        permission: 'manage-candidates',
                    },
                    {
                        title: 'Interviews',
                        href: hr.recruitment.interviews.index(),
                        permission: 'manage-interviews',
                    },
                    {
                        title: 'Offers',
                        href: hr.recruitment.offers.index(),
                        permission: 'manage-offers',
                    },
                    {
                        title: 'Candidate Onboarding',
                        href: hr.recruitment.candidateOnboarding.index(),
                        permission: 'manage-candidate-onboarding',
                    },
                    {
                        title: 'Candidate Assessments',
                        href: hr.recruitment.candidateAssessments.index(),
                        permission: 'manage-candidate-assessments',
                    },
                    {
                        title: 'Onboarding Checklists',
                        href: hr.recruitment.onboardingChecklists.index(),
                        permission: 'manage-onboarding-checklists',
                    },
                    {
                        title: 'Checklist Items',
                        href: hr.recruitment.checklistItems.index(),
                        permission: 'manage-checklist-items',
                    },
                    {
                        title: 'Career',
                        href: career.index(),
                        permission: 'manage-career-page',
                        external: true,
                    },
                    {
                        title: 'Job Categories',
                        href: hr.recruitment.jobCategories.index(),
                        permission: 'manage-job-categories',
                    },
                    {
                        title: 'Job Types',
                        href: hr.recruitment.jobTypes.index(),
                        permission: 'manage-job-types',
                    },
                    {
                        title: 'Job Locations',
                        href: hr.recruitment.jobLocations.index(),
                        permission: 'manage-job-locations',
                    },
                    {
                        title: 'Candidate Sources',
                        href: hr.recruitment.candidateSources.index(),
                        permission: 'manage-candidate-sources',
                    },
                    {
                        title: 'Interview Types',
                        href: hr.recruitment.interviewTypes.index(),
                        permission: 'manage-interview-types',
                    },
                    {
                        title: 'Interview Rounds',
                        href: hr.recruitment.interviewRounds.index(),
                        permission: 'manage-interview-rounds',
                    },
                    {
                        title: 'Offer Templates',
                        href: hr.recruitment.offerTemplates.index(),
                        permission: 'manage-offer-templates',
                    },
                    {
                        title: 'Custom Questions',
                        href: hr.recruitment.customQuestions.index(),
                        permission: 'manage-custom-questions',
                    },
                ],
            },
            {
                title: 'Employee Lifecycle',
                icon: RefreshCw,
                children: [
                    {
                        title: 'Awards',
                        href: hr.awards.index(),
                        permission: 'manage-awards',
                    },
                    {
                        title: 'Promotions',
                        href: hr.promotions.index(),
                        permission: 'manage-promotions',
                    },
                    {
                        title: 'Transfers',
                        href: hr.transfers.index(),
                        permission: 'manage-employee-transfers',
                    },
                    {
                        title: 'Warnings',
                        href: hr.warnings.index(),
                        permission: 'manage-warnings',
                    },
                    {
                        title: 'Resignations',
                        href: hr.resignations.index(),
                        permission: 'manage-resignations',
                    },
                    {
                        title: 'Terminations',
                        href: hr.terminations.index(),
                        permission: 'manage-terminations',
                    },
                    {
                        title: 'Trips',
                        href: hr.trips.index(),
                        permission: 'manage-trips',
                    },
                    {
                        title: 'Complaints',
                        href: hr.complaints.index(),
                        permission: 'manage-complaints',
                    },
                ],
            },
            {
                title: 'Performance Management',
                icon: ChartNoAxesColumn,
                children: [
                    {
                        title: 'Employee Reviews',
                        href: hr.performance.employeeReviews.index(),
                        permission: 'manage-employee-reviews',
                    },
                    {
                        title: 'Employee Goals',
                        href: hr.performance.employeeGoals.index(),
                        permission: 'manage-employee-goals',
                    },
                    {
                        title: 'Review Cycles',
                        href: hr.performance.reviewCycles.index(),
                        permission: 'manage-review-cycles',
                    },
                    {
                        title: 'Indicators',
                        href: hr.performance.indicators.index(),
                        permission: 'manage-performance-indicators',
                    },
                    {
                        title: 'Goal Types',
                        href: hr.performance.goalTypes.index(),
                        permission: 'manage-goal-types',
                    },
                    {
                        title: 'Indicator Categories',
                        href: hr.performance.indicatorCategories.index(),
                        permission: 'manage-performance-indicator-categories',
                    },
                ],
            },
            {
                title: 'Training & Development',
                icon: BookOpen,
                children: [
                    {
                        title: 'Employee Trainings',
                        href: hr.employeeTrainings.index(),
                        permission: 'manage-employee-trainings',
                    },
                    {
                        title: 'Training Sessions',
                        href: hr.trainingSessions.index(),
                        permission: 'manage-training-sessions',
                    },
                    {
                        title: 'Training Programs',
                        href: hr.trainingPrograms.index(),
                        permission: 'manage-training-programs',
                    },
                    {
                        title: 'Training Assessments',
                        href: hr.trainingAssessments.index(),
                        permission: 'manage-training-assessments',
                    },
                    {
                        title: 'Training Types',
                        href: hr.trainingTypes.index(),
                        permission: 'manage-training-types',
                    },
                ],
            },
        ],
    },
    {
        title: 'Finance & Assets',
        items: [
            {
                title: 'Payroll Management',
                icon: Banknote,
                children: [
                    {
                        title: 'Payslips',
                        href: hr.payslips.index(),
                        permission: 'manage-payslips',
                    },
                    {
                        title: 'Payroll Runs',
                        href: hr.payrollRuns.index(),
                        permission: 'manage-payroll-runs',
                    },
                    {
                        title: 'Employee Salaries',
                        href: hr.employeeSalaries.index(),
                        permission: 'manage-employee-salaries',
                    },
                    {
                        title: 'Salary Components',
                        href: hr.salaryComponents.index(),
                        permission: 'manage-salary-components',
                    },
                ],
            },
            {
                title: 'Asset Management',
                icon: Package,
                children: [
                    {
                        title: 'Dashboard',
                        href: hr.assets.dashboard(),
                        permission: 'manage-assets',
                    },
                    {
                        title: 'Assets',
                        href: hr.assets.index(),
                        permission: 'manage-assets',
                    },
                    {
                        title: 'Depreciation',
                        href: hr.assets.depreciationReport(),
                        permission: 'manage-assets',
                    },
                    {
                        title: 'Asset Types',
                        href: hr.assetTypes.index(),
                        permission: 'manage-asset-types',
                    },
                ],
            },
        ],
    },
    {
        title: 'Benchmark Survey',
        items: [
            {
                title: 'Salary Benchmark',
                icon: ChartBar,
                children: [
                    {
                        title: 'Analytics',
                        href: benchmark.analytics.index(),
                        permission: 'manage-benchmark-survey',
                    },
                    {
                        title: 'Participants',
                        href: benchmark.participants.index(),
                        permission: 'manage-benchmark-survey',
                    },
                    {
                        title: 'Survey Cycles',
                        href: benchmark.cycles.index(),
                        permission: 'manage-benchmark-survey',
                    },
                    {
                        title: 'Job Catalogue',
                        href: benchmark.jobs.index(),
                        permission: 'manage-benchmark-survey',
                    },
                ],
            },
        ],
    },
    {
        title: 'Communications & Content',
        items: [
            {
                title: 'Meetings',
                icon: Calendar,
                children: [
                    {
                        title: 'Meetings',
                        href: meetings.meetings.index(),
                        permission: 'manage-meetings',
                    },
                    {
                        title: 'Action Items',
                        href: meetings.actionItems.index(),
                        permission: 'manage-action-items',
                    },
                    {
                        title: 'Meeting Types',
                        href: meetings.meetingTypes.index(),
                        permission: 'manage-meeting-types',
                    },
                    {
                        title: 'Meeting Rooms',
                        href: meetings.meetingRooms.index(),
                        permission: 'manage-meeting-rooms',
                    },
                ],
            },
            {
                title: 'Documents & Contracts',
                icon: FileText,
                children: [
                    {
                        title: 'HR Documents',
                        href: hr.documents.hrDocuments.index(),
                        permission: 'manage-hr-documents',
                    },
                    {
                        title: 'Employee Contracts',
                        href: hr.contracts.employeeContracts.index(),
                        permission: 'manage-employee-contracts',
                    },
                    {
                        title: 'Acknowledgments',
                        href: hr.documents.documentAcknowledgments.index(),
                        permission: 'manage-document-acknowledgments',
                    },
                    {
                        title: 'Contract Templates',
                        href: hr.contracts.contractTemplates.index(),
                        permission: 'manage-contract-templates',
                    },
                    {
                        title: 'Document Templates',
                        href: hr.documents.documentTemplates.index(),
                        permission: 'manage-document-templates',
                    },
                    {
                        title: 'Contract Types',
                        href: hr.contracts.contractTypes.index(),
                        permission: 'manage-contract-types',
                    },
                    {
                        title: 'Document Categories',
                        href: hr.documents.documentCategories.index(),
                        permission: 'manage-document-categories',
                    },
                ],
            },
            {
                title: 'Media Library',
                href: mediaLibrary(),
                permission: 'manage-media',
                icon: Image,
            },
        ],
    },
    {
        title: 'System Control',
        items: [
            {
                title: 'Landing Page',
                icon: Globe,
                children: [
                    {
                        title: 'Landing Page',
                        href: landingPage.settings(),
                        permission: 'manage-landing-page',
                    },
                    {
                        title: 'Custom Pages',
                        href: landingPage.customPages.index(),
                        permission: 'manage-landing-page',
                    },
                    {
                        title: 'Contact Inquiries',
                        href: contacts.index(),
                        permission: 'manage-contacts',
                    },
                    {
                        title: 'Newsletter',
                        href: newsletters.index(),
                        permission: 'manage-newsletters',
                    },
                ],
            },
            {
                title: 'System Users',
                icon: Users,
                children: [
                    {
                        title: 'Users',
                        href: users.index(),
                        permission: 'manage-users',
                    },
                    {
                        title: 'Roles',
                        href: roles.index(),
                        permission: 'manage-roles',
                    },
                ],
            },
            {
                title: 'Currency',
                href: currencies.index(),
                permission: 'manage-currencies',
                icon: Banknote,
            },
            {
                title: 'Settings',
                href: settings(),
                permission: 'manage-settings',
                icon: Settings,
            },
            {
                title: 'User Manual',
                href: userManual(),
                icon: LifeBuoy,
            },
        ],
    },
];

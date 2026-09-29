<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HRM module routes
|--------------------------------------------------------------------------
|
| URIs, names and required permissions mirror the WorkDo HRM demo. Every module starts on the
| shared "coming soon" page; replace an entry with its real controller
| as the module gets built.
|
*/

$modules = [
    'calendar.index' => ['calendar', 'Calendar', 'view-calendar'],

    'hr.employees.index' => ['hr/employees', 'Employees', 'manage-employees'],
    'hr.organization-chart.index' => ['hr/organization-chart', 'Organization Chart', 'manage-organization-chart'],
    'hr.branches.index' => ['hr/branches', 'Branches', 'manage-branches'],
    'hr.departments.index' => ['hr/departments', 'Departments', 'manage-departments'],
    'hr.designations.index' => ['hr/designations', 'Designations', 'manage-designations'],
    'hr.holidays.index' => ['hr/holidays', 'Holidays', 'manage-holidays'],
    'hr.announcements.index' => ['hr/announcements', 'Announcements', 'manage-announcements'],
    'hr.award-types.index' => ['hr/award-types', 'Award Types', 'manage-award-types'],
    'hr.document-types.index' => ['hr/document-types', 'Document Types', 'manage-document-types'],

    'hr.attendance-records.index' => ['hr/attendance-records', 'Attendance Records', 'manage-attendance-records'],
    'hr.time-entries.index' => ['hr/time-entries', 'Timesheet', 'manage-time-entries'],
    'hr.biometric-attendance.index' => ['hr/biometric-attendance', 'Biometric Attendance', 'manage-biometric-attendance'],
    'hr.attendance-regularizations.index' => ['hr/attendance-regularizations', 'Attendance Regularizations', 'manage-attendance-regularizations'],
    'hr.shifts.index' => ['hr/shifts', 'Shifts', 'manage-shifts'],
    'hr.attendance-policies.index' => ['hr/attendance-policies', 'Attendance Policies', 'manage-attendance-policies'],

    'hr.leave-applications.index' => ['hr/leave-applications', 'Leave Applications', 'manage-leave-applications'],
    'hr.leave-balances.index' => ['hr/leave-balances', 'Leave Balances', 'manage-leave-balances'],
    'hr.leave-types.index' => ['hr/leave-types', 'Leave Types', 'manage-leave-types'],
    'hr.leave-policies.index' => ['hr/leave-policies', 'Leave Policies', 'manage-leave-policies'],

    'hr.recruitment.job-postings.index' => ['hr/recruitment/job-postings', 'Job Postings', 'manage-job-postings'],
    'hr.recruitment.candidates.index' => ['hr/recruitment/candidates', 'Candidates', 'manage-candidates'],
    'hr.recruitment.interviews.index' => ['hr/recruitment/interviews', 'Interviews', 'manage-interviews'],
    'hr.recruitment.offers.index' => ['hr/recruitment/offers', 'Offers', 'manage-offers'],
    'hr.recruitment.candidate-onboarding.index' => ['hr/recruitment/candidate-onboarding', 'Candidate Onboarding', 'manage-candidate-onboarding'],
    'hr.recruitment.candidate-assessments.index' => ['hr/recruitment/candidate-assessments', 'Candidate Assessments', 'manage-candidate-assessments'],
    'hr.recruitment.onboarding-checklists.index' => ['hr/recruitment/onboarding-checklists', 'Onboarding Checklists', 'manage-onboarding-checklists'],
    'hr.recruitment.checklist-items.index' => ['hr/recruitment/checklist-items', 'Checklist Items', 'manage-checklist-items'],
    'hr.recruitment.job-categories.index' => ['hr/recruitment/job-categories', 'Job Categories', 'manage-job-categories'],
    'hr.recruitment.job-types.index' => ['hr/recruitment/job-types', 'Job Types', 'manage-job-types'],
    'hr.recruitment.job-locations.index' => ['hr/recruitment/job-locations', 'Job Locations', 'manage-job-locations'],
    'hr.recruitment.candidate-sources.index' => ['hr/recruitment/candidate-sources', 'Candidate Sources', 'manage-candidate-sources'],
    'hr.recruitment.interview-types.index' => ['hr/recruitment/interview-types', 'Interview Types', 'manage-interview-types'],
    'hr.recruitment.interview-rounds.index' => ['hr/recruitment/interview-rounds', 'Interview Rounds', 'manage-interview-rounds'],
    'hr.recruitment.offer-templates.index' => ['hr/recruitment/offer-templates', 'Offer Templates', 'manage-offer-templates'],
    'hr.recruitment.custom-questions.index' => ['hr/recruitment/custom-questions', 'Custom Questions', 'manage-custom-questions'],

    'hr.awards.index' => ['hr/awards', 'Awards', 'manage-awards'],
    'hr.promotions.index' => ['hr/promotions', 'Promotions', 'manage-promotions'],
    'hr.transfers.index' => ['hr/transfers', 'Transfers', 'manage-employee-transfers'],
    'hr.warnings.index' => ['hr/warnings', 'Warnings', 'manage-warnings'],
    'hr.resignations.index' => ['hr/resignations', 'Resignations', 'manage-resignations'],
    'hr.terminations.index' => ['hr/terminations', 'Terminations', 'manage-terminations'],
    'hr.trips.index' => ['hr/trips', 'Trips', 'manage-trips'],
    'hr.complaints.index' => ['hr/complaints', 'Complaints', 'manage-complaints'],

    'hr.performance.employee-reviews.index' => ['hr/performance/employee-reviews', 'Employee Reviews', 'manage-employee-reviews'],
    'hr.performance.employee-goals.index' => ['hr/performance/employee-goals', 'Employee Goals', 'manage-employee-goals'],
    'hr.performance.review-cycles.index' => ['hr/performance/review-cycles', 'Review Cycles', 'manage-review-cycles'],
    'hr.performance.indicators.index' => ['hr/performance/indicators', 'Indicators', 'manage-performance-indicators'],
    'hr.performance.goal-types.index' => ['hr/performance/goal-types', 'Goal Types', 'manage-goal-types'],
    'hr.performance.indicator-categories.index' => ['hr/performance/indicator-categories', 'Indicator Categories', 'manage-performance-indicator-categories'],

    'hr.employee-trainings.index' => ['hr/employee-trainings', 'Employee Trainings', 'manage-employee-trainings'],
    'hr.training-sessions.index' => ['hr/training-sessions', 'Training Sessions', 'manage-training-sessions'],
    'hr.training-programs.index' => ['hr/training-programs', 'Training Programs', 'manage-training-programs'],
    'hr.training-assessments.index' => ['hr/training-assessments', 'Training Assessments', 'manage-training-assessments'],
    'hr.training-types.index' => ['hr/training-types', 'Training Types', 'manage-training-types'],

    'hr.payslips.index' => ['hr/payslips', 'Payslips', 'manage-payslips'],
    'hr.payroll-runs.index' => ['hr/payroll-runs', 'Payroll Runs', 'manage-payroll-runs'],
    'hr.employee-salaries.index' => ['hr/employee-salaries', 'Employee Salaries', 'manage-employee-salaries'],
    'hr.salary-components.index' => ['hr/salary-components', 'Salary Components', 'manage-salary-components'],

    'hr.assets.dashboard' => ['hr/assets-dashboard', 'Asset Dashboard', 'manage-assets'],
    'hr.assets.index' => ['hr/assets', 'Assets', 'manage-assets'],
    'hr.assets.depreciation-report' => ['hr/assets-depreciation-report', 'Depreciation', 'manage-assets'],
    'hr.asset-types.index' => ['hr/asset-types', 'Asset Types', 'manage-asset-types'],

    'meetings.meetings.index' => ['meetings/meetings', 'Meetings', 'manage-meetings'],
    'meetings.action-items.index' => ['meetings/action-items', 'Action Items', 'manage-action-items'],
    'meetings.meeting-types.index' => ['meetings/meeting-types', 'Meeting Types', 'manage-meeting-types'],
    'meetings.meeting-rooms.index' => ['meetings/meeting-rooms', 'Meeting Rooms', 'manage-meeting-rooms'],

    'hr.documents.hr-documents.index' => ['hr/documents/hr-documents', 'HR Documents', 'manage-hr-documents'],
    'hr.contracts.employee-contracts.index' => ['hr/contracts/employee-contracts', 'Employee Contracts', 'manage-employee-contracts'],
    'hr.documents.document-acknowledgments.index' => ['hr/documents/document-acknowledgments', 'Acknowledgments', 'manage-document-acknowledgments'],
    'hr.contracts.contract-templates.index' => ['hr/contracts/contract-templates', 'Contract Templates', 'manage-contract-templates'],
    'hr.documents.document-templates.index' => ['hr/documents/document-templates', 'Document Templates', 'manage-document-templates'],
    'hr.contracts.contract-types.index' => ['hr/contracts/contract-types', 'Contract Types', 'manage-contract-types'],
    'hr.documents.document-categories.index' => ['hr/documents/document-categories', 'Document Categories', 'manage-document-categories'],

    'media-library' => ['media-library', 'Media Library', 'manage-media'],

    'landing-page.settings' => ['landing-page/settings', 'Landing Page', 'manage-landing-page'],
    'landing-page.custom-pages.index' => ['custom-pages', 'Custom Pages', 'manage-landing-page'],
    'contacts.index' => ['contacts', 'Contact Inquiries', 'manage-contacts'],
    'newsletters.index' => ['newsletters', 'Newsletter', 'manage-newsletters'],

    'users.index' => ['users', 'Users', 'manage-users'],
    'roles.index' => ['roles', 'Roles', 'manage-roles'],
    'currencies.index' => ['currencies', 'Currency', 'manage-currencies'],
];

// Built modules live in routes/modules/*.php and replace their placeholder below.
foreach (glob(__DIR__.'/modules/*.php') ?: [] as $file) {
    require $file;
}

$router = app('router');
$router->getRoutes()->refreshNameLookups();

Route::middleware(['auth', 'verified'])->group(function () use ($modules, $router) {
    foreach ($modules as $name => [$uri, $title, $permission]) {
        if ($router->has($name)) {
            continue;
        }

        Route::inertia($uri, 'coming-soon', ['title' => $title])
            ->middleware("permission:{$permission}")
            ->name($name);
    }
});

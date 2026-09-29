import { MessageSquare } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import interviewTypeRoutes from '@/routes/hr/recruitment/interview-types';
import type { Paginated, TableFilters } from '@/types';

export default function InterviewTypes({
    interviewTypes,
    filters,
}: {
    interviewTypes: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={interviewTypes}
            filters={filters}
            routes={interviewTypeRoutes}
            module="interview-types"
            title="Interview Types"
            description="Manage interview types used in your recruitment process."
            singular="Interview Type"
            nameLabel="Interview Type Name"
            namePlaceholder="e.g., Technical, HR, Panel, Phone Screening"
            icon={MessageSquare}
            iconClass="bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-300"
        />
    );
}

InterviewTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: interviewTypeRoutes.index() },
        { title: 'Interview Types', href: interviewTypeRoutes.index() },
    ],
};

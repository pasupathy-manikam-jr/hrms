import { Globe } from 'lucide-react';
import { LookupPage } from '@/components/lookup-page';
import type { LookupRecord } from '@/components/lookup-page';
import { dashboard } from '@/routes';
import candidateSourceRoutes from '@/routes/hr/recruitment/candidate-sources';
import type { Paginated, TableFilters } from '@/types';

export default function CandidateSources({
    candidateSources,
    filters,
}: {
    candidateSources: Paginated<LookupRecord>;
    filters: TableFilters;
}) {
    return (
        <LookupPage
            records={candidateSources}
            filters={filters}
            routes={candidateSourceRoutes}
            module="candidate-sources"
            title="Candidate Sources"
            description="Manage sources used to track where candidates come from."
            singular="Candidate Source"
            nameLabel="Source Name"
            namePlaceholder="e.g., LinkedIn, Referral, Job Board"
            icon={Globe}
            iconClass="bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"
        />
    );
}

CandidateSources.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: candidateSourceRoutes.index() },
        { title: 'Candidate Sources', href: candidateSourceRoutes.index() },
    ],
};

import { Head } from '@inertiajs/react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { FilterSelect } from '@/components/table-filters';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import jobRoutes from '@/routes/benchmark/jobs';
import type { Paginated, TableFilters } from '@/types';

type Job = {
    id: number;
    code: string;
    industry: string;
    job_family: string;
    title: string;
    typical_level: string | null;
    summary: string | null;
    masco_reference: string | null;
};

const options = (values: string[]) =>
    values.map((value) => ({ id: value, name: value }));

export default function BenchmarkJobs({
    jobs,
    filters,
    cycles,
    cycle,
    industries,
    families,
}: {
    jobs: Paginated<Job>;
    filters: TableFilters;
    cycles: { id: number; name: string }[];
    cycle: { id: number; name: string } | null;
    industries: string[];
    families: string[];
}) {
    const { t } = useTranslation();
    const url = jobRoutes.index();
    const current = cycle ? { ...filters, cycle: String(cycle.id) } : filters;

    const columns: Column<Job>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (j) => (
                <span className="rounded-md border border-blue-200 px-2 py-0.5 text-xs font-medium text-blue-700">
                    {j.code}
                </span>
            ),
        },
        {
            key: 'title',
            label: 'Standard Job Title',
            sortable: true,
            render: (j) => (
                <div className="max-w-md">
                    <div className="font-medium">{j.title}</div>
                    <div className="line-clamp-2 text-xs text-muted-foreground">
                        {j.summary}
                    </div>
                </div>
            ),
        },
        {
            key: 'job_family',
            label: 'Job Family',
            sortable: true,
            render: (j) => j.job_family,
        },
        {
            key: 'industry',
            label: 'Industry',
            sortable: true,
            render: (j) => j.industry,
        },
        {
            key: 'typical_level',
            label: 'Typical Level',
            render: (j) => j.typical_level,
        },
        {
            key: 'masco_reference',
            label: 'MASCO',
            render: (j) => j.masco_reference,
        },
    ];

    return (
        <>
            <Head title={t('Job Catalogue')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Job Catalogue"
                    description="The standard job titles companies map their roles to, from the cycle template."
                />
                <DataTable
                    data={jobs}
                    columns={columns}
                    filters={current}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={current}
                                name="cycle"
                                label="Cycle"
                                options={cycles}
                            />
                            <FilterSelect
                                url={url}
                                filters={current}
                                name="industry"
                                label="All Industries"
                                options={options(industries)}
                            />
                            <FilterSelect
                                url={url}
                                filters={current}
                                name="job_family"
                                label="All Job Families"
                                options={options(families)}
                            />
                        </>
                    }
                />
            </div>
        </>
    );
}

BenchmarkJobs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Benchmark Survey', href: jobRoutes.index() },
        { title: 'Job Catalogue', href: jobRoutes.index() },
    ],
};

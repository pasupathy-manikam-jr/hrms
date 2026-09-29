import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Clock,
    Filter,
    MapPin,
    Search,
    Star,
    Wallet,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CareerShell } from '@/components/career-shell';
import type { CareerCompany } from '@/components/career-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/select-field';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import career from '@/routes/career';
import type { Paginated } from '@/types';

type Option = { id: number; name: string };

export type CareerJob = {
    id: number;
    job_code: string;
    title: string;
    positions: number;
    min_salary: string | null;
    max_salary: string | null;
    is_featured: boolean;
    skills: string[] | null;
    job_type: Option | null;
    location: Option | null;
    branch: Option | null;
};

type Filters = {
    search?: string;
    location_id?: string;
    salary?: string;
    job_types?: string[];
    vacancies?: string[];
    sort?: string;
};

export function SalaryRange({ job }: { job: CareerJob }) {
    const { money } = useFormat();

    if (job.min_salary === null && job.max_salary === null) {
        return null;
    }

    return (
        <span className="tabular-nums">
            {[job.min_salary, job.max_salary]
                .filter((v) => v !== null)
                .map((v) => money(Number(v)))
                .join(' – ')}
        </span>
    );
}

export default function CareerIndex({
    jobPostings,
    jobTypes,
    locations,
    salaryRanges,
    vacancyRanges,
    company,
    filters,
}: {
    jobPostings: Paginated<CareerJob>;
    jobTypes: Option[];
    locations: Option[];
    salaryRanges: string[];
    vacancyRanges: string[];
    company: CareerCompany;
    filters: Filters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const first = useRef(true);

    const visit = (changes: Filters & { page?: number }) =>
        router.get(
            career.index.url(),
            Object.fromEntries(
                Object.entries({
                    ...filters,
                    page: undefined,
                    ...changes,
                }).filter(
                    ([, value]) =>
                        value !== undefined &&
                        value !== '' &&
                        !(Array.isArray(value) && value.length === 0),
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => visit({ search }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const toggle = (key: 'job_types' | 'vacancies', value: string) => {
        const current = filters[key] ?? [];

        visit({
            [key]: current.includes(value)
                ? current.filter((v) => v !== value)
                : [...current, value],
        });
    };

    const salaryLabel = (range: string) => {
        const [min, max] = range.replace('+', '').split('-').map(Number);

        return max
            ? `${money(min)} – ${money(max)}`
            : t(':amount+', { amount: money(min) });
    };

    return (
        <>
            <Head title={t('Careers')} />
            <CareerShell company={company}>
                <section className="bg-emerald-50/70 px-4 py-20 text-center dark:bg-emerald-950/30">
                    <span className="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200">
                        <Star className="size-4" />
                        {t('Join our growing team')}
                    </span>
                    <h1 className="mt-6 text-4xl font-semibold tracking-tight text-slate-700 sm:text-5xl dark:text-slate-100">
                        {t('Build Your Dream Career')}
                    </h1>
                    <p className="mx-auto mt-5 max-w-3xl text-lg text-slate-600 sm:text-2xl dark:text-slate-300">
                        {t(
                            'Discover exciting opportunities, grow with innovative projects, and make a meaningful impact in a collaborative environment',
                        )}
                    </p>
                </section>

                <section className="mx-auto max-w-6xl px-4 py-12">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <h2 className="text-2xl font-semibold">
                            {t(':count Available Jobs', {
                                count: jobPostings.total,
                            })}
                        </h2>
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                {t('Sort by')}:
                                <SelectField
                                    className="w-40"
                                    value={filters.sort ?? 'newest'}
                                    onChange={(e) =>
                                        visit({ sort: e.target.value })
                                    }
                                >
                                    <option value="newest">
                                        {t('Newest First')}
                                    </option>
                                    <option value="oldest">
                                        {t('Oldest First')}
                                    </option>
                                    <option value="salary">
                                        {t('Highest Salary')}
                                    </option>
                                </SelectField>
                            </label>
                            <div className="relative w-64">
                                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder={t('Search for jobs')}
                                    aria-label={t('Search for jobs')}
                                    className="bg-card ps-9"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid items-start gap-6 lg:grid-cols-[17rem_1fr]">
                        <aside className="grid gap-5 rounded-xl border bg-card p-6 shadow-sm">
                            <h3 className="flex items-center gap-2 text-lg font-semibold">
                                <Filter className="size-5 text-emerald-600" />
                                {t('Filter Jobs')}
                            </h3>
                            <label className="grid gap-2 text-sm font-medium">
                                {t('Location')}
                                <SelectField
                                    value={filters.location_id ?? ''}
                                    onChange={(e) =>
                                        visit({ location_id: e.target.value })
                                    }
                                >
                                    <option value="">
                                        {t('All Locations')}
                                    </option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </SelectField>
                            </label>
                            <label className="grid gap-2 text-sm font-medium">
                                {t('Salary Range')}
                                <SelectField
                                    value={filters.salary ?? ''}
                                    onChange={(e) =>
                                        visit({ salary: e.target.value })
                                    }
                                >
                                    <option value="">{t('All Ranges')}</option>
                                    {salaryRanges.map((r) => (
                                        <option key={r} value={r}>
                                            {salaryLabel(r)}
                                        </option>
                                    ))}
                                </SelectField>
                            </label>
                            <fieldset className="grid gap-2 text-sm">
                                <legend className="mb-2 font-medium">
                                    {t('Job Type')}
                                </legend>
                                {jobTypes.map((type) => (
                                    <label
                                        key={type.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={(
                                                filters.job_types ?? []
                                            ).includes(String(type.id))}
                                            onCheckedChange={() =>
                                                toggle(
                                                    'job_types',
                                                    String(type.id),
                                                )
                                            }
                                        />
                                        {type.name}
                                    </label>
                                ))}
                            </fieldset>
                            <fieldset className="grid gap-2 text-sm">
                                <legend className="mb-2 font-medium">
                                    {t('Vacancies')}
                                </legend>
                                {vacancyRanges.map((range) => (
                                    <label
                                        key={range}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={(
                                                filters.vacancies ?? []
                                            ).includes(range)}
                                            onCheckedChange={() =>
                                                toggle('vacancies', range)
                                            }
                                        />
                                        {range}
                                    </label>
                                ))}
                            </fieldset>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    router.get(career.index.url());
                                }}
                            >
                                {t('Reset Filters')}
                            </Button>
                        </aside>

                        <div className="grid gap-6">
                            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                {jobPostings.data.map((job) => (
                                    <article
                                        key={job.id}
                                        className="flex flex-col gap-3 rounded-xl border bg-card p-6 shadow-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge className="bg-blue-50 text-blue-700 hover:bg-blue-50">
                                                {t(':count Vacancies', {
                                                    count: job.positions,
                                                })}
                                            </Badge>
                                            {job.is_featured && (
                                                <Badge className="gap-1 bg-amber-50 text-amber-700 hover:bg-amber-50">
                                                    <Star className="size-3" />
                                                    {t('Featured')}
                                                </Badge>
                                            )}
                                        </div>
                                        <h3 className="text-lg font-semibold">
                                            {job.title}
                                        </h3>
                                        {job.skills &&
                                            job.skills.length > 0 && (
                                                <div className="flex flex-wrap gap-1.5">
                                                    {job.skills
                                                        .slice(0, 3)
                                                        .map((skill) => (
                                                            <Badge
                                                                key={skill}
                                                                variant="outline"
                                                            >
                                                                {skill}
                                                            </Badge>
                                                        ))}
                                                </div>
                                            )}
                                        <ul className="grid gap-1 text-xs text-muted-foreground">
                                            {job.branch && (
                                                <li className="flex items-center gap-2">
                                                    <Building2 className="size-3.5" />
                                                    {job.branch.name}
                                                </li>
                                            )}
                                            {job.location && (
                                                <li className="flex items-center gap-2">
                                                    <MapPin className="size-3.5" />
                                                    {job.location.name}
                                                </li>
                                            )}
                                            {job.job_type && (
                                                <li className="flex items-center gap-2">
                                                    <Clock className="size-3.5" />
                                                    {job.job_type.name}
                                                </li>
                                            )}
                                        </ul>
                                        <div className="flex items-center gap-2 text-sm">
                                            <Wallet className="size-4 text-amber-600" />
                                            <SalaryRange job={job} />
                                        </div>
                                        <Button asChild className="mt-auto">
                                            <Link
                                                href={career.show(job.job_code)}
                                            >
                                                {t('Browse Job')}
                                            </Link>
                                        </Button>
                                    </article>
                                ))}
                            </div>
                            {jobPostings.data.length === 0 && (
                                <div className="rounded-xl border bg-card p-12 text-center text-muted-foreground">
                                    {t('No jobs match your filters.')}
                                </div>
                            )}
                            {jobPostings.last_page > 1 && (
                                <nav
                                    aria-label={t('Pagination')}
                                    className="flex justify-center gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={jobPostings.current_page <= 1}
                                        onClick={() =>
                                            visit({
                                                page:
                                                    jobPostings.current_page -
                                                    1,
                                            })
                                        }
                                    >
                                        {t('Previous')}
                                    </Button>
                                    {Array.from(
                                        { length: jobPostings.last_page },
                                        (_, i) => i + 1,
                                    ).map((page) => (
                                        <Button
                                            key={page}
                                            size="sm"
                                            variant={
                                                page ===
                                                jobPostings.current_page
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() => visit({ page })}
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            jobPostings.current_page >=
                                            jobPostings.last_page
                                        }
                                        onClick={() =>
                                            visit({
                                                page:
                                                    jobPostings.current_page +
                                                    1,
                                            })
                                        }
                                    >
                                        {t('Next')}
                                    </Button>
                                </nav>
                            )}
                        </div>
                    </div>
                </section>
            </CareerShell>
        </>
    );
}

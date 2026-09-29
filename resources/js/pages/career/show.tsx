import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Clock,
    Banknote,
    MapPin,
    Star,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { CareerShell } from '@/components/career-shell';
import type { CareerCompany } from '@/components/career-shell';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import career from '@/routes/career';
import { SalaryRange } from './index';
import type { CareerJob } from './index';

type Option = { id: number; name: string };

type Job = CareerJob & {
    description: string | null;
    requirements: string | null;
    benefits: string | null;
    min_experience: string | null;
    max_experience: string | null;
    application_deadline: string | null;
    publish_date: string | null;
    priority: 'Low' | 'Medium' | 'High';
    status: string;
    department: Option | null;
};

type SimilarJob = Pick<
    CareerJob,
    'id' | 'job_code' | 'title' | 'positions' | 'location' | 'branch'
>;

const blank = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    gender: '',
    experience_years: '',
    current_company: '',
    current_position: '',
    expected_salary: '',
    linkedin_url: '',
    portfolio_url: '',
};

type Field = keyof typeof blank;

function Fact({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex items-start gap-3">
            <Icon className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div className="text-sm">
                <div className="font-medium">{t(label)}</div>
                <div className="text-muted-foreground">{children}</div>
            </div>
        </div>
    );
}

function Section({ title, text }: { title: string; text: string | null }) {
    const { t } = useTranslation();

    return (
        text && (
            <section className="rounded-xl border bg-card p-6 shadow-sm">
                <h2 className="mb-4 text-2xl font-semibold">{t(title)}</h2>
                <p className="leading-relaxed whitespace-pre-line">{text}</p>
            </section>
        )
    );
}

export default function CareerShow({
    job,
    similarJobs,
    company,
}: {
    job: Job;
    similarJobs: SimilarJob[];
    company: CareerCompany;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const [applying, setApplying] = useState(false);
    const form = useForm(blank);

    const field = (
        key: Field,
        label: string,
        props: React.ComponentProps<typeof Input> = {},
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`apply-${key}`}>
                {t(label)}
                {props.required && <span className="text-destructive">*</span>}
            </Label>
            <Input
                id={`apply-${key}`}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                {...props}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <>
            <Head title={job.title} />
            <CareerShell company={company}>
                <div className="mx-auto max-w-6xl px-4 py-10">
                    <Link
                        href={career.index()}
                        className="mb-6 inline-flex items-center gap-2 text-blue-600 hover:underline"
                    >
                        <ArrowLeft className="size-4" /> {t('Back to All Jobs')}
                    </Link>

                    <div className="grid items-start gap-6 lg:grid-cols-[1fr_24rem]">
                        <div className="grid gap-6">
                            <section className="rounded-xl border bg-card p-6 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                                            {job.title}
                                            {job.is_featured && (
                                                <Star
                                                    className="size-5 fill-amber-400 text-amber-400"
                                                    aria-label={t('Featured')}
                                                />
                                            )}
                                        </h1>
                                        {job.branch && (
                                            <p className="mt-1 text-lg text-muted-foreground">
                                                {job.branch.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        <StatusBadge status={job.status} />
                                        {job.priority === 'High' && (
                                            <Badge variant="destructive">
                                                {t('High Priority')}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <Fact icon={MapPin} label="Location">
                                        {job.location?.name ?? '—'}
                                    </Fact>
                                    <Fact icon={Building2} label="Department">
                                        {job.department?.name ?? '—'}
                                    </Fact>
                                    <Fact icon={Clock} label="Type">
                                        {job.job_type?.name ?? '—'}
                                    </Fact>
                                    <Fact icon={Users} label="Positions">
                                        {job.positions}
                                    </Fact>
                                    <Fact icon={Banknote} label="Salary Range">
                                        <SalaryRange job={job} />
                                    </Fact>
                                </div>
                                <div className="mt-6 flex justify-center">
                                    <Button onClick={() => setApplying(true)}>
                                        {t('Apply for this Position')}
                                    </Button>
                                </div>
                            </section>
                            <Section
                                title="Job Description"
                                text={job.description}
                            />
                            <Section
                                title="Requirements"
                                text={job.requirements}
                            />
                            <Section
                                title="Benefits & Perks"
                                text={job.benefits}
                            />
                            {job.skills && job.skills.length > 0 && (
                                <section className="rounded-xl border bg-card p-6 shadow-sm">
                                    <h2 className="mb-4 text-2xl font-semibold">
                                        {t('Required Skills')}
                                    </h2>
                                    <div className="flex flex-wrap gap-2">
                                        {job.skills.map((skill) => (
                                            <Badge
                                                key={skill}
                                                variant="secondary"
                                            >
                                                {skill}
                                            </Badge>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>

                        <aside className="grid gap-6">
                            <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                                <h2 className="text-lg font-semibold">
                                    {t('Quick Apply')}
                                </h2>
                                <Button
                                    className="w-full"
                                    onClick={() => setApplying(true)}
                                >
                                    {t('Apply Now')}
                                </Button>
                                <p className="text-center text-sm text-muted-foreground">
                                    {t('Application takes less than 5 minutes')}
                                </p>
                            </section>
                            <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                                <h2 className="text-lg font-semibold">
                                    {t('Job Information')}
                                </h2>
                                {job.publish_date && (
                                    <Fact
                                        icon={CalendarDays}
                                        label="Posted Date"
                                    >
                                        {date(job.publish_date)}
                                    </Fact>
                                )}
                                {job.application_deadline && (
                                    <Fact
                                        icon={CalendarDays}
                                        label="Application Deadline"
                                    >
                                        <span className="text-red-600">
                                            {date(job.application_deadline)}
                                        </span>
                                    </Fact>
                                )}
                                {job.min_experience !== null && (
                                    <Fact
                                        icon={Building2}
                                        label="Experience Required"
                                    >
                                        {t(':min - :max years', {
                                            min: job.min_experience,
                                            max: job.max_experience ?? '',
                                        })}
                                    </Fact>
                                )}
                            </section>
                            {similarJobs.length > 0 && (
                                <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-sm">
                                    <h2 className="text-lg font-semibold">
                                        {t('Similar Jobs')}
                                    </h2>
                                    {similarJobs.map((similar) => (
                                        <Link
                                            key={similar.id}
                                            href={career.show(similar.job_code)}
                                            className="grid gap-2 rounded-lg border p-4 hover:bg-muted/50"
                                        >
                                            <span className="font-semibold">
                                                {similar.title}
                                            </span>
                                            {similar.branch && (
                                                <span className="text-sm text-muted-foreground">
                                                    {similar.branch.name}
                                                </span>
                                            )}
                                            <span className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <MapPin className="size-3.5" />
                                                    {similar.location?.name}
                                                </span>
                                                <Badge variant="outline">
                                                    {t(':count Positions', {
                                                        count: similar.positions,
                                                    })}
                                                </Badge>
                                            </span>
                                        </Link>
                                    ))}
                                </section>
                            )}
                        </aside>
                    </div>
                </div>
            </CareerShell>

            <FormDialog
                open={applying}
                onOpenChange={setApplying}
                title={t('Apply for :job', { job: job.title })}
                description="We'll review your application and get back to you."
                submitLabel="Submit Application"
                processing={form.processing}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(career.apply.url(job.job_code), {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            setApplying(false);
                        },
                    });
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {field('first_name', 'First Name', { required: true })}
                    {field('last_name', 'Last Name', { required: true })}
                    {field('email', 'Email', { required: true, type: 'email' })}
                    {field('phone', 'Phone', { type: 'tel' })}
                    <div className="grid gap-2">
                        <Label htmlFor="apply-gender">{t('Gender')}</Label>
                        <SelectField
                            id="apply-gender"
                            value={form.data.gender}
                            onChange={(e) =>
                                form.setData('gender', e.target.value)
                            }
                        >
                            <option value="">{t('Prefer not to say')}</option>
                            <option value="male">{t('Male')}</option>
                            <option value="female">{t('Female')}</option>
                            <option value="other">{t('Other')}</option>
                        </SelectField>
                        <InputError message={form.errors.gender} />
                    </div>
                    {field('experience_years', 'Years of Experience', {
                        type: 'number',
                        min: 0,
                        step: '0.5',
                    })}
                    {field('current_company', 'Current Company')}
                    {field('current_position', 'Current Position')}
                    {field('expected_salary', 'Expected Salary', {
                        type: 'number',
                        min: 0,
                    })}
                    {field('linkedin_url', 'LinkedIn URL', { type: 'url' })}
                    <div className="sm:col-span-2">
                        {field('portfolio_url', 'Portfolio URL', {
                            type: 'url',
                        })}
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Mail, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import employeeRoutes from '@/routes/hr/employees';

type Named = { id: number; name: string } | null;

type Employee = {
    id: number;
    employee_id: string;
    phone: string | null;
    date_of_birth: string | null;
    gender: 'male' | 'female' | 'other' | null;
    date_of_joining: string | null;
    employment_type: string | null;
    employee_status: string;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    emergency_contact_name: string | null;
    emergency_contact_relationship: string | null;
    emergency_contact_number: string | null;
    bank_name: string | null;
    account_holder_name: string | null;
    account_number: string | null;
    bank_identifier_code: string | null;
    bank_branch: string | null;
    tax_payer_id: string | null;
    id_type: 'mykad' | 'passport' | null;
    id_number: string | null;
    documents: {
        id: number;
        file_name: string;
        document_type: { id: number; name: string } | null;
    }[];
    user: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        reports_to: Named;
    };
    branch: Named;
    department: Named;
    designation: Named;
    shift: {
        id: number;
        name: string;
        start_time: string;
        end_time: string;
    } | null;
};

type Certification = {
    id: number;
    completion_date: string | null;
    score: string | null;
    certification: boolean;
    program: Named;
};

type Contract = {
    id: number;
    contract_number: string;
    start_date: string;
    end_date: string | null;
    status: string;
    contract_type: Named;
};

const TABS = [
    'Basic Info',
    'Employment',
    'Contact',
    'Banking',
    'Certifications',
    'Documents',
] as const;

function Fields({ items }: { items: [string, ReactNode][] }) {
    const { t } = useTranslation();

    return (
        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            {items.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-sm text-muted-foreground">
                        {t(label)}
                    </dt>
                    <dd className="mt-1 font-medium">{value || '-'}</dd>
                </div>
            ))}
        </dl>
    );
}

export default function EmployeeShow({
    employee,
    certifications,
    contracts,
}: {
    employee: Employee;
    certifications: Certification[];
    contracts: Contract[];
}) {
    const { t } = useTranslation();
    const { date, time } = useFormat();
    const [tab, setTab] = useState<(typeof TABS)[number]>('Basic Info');
    const e = employee;

    const panels: Record<(typeof TABS)[number], [string, ReactNode]> = {
        'Basic Info': [
            'Basic Information',
            <Fields
                key="basic"
                items={[
                    ['Full Name', e.user.name],
                    ['Employee ID', e.employee_id],
                    ['Email', e.user.email],
                    ['Phone Number', e.phone],
                    ['Date of Birth', e.date_of_birth && date(e.date_of_birth)],
                    [
                        e.id_type === 'passport' ? 'Passport No.' : 'MyKad No.',
                        e.id_number,
                    ],
                    [
                        'Gender',
                        e.gender &&
                            t(
                                e.gender.charAt(0).toUpperCase() +
                                    e.gender.slice(1),
                            ),
                    ],
                ]}
            />,
        ],
        Employment: [
            'Employment Details',
            <Fields
                key="employment"
                items={[
                    ['Branch', e.branch?.name],
                    ['Department', e.department?.name],
                    ['Designation', e.designation?.name],
                    ['Reports To', e.user.reports_to?.name],
                    [
                        'Date of Joining',
                        e.date_of_joining && date(e.date_of_joining),
                    ],
                    [
                        'Employment Type',
                        e.employment_type && t(e.employment_type),
                    ],
                    [
                        'Shift',
                        e.shift &&
                            `${e.shift.name} (${time(e.shift.start_time)} - ${time(e.shift.end_time)})`,
                    ],
                    [
                        'Status',
                        <StatusBadge key="s" status={e.employee_status} />,
                    ],
                ]}
            />,
        ],
        Contact: [
            'Contact Information',
            <Fields
                key="contact"
                items={[
                    ['Address Line 1', e.address_line_1],
                    ['Address Line 2', e.address_line_2],
                    ['City', e.city],
                    ['State', e.state],
                    ['Country', e.country],
                    ['Postcode', e.postal_code],
                    ['Emergency Contact Name', e.emergency_contact_name],
                    ['Relationship', e.emergency_contact_relationship],
                    ['Emergency Contact Number', e.emergency_contact_number],
                ]}
            />,
        ],
        Banking: [
            'Banking Information',
            <Fields
                key="banking"
                items={[
                    ['Bank Name', e.bank_name],
                    ['Account Holder Name', e.account_holder_name],
                    ['Account Number', e.account_number],
                    ['Bank Identifier Code', e.bank_identifier_code],
                    ['Bank Branch', e.bank_branch],
                    ['Income Tax No.', e.tax_payer_id],
                ]}
            />,
        ],
        Certifications: [
            'Certifications',
            certifications.length === 0 ? (
                <p key="none" className="text-sm text-muted-foreground">
                    {t('No certifications yet')}
                </p>
            ) : (
                <ul key="certs" className="divide-y rounded-lg border">
                    {certifications.map((c) => (
                        <li
                            key={c.id}
                            className="flex items-center justify-between gap-4 p-3"
                        >
                            <div>
                                <div className="font-medium">
                                    {c.program?.name}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {c.completion_date &&
                                        date(c.completion_date)}
                                    {c.score !== null &&
                                        ` · ${t('Score')}: ${c.score}`}
                                </div>
                            </div>
                            {c.certification && (
                                <StatusBadge status="Certified" />
                            )}
                        </li>
                    ))}
                </ul>
            ),
        ],
        Documents: [
            'Documents',
            contracts.length === 0 && e.documents.length === 0 ? (
                <p key="none" className="text-sm text-muted-foreground">
                    {t('No documents yet')}
                </p>
            ) : (
                <ul key="docs" className="divide-y rounded-lg border">
                    {e.documents.map((d) => (
                        <li
                            key={`doc-${d.id}`}
                            className="flex items-center justify-between gap-4 p-3"
                        >
                            <div>
                                <div className="font-medium">
                                    {d.document_type?.name ?? t('Document')}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {d.file_name}
                                </div>
                            </div>
                            <a
                                href={
                                    employeeRoutes.document({
                                        employee: e.id,
                                        document: d.id,
                                    }).url
                                }
                                className="text-sm text-blue-600 hover:underline"
                            >
                                {t('Download')}
                            </a>
                        </li>
                    ))}
                    {contracts.map((c) => (
                        <li
                            key={c.id}
                            className="flex items-center justify-between gap-4 p-3"
                        >
                            <div>
                                <div className="font-medium">
                                    {c.contract_type?.name ?? t('Contract')} ·{' '}
                                    {c.contract_number}
                                </div>
                                <div className="text-sm whitespace-nowrap text-muted-foreground">
                                    {date(c.start_date)}
                                    {c.end_date && ` – ${date(c.end_date)}`}
                                </div>
                            </div>
                            <StatusBadge status={c.status} />
                        </li>
                    ))}
                </ul>
            ),
        ],
    };

    return (
        <>
            <Head title={e.user.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={e.user.name}
                    description="View detailed information and activity for employee."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={employeeRoutes.index()}>
                                <ArrowLeft className="rtl:rotate-180" />{' '}
                                {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <div className="grid items-start gap-6 rounded-xl border bg-muted/20 p-4 md:p-6 lg:grid-cols-[18rem_1fr]">
                    <aside className="flex flex-col items-center rounded-xl border bg-card p-6 text-center shadow-sm">
                        <UserAvatar
                            name={e.user.name}
                            src={e.user.avatar}
                            gender={e.gender}
                            className="size-32"
                        />
                        <h2 className="mt-4 text-xl font-bold">
                            {e.user.name}
                        </h2>
                        <p className="text-muted-foreground">
                            {e.designation?.name}
                        </p>
                        <div className="mt-2">
                            <StatusBadge status={e.employee_status} />
                        </div>
                        <ul className="mt-5 grid w-full gap-3 text-start text-sm">
                            <li className="flex items-center gap-3">
                                <UserRound className="size-4 shrink-0 text-muted-foreground" />
                                {t('Employee ID')}: {e.employee_id}
                            </li>
                            <li className="flex items-center gap-3 break-all">
                                <Mail className="size-4 shrink-0 text-muted-foreground" />
                                {e.user.email}
                            </li>
                            {e.department && (
                                <li className="flex items-center gap-3">
                                    <Building2 className="size-4 shrink-0 text-muted-foreground" />
                                    {e.department.name}
                                </li>
                            )}
                        </ul>
                    </aside>

                    <div className="grid gap-4">
                        <div
                            role="tablist"
                            className="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 md:grid-cols-6"
                        >
                            {TABS.map((name) => (
                                <button
                                    key={name}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === name}
                                    onClick={() => setTab(name)}
                                    className={cn(
                                        'rounded-md px-3 py-2 text-sm font-medium',
                                        tab === name
                                            ? 'bg-card shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t(name)}
                                </button>
                            ))}
                        </div>
                        <section
                            role="tabpanel"
                            className="rounded-xl border bg-card p-6 shadow-sm"
                        >
                            <h3 className="mb-6 text-lg font-semibold">
                                {t(panels[tab][0])}
                            </h3>
                            {panels[tab][1]}
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

EmployeeShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeeRoutes.index() },
        { title: 'Employee Details', href: employeeRoutes.index() },
    ],
};

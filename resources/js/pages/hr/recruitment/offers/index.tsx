import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Eye,
    List,
    Plus,
    RefreshCw,
    SquareKanban,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { ViewToggle } from '@/components/view-toggle';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import offerRoutes from '@/routes/hr/recruitment/offers';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };
type Status =
    | 'Draft'
    | 'Sent'
    | 'Accepted'
    | 'Negotiating'
    | 'Declined'
    | 'Expired';
type CandidateOption = {
    id: number;
    first_name: string;
    last_name: string;
    job_id: number;
};

type Offer = {
    id: number;
    candidate_id: number;
    job_id: number;
    offer_template_id: number | null;
    offer_date: string;
    salary: string;
    bonus: string | null;
    benefits: string | null;
    start_date: string;
    expiration_date: string;
    status: Status;
    response_date: string | null;
    decline_reason: string | null;
    approved_by: number | null;
    candidate: CandidateOption | null;
    job: { id: number; title: string } | null;
    template: Option | null;
    approver: Option | null;
};

const STATUSES: Status[] = [
    'Draft',
    'Sent',
    'Accepted',
    'Negotiating',
    'Declined',
    'Expired',
];
// Final: the demo hides Edit and Update Status once reached.
const CLOSED: Status[] = ['Accepted', 'Declined'];

const today = () => new Date().toLocaleDateString('en-CA');

const blank = () => ({
    candidate_id: '' as number | '',
    offer_template_id: '' as number | '',
    offer_date: today(),
    salary: '',
    bonus: '',
    benefits: '',
    start_date: '',
    expiration_date: '',
    approved_by: '' as number | '',
});

const fullName = (c: { first_name: string; last_name: string }) =>
    `${c.first_name} ${c.last_name}`;

export default function Offers({
    offers,
    candidates,
    offerTemplates,
    employees,
    statusCounts,
    filters,
}: {
    offers: Paginated<Offer>;
    candidates: CandidateOption[];
    offerTemplates: Option[];
    employees: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const [editing, setEditing] = useState<Offer | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Offer | null>(null);
    const [statusFor, setStatusFor] = useState<Offer | null>(null);
    const form = useForm(blank());
    const statusForm = useForm({
        status: 'Draft' as Status,
        decline_reason: '',
    });
    const url = offerRoutes.index();

    const openForm = (offer: Offer | null) => {
        setEditing(offer);
        form.clearErrors();
        form.setData(
            offer
                ? {
                      candidate_id: offer.candidate_id,
                      offer_template_id: offer.offer_template_id ?? '',
                      offer_date: offer.offer_date,
                      salary: offer.salary,
                      bonus: offer.bonus ?? '',
                      benefits: offer.benefits ?? '',
                      start_date: offer.start_date,
                      expiration_date: offer.expiration_date,
                      approved_by: offer.approved_by ?? '',
                  }
                : blank(),
        );
        setFormOpen(true);
    };

    const submit = () =>
        form.submit(
            editing ? offerRoutes.update(editing.id) : offerRoutes.store(),
            { preserveScroll: true, onSuccess: () => setFormOpen(false) },
        );

    const today = new Date().toISOString().slice(0, 10);

    const columns: Column<Offer>[] = [
        {
            key: 'candidate',
            label: 'Candidate',
            render: (row) =>
                row.candidate ? (
                    <PersonCell
                        name={fullName(row.candidate)}
                        detail={row.job?.title}
                    />
                ) : (
                    '—'
                ),
        },
        {
            key: 'salary',
            label: 'Salary',
            sortable: true,
            render: (row) => (
                <span className="whitespace-nowrap">
                    {money(Number(row.salary))}
                </span>
            ),
        },
        {
            key: 'start_date',
            label: 'Start Date',
            sortable: true,
            render: (row) => <DateCell value={row.start_date} />,
        },
        {
            key: 'expiration_date',
            label: 'Expires',
            sortable: true,
            render: (row) => (
                <div className="grid gap-0.5">
                    <DateCell value={row.expiration_date} />
                    {!CLOSED.includes(row.status) &&
                        row.expiration_date < today && (
                            <span className="ps-6 text-xs text-destructive">
                                {t('Expired')}
                            </span>
                        )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'offer_date',
            label: 'Offer Date',
            render: (row) => <DateCell value={row.offer_date} />,
        },
    ];

    return (
        <>
            <Head title={t('Offers')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Offers"
                    description="Create job offers and track candidate responses."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <ViewToggle
                                current="List"
                                views={[
                                    {
                                        label: 'List',
                                        href: offerRoutes.index(),
                                        icon: List,
                                    },
                                    {
                                        label: 'Kanban',
                                        href: offerRoutes.kanban(),
                                        icon: SquareKanban,
                                    },
                                ]}
                            />
                            {can('create-offers') && (
                                <Button onClick={() => openForm(null)}>
                                    <Plus /> {t('Create Offer')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={offers}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={statusCounts}
                        />
                    }
                    toolbar={
                        <FilterSelect
                            url={url}
                            filters={filters}
                            name="candidate_id"
                            label="All Candidates"
                            options={candidates.map((c) => ({
                                id: c.id,
                                name: fullName(c),
                            }))}
                        />
                    }
                    actions={(offer) => {
                        const open = !CLOSED.includes(offer.status);

                        return (
                            <>
                                {can('view-offers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('View')}
                                        asChild
                                    >
                                        <Link href={offerRoutes.show(offer.id)}>
                                            <Eye />
                                        </Link>
                                    </Button>
                                )}
                                {open && can('approve-offers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Update Status')}
                                        onClick={() => {
                                            statusForm.clearErrors();
                                            statusForm.setData({
                                                status: offer.status,
                                                decline_reason: '',
                                            });
                                            setStatusFor(offer);
                                        }}
                                    >
                                        <RefreshCw />
                                    </Button>
                                )}
                                {open && can('edit-offers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(offer)}
                                    >
                                        <SquarePen />
                                    </Button>
                                )}
                                {can('delete-offers') && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(offer)}
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                            </>
                        );
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Offer' : 'Create Offer'}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="offer-candidate">
                            {t('Candidate')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="offer-candidate"
                            required
                            value={form.data.candidate_id}
                            onChange={(e) =>
                                form.setData(
                                    'candidate_id',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('Select Candidate')}</option>
                            {candidates.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {fullName(c)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.candidate_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-template">
                            {t('Offer Template')}
                        </Label>
                        <SelectField
                            id="offer-template"
                            value={form.data.offer_template_id}
                            onChange={(e) =>
                                form.setData(
                                    'offer_template_id',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('None')}</option>
                            {offerTemplates.map((tpl) => (
                                <option key={tpl.id} value={tpl.id}>
                                    {tpl.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.offer_template_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-salary">
                            {t('Salary')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="offer-salary"
                            type="number"
                            min={0}
                            step="0.01"
                            required
                            value={form.data.salary}
                            onChange={(e) =>
                                form.setData('salary', e.target.value)
                            }
                        />
                        <InputError message={form.errors.salary} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-bonus">{t('Bonus')}</Label>
                        <Input
                            id="offer-bonus"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.bonus}
                            onChange={(e) =>
                                form.setData('bonus', e.target.value)
                            }
                        />
                        <InputError message={form.errors.bonus} />
                    </div>
                    {(
                        [
                            ['offer_date', 'Offer Date'],
                            ['start_date', 'Start Date'],
                            ['expiration_date', 'Expiration Date'],
                        ] as const
                    ).map(([name, label]) => (
                        <div key={name} className="grid gap-2">
                            <Label htmlFor={`offer-${name}`}>
                                {t(label)}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id={`offer-${name}`}
                                type="date"
                                required
                                value={form.data[name]}
                                onChange={(e) =>
                                    form.setData(name, e.target.value)
                                }
                            />
                            <InputError message={form.errors[name]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="offer-approver">
                            {t('Approved By')}
                        </Label>
                        <SelectField
                            id="offer-approver"
                            value={form.data.approved_by}
                            onChange={(e) =>
                                form.setData(
                                    'approved_by',
                                    e.target.value ? +e.target.value : '',
                                )
                            }
                        >
                            <option value="">{t('Select Approver')}</option>
                            {employees.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.approved_by} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="offer-benefits">{t('Benefits')}</Label>
                        <textarea
                            id="offer-benefits"
                            rows={3}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={form.data.benefits}
                            onChange={(e) =>
                                form.setData('benefits', e.target.value)
                            }
                        />
                        <InputError message={form.errors.benefits} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={statusFor !== null}
                onOpenChange={(open) => !open && setStatusFor(null)}
                title="Update Status"
                description="Accepting hires the candidate; declined or expired offers reject them."
                onSubmit={(e) => {
                    e.preventDefault();

                    if (statusFor) {
                        statusForm.submit(
                            offerRoutes.updateStatus(statusFor.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setStatusFor(null),
                            },
                        );
                    }
                }}
                processing={statusForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="offer-new-status">{t('Status')}</Label>
                    <SelectField
                        id="offer-new-status"
                        value={statusForm.data.status}
                        onChange={(e) =>
                            statusForm.setData(
                                'status',
                                e.target.value as Status,
                            )
                        }
                    >
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {t(s)}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={statusForm.errors.status} />
                </div>
                {statusForm.data.status === 'Declined' && (
                    <div className="grid gap-2">
                        <Label htmlFor="offer-decline-reason">
                            {t('Decline Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="offer-decline-reason"
                            rows={3}
                            required
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            value={statusForm.data.decline_reason}
                            onChange={(e) =>
                                statusForm.setData(
                                    'decline_reason',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={statusForm.errors.decline_reason}
                        />
                    </div>
                )}
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This offer will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(offerRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Offers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: offerRoutes.index() },
        { title: 'Offers', href: offerRoutes.index() },
    ],
};

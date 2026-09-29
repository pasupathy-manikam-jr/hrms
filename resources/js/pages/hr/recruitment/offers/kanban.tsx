import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, List, SquareKanban } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { KanbanBoard, KanbanSearch } from '@/components/kanban-board';
import { PageHeader } from '@/components/page-header';
import { PersonCell } from '@/components/user-avatar';
import { FilterSelect } from '@/components/table-filters';
import { Label } from '@/components/ui/label';
import { ViewToggle } from '@/components/view-toggle';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import offerRoutes from '@/routes/hr/recruitment/offers';
import type { TableFilters } from '@/types';

const STATUSES = [
    'Draft',
    'Sent',
    'Accepted',
    'Negotiating',
    'Declined',
    'Expired',
] as const;
// Final: updateStatus() refuses to move these.
const CLOSED = ['Accepted', 'Declined'];

type Person = { id: number; first_name: string; last_name: string };

type Offer = {
    id: number;
    salary: string;
    bonus: string | null;
    offer_date: string;
    start_date: string;
    expiration_date: string;
    status: string;
    candidate: (Person & { email: string }) | null;
    job: { id: number; title: string } | null;
    approver: { id: number; name: string } | null;
};

export default function OfferKanban({
    offers,
    candidates,
    filters,
}: {
    offers: Offer[];
    candidates: Person[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { date, money } = useFormat();
    const can = useCan();
    const url = offerRoutes.kanban();
    const [declining, setDeclining] = useState<Offer | null>(null);
    const declineForm = useForm({ status: 'Declined', decline_reason: '' });

    const move = (offer: Offer, status: string) => {
        // The endpoint requires a reason for a decline, so ask for one first.
        if (status === 'Declined') {
            declineForm.reset();
            declineForm.clearErrors();
            setDeclining(offer);

            return;
        }

        router.put(
            offerRoutes.updateStatus(offer.id),
            { status },
            {
                preserveScroll: true,
                onError: (errors) => toast.error(Object.values(errors)[0]),
            },
        );
    };

    return (
        <>
            <Head title={t('Offers')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Offers"
                    description="Create job offers and track candidate responses."
                    action={
                        <ViewToggle
                            current="Kanban"
                            views={[
                                {
                                    label: 'List',
                                    href: offerRoutes.index(),
                                    icon: List,
                                },
                                {
                                    label: 'Kanban',
                                    href: url,
                                    icon: SquareKanban,
                                },
                            ]}
                        />
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <KanbanSearch url={url} filters={filters} />
                    <FilterSelect
                        url={url}
                        filters={filters}
                        name="candidate_id"
                        label="All Candidates"
                        options={candidates.map((c) => ({
                            id: c.id,
                            name: `${c.first_name} ${c.last_name}`,
                        }))}
                    />
                </div>

                <KanbanBoard
                    statuses={STATUSES}
                    items={offers}
                    canMove={(offer) =>
                        can('approve-offers') && !CLOSED.includes(offer.status)
                    }
                    onMove={move}
                    renderCard={(o) => (
                        <>
                            <PersonCell
                                name={
                                    o.candidate
                                        ? `${o.candidate.first_name} ${o.candidate.last_name}`
                                        : '—'
                                }
                                detail={o.job?.title}
                            />
                            <div className="font-semibold">
                                {money(Number(o.salary))}
                                {o.bonus !== null && (
                                    <span className="ms-1 text-xs font-normal text-muted-foreground">
                                        + {money(Number(o.bonus))} {t('Bonus')}
                                    </span>
                                )}
                            </div>
                            <div className="grid gap-1 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <CalendarDays className="size-3" />
                                    {t('Start')}: {date(o.start_date)}
                                </span>
                                <span>
                                    {t('Expires')}: {date(o.expiration_date)}
                                </span>
                                {o.approver && (
                                    <span>
                                        {t('Approved By')}: {o.approver.name}
                                    </span>
                                )}
                            </div>
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={declining !== null}
                onOpenChange={(open) => !open && setDeclining(null)}
                title="Decline Offer"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (declining) {
                        declineForm.submit(
                            offerRoutes.updateStatus(declining.id),
                            {
                                preserveScroll: true,
                                onSuccess: () => setDeclining(null),
                            },
                        );
                    }
                }}
                processing={declineForm.processing}
            >
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
                        value={declineForm.data.decline_reason}
                        onChange={(e) =>
                            declineForm.setData(
                                'decline_reason',
                                e.target.value,
                            )
                        }
                    />
                    <InputError message={declineForm.errors.decline_reason} />
                    <InputError message={declineForm.errors.status} />
                </div>
            </FormDialog>
        </>
    );
}

OfferKanban.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: offerRoutes.index() },
        { title: 'Offers', href: offerRoutes.index() },
        { title: 'Kanban', href: offerRoutes.kanban() },
    ],
};

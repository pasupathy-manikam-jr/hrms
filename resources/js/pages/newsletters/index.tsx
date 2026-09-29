import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import newsletterRoutes from '@/routes/newsletters';
import type { Paginated, TableFilters } from '@/types';

type Subscriber = { id: number; email: string; created_at: string };

export default function Newsletters({
    newsletters,
    filters,
}: {
    newsletters: Paginated<Subscriber>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [deleting, setDeleting] = useState<Subscriber | null>(null);

    const columns: Column<Subscriber>[] = [
        {
            key: 'email',
            label: 'Email',
            sortable: true,
            render: (s) => <span className="font-medium">{s.email}</span>,
        },
        {
            key: 'created_at',
            label: 'Subscribed Date',
            sortable: true,
            render: (s) => <DateCell value={s.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Newsletter')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Newsletter"
                    description="Email addresses subscribed from the landing page."
                />

                <DataTable
                    data={newsletters}
                    columns={columns}
                    filters={filters}
                    url={newsletterRoutes.index()}
                    actions={(subscriber) =>
                        can('delete-newsletters') && (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Delete')}
                                onClick={() => setDeleting(subscriber)}
                            >
                                <Trash2 />
                            </Button>
                        )
                    }
                />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This subscriber will be permanently removed."
                onConfirm={() =>
                    deleting &&
                    router.delete(newsletterRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Newsletters.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Newsletter', href: newsletterRoutes.index() },
    ],
};

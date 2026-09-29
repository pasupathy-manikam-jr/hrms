import { Head, Link, router } from '@inertiajs/react';
import { Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { settings } from '@/routes/landing-page';
import pageRoutes from '@/routes/landing-page/custom-pages';
import type { Paginated, TableFilters } from '@/types';

type CustomPage = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    is_active: boolean;
    sort_order: number;
    created_at: string;
};

export default function CustomPages({
    pages,
    filters,
}: {
    pages: Paginated<CustomPage>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [deleting, setDeleting] = useState<CustomPage | null>(null);

    const columns: Column<CustomPage>[] = [
        {
            key: 'title',
            label: 'Title',
            sortable: true,
            render: (p) => (
                <div>
                    <div className="font-medium">{p.title}</div>
                    <div className="text-muted-foreground">/page/{p.slug}</div>
                </div>
            ),
        },
        {
            key: 'excerpt',
            label: 'Content',
            render: (p) => (
                <span className="text-muted-foreground">{p.excerpt}</span>
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (p) => (
                <StatusBadge status={p.is_active ? 'active' : 'inactive'} />
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (p) => <DateCell value={p.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Custom Pages')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Custom Pages"
                    description="Manage the pages linked from the landing page header and footer."
                    action={
                        can('edit-landing-page') && (
                            <Button asChild>
                                <Link href={pageRoutes.create()}>
                                    <Plus /> {t('Add Page')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={pages}
                    columns={columns}
                    filters={filters}
                    url={pageRoutes.index()}
                    actions={(page) => (
                        <>
                            {can('edit-landing-page') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        asChild
                                    >
                                        <Link href={pageRoutes.edit(page.id)}>
                                            <SquarePen />
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Delete')}
                                        onClick={() => setDeleting(page)}
                                    >
                                        <Trash2 />
                                    </Button>
                                </>
                            )}
                        </>
                    )}
                />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This page will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(pageRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

CustomPages.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Landing Page', href: settings() },
        { title: 'Custom Pages', href: pageRoutes.index() },
    ],
};

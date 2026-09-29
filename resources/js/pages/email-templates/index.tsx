import { Head, Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/email-templates';
import type { Paginated, TableFilters } from '@/types';

type Template = {
    id: number;
    name: string;
    from: string | null;
    updated_at: string;
    email_template_langs_count: number;
    email_template_langs: { id: number; lang: string; subject: string }[];
};

export default function EmailTemplates({
    templates,
    filters,
}: {
    templates: Paginated<Template>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();

    const columns: Column<Template>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (tpl) => (
                <Link
                    href={templateRoutes.show(tpl.id)}
                    className="font-medium hover:underline"
                >
                    {tpl.name}
                </Link>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Email Templates')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Email Templates"
                    description="Edit the subject and body of the emails the app sends."
                />
                <DataTable
                    data={templates}
                    columns={columns}
                    filters={filters}
                    url={templateRoutes.index()}
                    actions={(tpl) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={templateRoutes.show(tpl.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>
        </>
    );
}

EmailTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Email Templates', href: templateRoutes.index() },
    ],
};

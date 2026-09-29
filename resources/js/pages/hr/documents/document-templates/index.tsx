import { TemplatePage } from '@/components/template-page';
import type { Template } from '@/components/template-page';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/hr/documents/document-templates';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

export default function DocumentTemplates({
    documentTemplates,
    categories,
    statusCounts,
    filters,
}: {
    documentTemplates: Paginated<Template>;
    categories: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    return (
        <TemplatePage
            title="Document Templates"
            description="Reusable templates for generating HR documents."
            kind="document"
            module="document-templates"
            routes={templateRoutes}
            templates={documentTemplates}
            group={{
                key: 'category_id',
                relation: 'category',
                label: 'Category',
                allLabel: 'All Categories',
            }}
            groups={categories}
            statusCounts={statusCounts}
            filters={filters}
        />
    );
}

DocumentTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Document Templates', href: templateRoutes.index() },
    ],
};

import { TemplateShow } from '@/components/template-page';
import type { Template } from '@/components/template-page';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/hr/documents/document-templates';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';

export default function DocumentTemplateShow({
    template,
    placeholders,
    employees,
}: {
    template: Template;
    placeholders: string[];
    employees: { id: number; name: string; employee_id: string }[];
}) {
    return (
        <TemplateShow
            template={template}
            placeholders={placeholders}
            employees={employees}
            routes={templateRoutes}
            group={{ relation: 'category', label: 'Category' }}
            description="Reusable letters and certificates with placeholders filled in per employee."
        />
    );
}

DocumentTemplateShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Document Templates', href: templateRoutes.index() },
        { title: 'Template Details', href: templateRoutes.index() },
    ],
};

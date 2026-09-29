import { TemplateShow } from '@/components/template-page';
import type { Template } from '@/components/template-page';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/hr/contracts/contract-templates';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';

export default function ContractTemplateShow({
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
            group={{ relation: 'contract_type', label: 'Contract Type' }}
            description="Contract wording with placeholders filled in per employee."
        />
    );
}

ContractTemplateShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Contract Templates', href: templateRoutes.index() },
        { title: 'Template Details', href: templateRoutes.index() },
    ],
};

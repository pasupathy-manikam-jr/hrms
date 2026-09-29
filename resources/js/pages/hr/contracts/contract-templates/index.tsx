import { TemplatePage } from '@/components/template-page';
import type { Template } from '@/components/template-page';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/hr/contracts/contract-templates';
import hrDocumentRoutes from '@/routes/hr/documents/hr-documents';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

export default function ContractTemplates({
    contractTemplates,
    contractTypes,
    statusCounts,
    filters,
}: {
    contractTemplates: Paginated<Template>;
    contractTypes: Option[];
    statusCounts: Record<string, number>;
    filters: TableFilters;
}) {
    return (
        <TemplatePage
            title="Contract Templates"
            description="Reusable templates for generating employee contracts."
            kind="contract"
            module="contract-templates"
            routes={templateRoutes}
            templates={contractTemplates}
            group={{
                key: 'contract_type_id',
                relation: 'contract_type',
                label: 'Contract Type',
                allLabel: 'All Contract Types',
            }}
            groups={contractTypes}
            statusCounts={statusCounts}
            filters={filters}
        />
    );
}

ContractTemplates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents & Contracts', href: hrDocumentRoutes.index() },
        { title: 'Contract Templates', href: templateRoutes.index() },
    ],
};

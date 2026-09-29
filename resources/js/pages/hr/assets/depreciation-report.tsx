import { Head, Link } from '@inertiajs/react';
import { ChartColumn, Download, List, Printer } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { DateCell } from '@/components/table-cells';
import { DateRangeFilter, FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import assetRoutes from '@/routes/hr/assets';
import type { Paginated, TableFilters } from '@/types';

type Option = { id: number; name: string };

const percent = (part: number, whole: number) =>
    whole > 0 ? (part / whole) * 100 : 0;

type Asset = {
    id: number;
    name: string;
    asset_code: string | null;
    purchase_date: string | null;
    purchase_cost: string;
    salvage_value: string;
    useful_life_years: number;
    current_value: number;
    asset_type: Option | null;
};

export default function DepreciationReport({
    assets,
    assetTypes,
    totals,
    filters,
}: {
    assets: Paginated<Asset>;
    assetTypes: Option[];
    totals: {
        purchase_value: number;
        current_value: number;
        depreciation: number;
    };
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const url = assetRoutes.depreciationReport();

    const columns: Column<Asset>[] = [
        {
            key: 'name',
            label: 'Asset Name',
            sortable: true,
            render: (a) => (
                <div>
                    <div className="font-medium">{a.name}</div>
                    <div className="text-muted-foreground">
                        {a.asset_type?.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'purchase_date',
            label: 'Purchase Date',
            sortable: true,
            render: (a) => <DateCell value={a.purchase_date} />,
        },
        {
            key: 'purchase_cost',
            label: 'Purchase Cost',
            sortable: true,
            render: (a) => money(Number(a.purchase_cost)),
        },
        {
            key: 'method',
            label: 'Depreciation Method',
            render: () => t('Straight Line'),
        },
        {
            key: 'current_value',
            label: 'Current Value',
            render: (a) => money(a.current_value),
        },
        {
            key: 'depreciation',
            label: 'Depreciation',
            render: (a) => money(Number(a.purchase_cost) - a.current_value),
        },
        {
            key: 'depreciation_percent',
            label: 'Depreciation %',
            render: (a) =>
                `${percent(Number(a.purchase_cost) - a.current_value, Number(a.purchase_cost)).toFixed(2)}%`,
        },
    ];

    return (
        <>
            <Head title={t('Depreciation Report')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Asset Depreciation Report"
                    description="Straight-line depreciation of company assets, charged per full month since purchase."
                    action={
                        <div className="flex flex-wrap gap-2 print:hidden">
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.index()}>
                                    <List /> {t('Asset List')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={assetRoutes.dashboard()}>
                                    <ChartColumn /> {t('Dashboard')}
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer /> {t('Print')}
                            </Button>
                            {can('export-assets') && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={
                                            assetRoutes.exportDepreciationCsv({
                                                query: filters,
                                            }).url
                                        }
                                        download
                                    >
                                        <Download /> {t('Export CSV')}
                                    </a>
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {(
                        [
                            [
                                'Total Purchase Value',
                                totals.purchase_value,
                                t('Original cost of all assets'),
                                'text-blue-600',
                            ],
                            [
                                'Total Current Value',
                                totals.current_value,
                                t(':percent% of purchase value', {
                                    percent: Math.round(
                                        percent(
                                            totals.current_value,
                                            totals.purchase_value,
                                        ),
                                    ),
                                }),
                                'text-emerald-600',
                            ],
                            [
                                'Total Depreciation',
                                totals.depreciation,
                                t(':percent% of purchase value', {
                                    percent: Math.round(
                                        percent(
                                            totals.depreciation,
                                            totals.purchase_value,
                                        ),
                                    ),
                                }),
                                'text-red-600',
                            ],
                        ] as const
                    ).map(([label, value, note, tone]) => (
                        <div
                            key={label}
                            className="rounded-xl border bg-card p-5 shadow-sm dark:border-gray-800"
                        >
                            <div className="text-sm text-muted-foreground">
                                {t(label)}
                            </div>
                            <div className="text-xl font-bold">
                                {money(value)}
                            </div>
                            <div className={`mt-1 text-xs ${tone}`}>{note}</div>
                        </div>
                    ))}
                </div>

                <DataTable
                    data={assets}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="asset_type_id"
                                label="All Types"
                                options={assetTypes}
                            />
                            <DateRangeFilter url={url} filters={filters} />
                        </>
                    }
                />
            </div>
        </>
    );
}

DepreciationReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Asset Management', href: assetRoutes.dashboard() },
        {
            title: 'Depreciation Report',
            href: assetRoutes.depreciationReport(),
        },
    ],
};

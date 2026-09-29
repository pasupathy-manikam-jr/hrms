import { Head } from '@inertiajs/react';
import {
    Barcode,
    CalendarDays,
    MapPin,
    Package,
    Undo2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import {
    AssignAssetDialog,
    ReturnAssetDialog,
} from '@/components/asset-assignment-dialogs';
import {
    DetailPage,
    Fields,
    RecordList,
    Summary,
    SummaryIcon,
    TextBlock,
} from '@/components/detail-page';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { PersonCell } from '@/components/user-avatar';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import assetRoutes from '@/routes/hr/assets';

type Assignment = {
    id: number;
    assigned_at: string;
    returned_at: string | null;
    notes: string | null;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | null;
        user: { name: string; email: string; avatar: string | null };
    };
};

type Asset = {
    id: number;
    name: string;
    serial_number: string | null;
    asset_code: string | null;
    purchase_date: string | null;
    purchase_cost: string;
    salvage_value: string;
    useful_life_years: number;
    current_value: number;
    status: string;
    condition: string;
    location: string | null;
    description: string | null;
    created_at: string;
    asset_type: { id: number; name: string } | null;
    assignments: Assignment[];
};

export default function AssetShow({
    asset,
    employees,
}: {
    asset: Asset;
    employees: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { money } = useFormat();
    const can = useCan();
    const [assigning, setAssigning] = useState(false);
    const [returning, setReturning] = useState(false);
    const cost = Number(asset.purchase_cost);
    const depreciation = cost - asset.current_value;
    const holder = asset.assignments.find((a) => a.returned_at === null);

    return (
        <>
            <Head title={asset.name} />
            <DetailPage
                title={asset.name}
                description="View asset details, depreciation and assignment history."
                back={assetRoutes.index()}
                summary={
                    <>
                        <Summary
                            media={<SummaryIcon icon={Package} />}
                            title={asset.name}
                            subtitle={asset.asset_type?.name}
                            status={asset.status}
                            facts={[
                                [
                                    Barcode,
                                    asset.asset_code && (
                                        <IdBadge>{asset.asset_code}</IdBadge>
                                    ),
                                ],
                                [MapPin, asset.location],
                                [
                                    CalendarDays,
                                    asset.purchase_date && (
                                        <DateCell value={asset.purchase_date} />
                                    ),
                                ],
                            ]}
                        />
                        {holder && (
                            <div className="mt-5 w-full border-t pt-5 text-start">
                                <div className="mb-2 text-sm text-muted-foreground">
                                    {t('Assigned To')}
                                </div>
                                <PersonCell
                                    name={holder.employee.user.name}
                                    detail={holder.employee.user.email}
                                    src={holder.employee.user.avatar}
                                    gender={holder.employee.gender}
                                />
                            </div>
                        )}
                        {can('assign-assets') && (
                            <div className="mt-5 w-full">
                                {asset.status === 'available' && (
                                    <Button
                                        className="w-full"
                                        onClick={() => setAssigning(true)}
                                    >
                                        <UserPlus /> {t('Assign')}
                                    </Button>
                                )}
                                {asset.status === 'assigned' && (
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => setReturning(true)}
                                    >
                                        <Undo2 /> {t('Return')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </>
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Asset Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        ['Name', asset.name],
                                        ['Asset Type', asset.asset_type?.name],
                                        [
                                            'Asset Code',
                                            asset.asset_code && (
                                                <IdBadge>
                                                    {asset.asset_code}
                                                </IdBadge>
                                            ),
                                        ],
                                        ['Serial Number', asset.serial_number],
                                        [
                                            'Purchase Date',
                                            <DateCell
                                                key="d"
                                                value={asset.purchase_date}
                                            />,
                                        ],
                                        ['Location', asset.location],
                                        [
                                            'Status',
                                            <StatusBadge
                                                key="s"
                                                status={asset.status}
                                            />,
                                        ],
                                        [
                                            'Condition',
                                            <StatusBadge
                                                key="c"
                                                status={asset.condition}
                                            />,
                                        ],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={asset.description}
                                />
                            </div>
                        ),
                    },
                    {
                        label: 'Depreciation',
                        heading: 'Depreciation & Book Value',
                        content: (
                            <Fields
                                items={[
                                    ['Depreciation Method', t('Straight Line')],
                                    [
                                        'Useful Life (Years)',
                                        asset.useful_life_years,
                                    ],
                                    ['Purchase Cost', money(cost)],
                                    [
                                        'Salvage Value',
                                        money(Number(asset.salvage_value)),
                                    ],
                                    [
                                        'Accumulated Depreciation',
                                        money(depreciation),
                                    ],
                                    [
                                        'Depreciation %',
                                        cost > 0
                                            ? `${((depreciation / cost) * 100).toFixed(2)}%`
                                            : '0%',
                                    ],
                                    [
                                        'Current Value',
                                        <span
                                            key="v"
                                            className="text-lg font-semibold text-primary"
                                        >
                                            {money(asset.current_value)}
                                        </span>,
                                    ],
                                ]}
                            />
                        ),
                    },
                    {
                        label: 'Assignment History',
                        content: (
                            <RecordList
                                items={asset.assignments}
                                empty="No assignments yet"
                                render={(a) => (
                                    <>
                                        <div className="grid gap-1">
                                            <PersonCell
                                                name={a.employee.user.name}
                                                detail={a.employee.employee_id}
                                                src={a.employee.user.avatar}
                                                gender={a.employee.gender}
                                            />
                                            {a.notes && (
                                                <p className="ps-13 text-sm whitespace-pre-line text-muted-foreground">
                                                    {a.notes}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <DateCell value={a.assigned_at} />
                                            <span>→</span>
                                            {a.returned_at ? (
                                                <DateCell
                                                    value={a.returned_at}
                                                />
                                            ) : (
                                                <StatusBadge status="assigned" />
                                            )}
                                        </div>
                                    </>
                                )}
                            />
                        ),
                    },
                ]}
            />

            <AssignAssetDialog
                key={`assign-${assigning}`}
                asset={assigning ? asset : null}
                employees={employees}
                onClose={() => setAssigning(false)}
            />
            <ReturnAssetDialog
                key={`return-${returning}`}
                asset={returning ? asset : null}
                onClose={() => setReturning(false)}
            />
        </>
    );
}

AssetShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Asset Management', href: assetRoutes.index() },
        { title: 'Assets', href: assetRoutes.index() },
        { title: 'Asset Details', href: assetRoutes.index() },
    ],
};

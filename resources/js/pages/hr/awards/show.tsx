import { Head } from '@inertiajs/react';
import {
    Award as AwardIcon,
    Building2,
    CalendarDays,
    Gift,
} from 'lucide-react';
import {
    DetailPage,
    Fields,
    Summary,
    TextBlock,
} from '@/components/detail-page';
import { DateCell, IdBadge } from '@/components/table-cells';
import { PersonCell, UserAvatar } from '@/components/user-avatar';
import { useFormat } from '@/hooks/use-format';
import { dashboard } from '@/routes';
import awardRoutes from '@/routes/hr/awards';

type Option = { id: number; name: string };

type Award = {
    id: number;
    award_date: string;
    gift: string | null;
    monetary_value: string | null;
    description: string | null;
    created_at: string;
    employee: {
        id: number;
        employee_id: string;
        gender: 'male' | 'female' | 'other' | null;
        user: { name: string; email: string; avatar: string | null };
        department: Option | null;
        designation: Option | null;
    };
    award_type: Option & { description: string | null };
};

export default function AwardShow({ award }: { award: Award }) {
    const { date, money } = useFormat();
    const { employee } = award;
    const value =
        award.monetary_value !== null
            ? money(Number(award.monetary_value))
            : null;

    return (
        <>
            <Head title={award.award_type.name} />
            <DetailPage
                title={award.award_type.name}
                description="View the award, its recipient, gift and value."
                back={awardRoutes.index()}
                summary={
                    <Summary
                        media={
                            <UserAvatar
                                name={employee.user.name}
                                src={employee.user.avatar}
                                gender={employee.gender}
                                className="size-32"
                            />
                        }
                        title={employee.user.name}
                        subtitle={employee.designation?.name}
                        facts={[
                            [AwardIcon, award.award_type.name],
                            [CalendarDays, date(award.award_date)],
                            [Gift, award.gift],
                            [Building2, employee.department?.name],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Details',
                        heading: 'Award Details',
                        content: (
                            <div className="grid gap-6">
                                <Fields
                                    items={[
                                        [
                                            'Employee',
                                            <PersonCell
                                                key="e"
                                                name={employee.user.name}
                                                detail={employee.user.email}
                                                src={employee.user.avatar}
                                                gender={employee.gender}
                                            />,
                                        ],
                                        [
                                            'Employee ID',
                                            <IdBadge key="id">
                                                {employee.employee_id}
                                            </IdBadge>,
                                        ],
                                        ['Award Type', award.award_type.name],
                                        [
                                            'Award Date',
                                            <DateCell
                                                key="d"
                                                value={award.award_date}
                                            />,
                                        ],
                                        ['Gift', award.gift],
                                        ['Monetary Value', value],
                                    ]}
                                />
                                <TextBlock
                                    label="Description"
                                    value={award.description}
                                />
                                {award.award_type.description && (
                                    <TextBlock
                                        label="About this award"
                                        value={award.award_type.description}
                                    />
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </>
    );
}

AwardShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Awards', href: awardRoutes.index() },
        { title: 'Award Details', href: awardRoutes.index() },
    ],
};

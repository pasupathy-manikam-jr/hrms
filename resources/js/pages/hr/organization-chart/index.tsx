import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import organizationChartRoutes from '@/routes/hr/organization-chart';

type ChartNode = {
    id: number;
    name: string;
    email: string;
    designation: string | null;
    department: string | null;
    branch: string | null;
    employee_id: string | null;
    status: 'active' | 'inactive';
    children: ChartNode[];
};

// Levels shown open on first load (root + two rows below it), like the demo.
const OPEN_DEPTH = 2;

function PersonCard({
    node,
    root,
    open,
    onToggle,
}: {
    node: ChartNode;
    root: boolean;
    open: boolean;
    onToggle: () => void;
}) {
    const { t } = useTranslation();
    const getInitials = useInitials();
    const hasChildren = node.children.length > 0;

    return (
        <div
            className={cn(
                'relative mx-auto flex w-44 flex-col items-center rounded-xl border-2 bg-card px-3 pt-4 pb-5 text-center shadow-sm',
                root
                    ? 'border-primary'
                    : hasChildren
                      ? 'border-blue-300 dark:border-blue-800'
                      : 'border-border',
            )}
            title={[node.department, node.branch].filter(Boolean).join(' · ')}
        >
            <div className="relative">
                <Avatar className="size-12">
                    <AvatarFallback className="bg-primary/10 font-semibold text-primary">
                        {getInitials(node.name)}
                    </AvatarFallback>
                </Avatar>
                <span
                    className={cn(
                        'absolute end-0 bottom-0 size-3 rounded-full ring-2 ring-card',
                        node.status === 'active'
                            ? 'bg-emerald-500'
                            : 'bg-red-500',
                    )}
                    aria-label={t(
                        node.status === 'active' ? 'Active' : 'Inactive',
                    )}
                />
            </div>
            <div className="mt-2 w-full truncate text-sm font-semibold">
                {node.name}
            </div>
            <div className="w-full truncate text-xs text-muted-foreground">
                {node.email}
            </div>
            {node.designation && (
                <span className="mt-2 rounded-md border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                    {t(node.designation)}
                </span>
            )}
            {hasChildren && (
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={open}
                    aria-label={t(open ? 'Collapse' : 'Expand')}
                    className="absolute -bottom-3.5 left-1/2 flex size-7 -translate-x-1/2 items-center justify-center rounded-full bg-primary text-primary-foreground shadow ring-2 ring-card hover:opacity-90"
                >
                    {open ? (
                        <ChevronUp className="size-4" />
                    ) : (
                        <ChevronDown className="size-4" />
                    )}
                </button>
            )}
        </div>
    );
}

function TreeNode({
    node,
    depth,
    collapsed,
    toggle,
}: {
    node: ChartNode;
    depth: number;
    collapsed: Set<number>;
    toggle: (id: number) => void;
}) {
    const open =
        node.children.length > 0 &&
        (depth < OPEN_DEPTH ? !collapsed.has(node.id) : collapsed.has(node.id));

    return (
        <li>
            <PersonCard
                node={node}
                root={depth === 0}
                open={open}
                onToggle={() => toggle(node.id)}
            />
            {open && (
                <ul>
                    {node.children.map((child) => (
                        <TreeNode
                            key={child.id}
                            node={child}
                            depth={depth + 1}
                            collapsed={collapsed}
                            toggle={toggle}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function OrganizationChart({
    chartData,
    totalCount,
}: {
    chartData: ChartNode | ChartNode[];
    totalCount: number;
}) {
    const { t } = useTranslation();
    // Nodes whose default (open near the top, closed deeper down) the user has flipped.
    const [flipped, setFlipped] = useState<Set<number>>(new Set());
    const roots = Array.isArray(chartData) ? chartData : [chartData];
    const canvas = useRef<HTMLDivElement>(null);

    // Open centred on the root, like the demo (a wide tree scrolls both ways from there).
    useEffect(() => {
        const el = canvas.current;

        if (el) {
            el.scrollLeft = (el.scrollWidth - el.clientWidth) / 2;
        }
    }, []);

    const toggle = (id: number) =>
        setFlipped((current) => {
            const next = new Set(current);

            if (!next.delete(id)) {
                next.add(id);
            }

            return next;
        });

    return (
        <>
            <Head title={t('Organization Chart')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Organization Chart"
                    description="Visual hierarchy of your organization"
                />

                <div className="flex flex-wrap items-center gap-4 text-sm">
                    <span className="flex items-center gap-2 rounded-lg border bg-card px-4 py-2 font-semibold shadow-sm">
                        <Users className="size-4 text-primary" />
                        {t(':count Members', { count: totalCount })}
                    </span>
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <span className="size-2.5 rounded-full bg-emerald-500" />
                        {t('Active')}
                    </span>
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <span className="size-2.5 rounded-full bg-red-500" />
                        {t('Inactive')}
                    </span>
                </div>

                <div
                    ref={canvas}
                    className="overflow-auto rounded-xl border bg-card p-8 shadow-sm"
                >
                    {/* Sized to its content so a wide tree scrolls instead of clipping. */}
                    <ul className="org-tree mx-auto w-max">
                        {roots.map((root) => (
                            <TreeNode
                                key={root.id}
                                node={root}
                                depth={0}
                                collapsed={flipped}
                                toggle={toggle}
                            />
                        ))}
                    </ul>
                </div>
            </div>
        </>
    );
}

OrganizationChart.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Workforce Management', href: dashboard() },
        {
            title: 'Organization Chart',
            href: organizationChartRoutes.index(),
        },
    ],
};

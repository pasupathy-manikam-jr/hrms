import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    CalendarDays,
    Clock,
    FileText,
    TrendingUp,
    UserCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import HrmWordmark from '@/components/hrm-wordmark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

const stats: {
    label: string;
    value: string;
    progress: number;
    icon: LucideIcon;
}[] = [
    { label: 'Onboarding', value: '34', progress: 100, icon: UserCheck },
    { label: 'Attendance', value: '96%', progress: 92, icon: Clock },
    { label: 'Leaves', value: '12', progress: 66, icon: CalendarDays },
    { label: 'Payroll Done', value: '100%', progress: 100, icon: Award },
];

const features: { title: string; text: string; icon: LucideIcon }[] = [
    {
        title: 'Employee Database',
        text: 'Manage records, documents, and profiles securely.',
        icon: Users,
    },
    {
        title: 'Attendance & Leaves',
        text: 'Track work hours, shifts, and leave approvals.',
        icon: Clock,
    },
    {
        title: 'Payroll & Payslips',
        text: 'Automate salaries, allowances, and payslip generation.',
        icon: FileText,
    },
    {
        title: 'Performance & HR',
        text: 'Evaluate goals, feedback, and organizational growth.',
        icon: Award,
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name, globalSettings } = usePage().props;
    const { t } = useTranslation();

    return (
        <div className="flex min-h-dvh w-full flex-col bg-white md:h-dvh md:flex-row dark:bg-gray-900">
            <div className="flex w-full flex-col justify-between p-6 md:w-1/2 md:overflow-y-auto lg:p-8 xl:w-[45%]">
                <div className="flex justify-end md:hidden">
                    <LanguageSwitcher />
                </div>
                <Link href={home()} className="mx-auto mt-4">
                    <HrmWordmark />
                </Link>

                <div className="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center py-8">
                    <h1 className="mb-2 text-[28px] font-bold text-gray-900 dark:text-white">
                        {title && t(title)}
                    </h1>
                    <div className="mb-4 h-1 w-8 rounded-full bg-primary" />
                    <p className="mb-8 text-gray-500 dark:text-gray-400">
                        {description && t(description)}
                    </p>
                    {children}
                </div>

                <p className="text-center text-sm text-gray-400 dark:text-gray-500">
                    {globalSettings.footerText ??
                        `© ${new Date().getFullYear()} ${name}`}
                </p>
            </div>

            <div className="relative hidden flex-col overflow-hidden bg-gradient-to-br from-slate-50 to-emerald-50/60 px-12 py-12 md:flex md:w-1/2 xl:w-[55%] dark:from-gray-950 dark:to-gray-950">
                <div className="pointer-events-none absolute -top-20 -right-20 size-[600px] rounded-full bg-primary opacity-10 blur-3xl" />

                <div className="absolute end-6 top-6 z-20">
                    <LanguageSwitcher />
                </div>
                <div className="relative z-10 mx-auto w-full max-w-2xl">
                    <h2 className="mb-5 text-2xl leading-tight font-bold text-slate-800 lg:text-3xl xl:text-[40px] dark:text-white">
                        {t('Manage Your Workforce Smarter with')}{' '}
                        <span className="text-primary">{name}</span>
                    </h2>
                    <p className="mb-8 max-w-lg text-sm leading-relaxed text-slate-600 xl:text-lg dark:text-gray-300">
                        {t(
                            'Streamline employee management, attendance tracking, payroll, performance, and daily operations – all in one powerful platform.',
                        )}
                    </p>

                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_4px_20px_rgba(0,0,0,0.05)] xl:p-6 dark:border-gray-700 dark:bg-gray-900">
                        <div className="mb-4 flex items-center gap-3">
                            <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                <Users className="size-4 text-primary" />
                            </div>
                            <h3 className="font-bold text-gray-900 xl:text-lg dark:text-white">
                                {t('HR Workflow Overview')}
                            </h3>
                        </div>

                        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                            {stats.map(
                                ({ label, value, progress, icon: Icon }) => (
                                    <div
                                        key={label}
                                        className="rounded-lg border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                    >
                                        <div className="flex items-center gap-1 text-xs font-bold text-gray-900 dark:text-white">
                                            <Icon className="size-4 text-primary" />
                                            <span className="truncate">
                                                {t(label)}
                                            </span>
                                        </div>
                                        <div className="my-2 text-xl font-bold text-primary xl:text-2xl">
                                            {value}
                                        </div>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${progress}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                            <div className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                    <Users className="size-5 text-primary" />
                                </div>
                                <div>
                                    <div className="text-sm font-bold text-gray-900 dark:text-white">
                                        {t('Active Employees')}
                                    </div>
                                    <div className="text-3xl font-bold text-gray-400">
                                        142{' '}
                                        <span className="text-xs font-normal">
                                            {t('members')}
                                        </span>
                                    </div>
                                    <div className="flex items-center text-xs text-gray-500">
                                        <TrendingUp className="mr-1 size-3 text-primary" />
                                        <span className="mr-1 font-semibold text-primary">
                                            8.5%
                                        </span>
                                        {t('this quarter')}
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div>
                                    <div className="text-sm font-bold text-gray-900 dark:text-white">
                                        {t('Payroll Processed')}
                                    </div>
                                    <div className="text-3xl font-bold text-primary">
                                        RM 58,400
                                    </div>
                                    <div className="flex items-center text-xs text-gray-500">
                                        <TrendingUp className="mr-1 size-3 text-primary" />
                                        <span className="mr-1 font-semibold text-primary">
                                            100%
                                        </span>
                                        {t('on time')}
                                    </div>
                                </div>
                                <svg
                                    viewBox="0 0 100 50"
                                    className="h-12 w-24 text-primary"
                                    aria-hidden
                                >
                                    <polyline
                                        points="0,45 12,38 20,44 32,28 42,34 54,18 64,24 76,10 86,14 98,4"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="3"
                                        strokeLinejoin="round"
                                    />
                                    <circle
                                        cx="98"
                                        cy="4"
                                        r="3"
                                        fill="currentColor"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 grid grid-cols-2 gap-4 xl:grid-cols-4">
                        {features.map(
                            ({ title: feature, text, icon: Icon }) => (
                                <div key={feature} className="text-center">
                                    <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10">
                                        <Icon className="size-5 text-primary" />
                                    </div>
                                    <div className="mb-1 text-xs font-bold text-gray-900 dark:text-white">
                                        {t(feature)}
                                    </div>
                                    <p className="text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                                        {t(text)}
                                    </p>
                                </div>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

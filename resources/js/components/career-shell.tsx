import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import HrmWordmark from '@/components/hrm-wordmark';
import { useTranslation } from '@/hooks/use-translation';
import career from '@/routes/career';

export type CareerCompany = { name: string; email: string | null };

/** The public careers site frame: centred logo bar, page, dark footer with contact details. */
export function CareerShell({
    company,
    children,
}: {
    company: CareerCompany;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-svh flex-col bg-slate-50 text-foreground dark:bg-background">
            <header className="border-b bg-card shadow-sm">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-center px-4">
                    <Link href={career.index()} aria-label={t('All jobs')}>
                        <HrmWordmark className="h-9" />
                    </Link>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="bg-gray-900 text-gray-300">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2">
                    <div>
                        <div className="mb-3 text-lg font-semibold text-white">
                            {company.name}
                        </div>
                        <p>
                            {t(
                                'Building the future of work with innovative solutions and amazing people.',
                            )}
                        </p>
                    </div>
                    {company.email && (
                        <div>
                            <div className="mb-3 font-semibold text-white">
                                {t('Contact Info')}
                            </div>
                            <p>
                                {t('Email')}:{' '}
                                <a
                                    href={`mailto:${company.email}`}
                                    className="hover:text-white"
                                >
                                    {company.email}
                                </a>
                            </p>
                        </div>
                    )}
                </div>
                <div className="mx-auto max-w-6xl border-t border-gray-800 px-4 py-6 text-center text-sm">
                    © {new Date().getFullYear()} {company.name}
                </div>
            </footer>
        </div>
    );
}

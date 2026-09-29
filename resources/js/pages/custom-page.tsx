import { Head } from '@inertiajs/react';
import {
    LandingFooter,
    LandingHeader,
    LandingShell,
} from '@/components/landing-shell';
import type {
    CustomPageLink,
    LandingSections,
} from '@/components/landing-shell';
import { useTranslation } from '@/hooks/use-translation';
import { home } from '@/routes';

export default function CustomPage({
    page,
    footer,
    customPages,
}: {
    page: {
        title: string;
        /** Allow-list sanitized on the server (App\Support\HtmlSanitizer). */
        content: string;
        meta_title: string | null;
        meta_description: string | null;
    };
    footer: LandingSections['footer'] | null;
    customPages: CustomPageLink[];
}) {
    const { t } = useTranslation();
    const base = home().url;

    return (
        <>
            <Head title={page.meta_title || page.title}>
                {page.meta_description && (
                    <meta name="description" content={page.meta_description} />
                )}
            </Head>

            <LandingShell>
                <LandingHeader base={base} pages={customPages} />

                <main className="bg-slate-50 py-20 dark:bg-gray-950">
                    <article className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                        <h1 className="mb-8 text-4xl font-bold tracking-tight md:text-5xl">
                            {t(page.title)}
                        </h1>
                        <div
                            className="rounded-xl border border-gray-200 bg-white p-8 leading-relaxed text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 [&_a]:text-primary [&_a]:underline [&_b]:text-gray-900 dark:[&_b]:text-white [&_h2]:mt-6 [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-4 [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:mb-4 [&_strong]:text-gray-900 dark:[&_strong]:text-white [&_ul]:list-disc [&_ul]:ps-6"
                            dangerouslySetInnerHTML={{ __html: page.content }}
                        />
                    </article>
                </main>

                {footer && (
                    <LandingFooter
                        base={base}
                        footer={footer}
                        pages={customPages}
                    />
                )}
            </LandingShell>
        </>
    );
}

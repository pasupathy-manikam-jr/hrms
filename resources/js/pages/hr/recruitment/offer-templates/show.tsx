import { Head } from '@inertiajs/react';
import { Braces, CalendarDays, FileText } from 'lucide-react';
import { DetailPage, Summary, SummaryIcon } from '@/components/detail-page';
import { Badge } from '@/components/ui/badge';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import offerTemplateRoutes from '@/routes/hr/recruitment/offer-templates';

type OfferTemplate = {
    id: number;
    name: string;
    template_content: string;
    variables: string[] | null;
    status: string;
    created_at: string;
};

export default function OfferTemplateShow({
    offerTemplate: tpl,
}: {
    offerTemplate: OfferTemplate;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const variables = tpl.variables ?? [];

    return (
        <>
            <Head title={tpl.name} />
            <DetailPage
                title={tpl.name}
                description="View the offer template and its placeholders."
                back={offerTemplateRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={FileText} />}
                        title={tpl.name}
                        status={tpl.status}
                        facts={[
                            [
                                Braces,
                                t(':count placeholders', {
                                    count: variables.length,
                                }),
                            ],
                            [
                                CalendarDays,
                                `${t('Created At')}: ${date(tpl.created_at)}`,
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Content',
                        heading: 'Template Content',
                        content: (
                            // Sandboxed and escaped: shown exactly as written.
                            <iframe
                                title={t('Template Content')}
                                sandbox=""
                                className="h-[60dvh] w-full rounded-md border bg-white"
                                srcDoc={`<body style="font-family:system-ui,sans-serif;font-size:14px;white-space:pre-wrap;margin:16px;color:#111">${tpl.template_content
                                    .replace(/&/g, '&amp;')
                                    .replace(/</g, '&lt;')}</body>`}
                            />
                        ),
                    },
                    {
                        label: 'Placeholders',
                        content:
                            variables.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('This template has no placeholders.')}
                                </p>
                            ) : (
                                <div className="grid gap-4">
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'These are filled from the offer when its letter is shown.',
                                        )}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {variables.map((v) => (
                                            <Badge
                                                key={v}
                                                variant="secondary"
                                                className="font-mono"
                                            >
                                                {`{{${v}}}`}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            ),
                    },
                ]}
            />
        </>
    );
}

OfferTemplateShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Recruitment', href: offerTemplateRoutes.index() },
        { title: 'Offer Templates', href: offerTemplateRoutes.index() },
        {
            title: 'Offer Template Details',
            href: offerTemplateRoutes.index(),
        },
    ],
};

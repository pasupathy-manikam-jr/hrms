import { Head, useForm, useHttp } from '@inertiajs/react';
import { Code, Eye, Languages, Mail } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    DetailPage,
    RecordList,
    Summary,
    SummaryIcon,
} from '@/components/detail-page';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import templateRoutes from '@/routes/email-templates';

type Translation = {
    id: number;
    lang: string;
    subject: string;
    content: string;
};

type Template = {
    id: number;
    name: string;
    from: string | null;
    email_template_langs: Translation[];
};

type Preview = { subject: string; content: string };

const textareaClass =
    'min-h-80 rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function EmailTemplateEditor({
    template,
    languages,
    placeholders,
}: {
    template: Template;
    languages: { code: string; name: string }[];
    placeholders: string[];
}) {
    const { t } = useTranslation();
    const contentRef = useRef<HTMLTextAreaElement>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const http = useHttp<Preview, Preview>({ subject: '', content: '' });

    const translation = (lang: string) =>
        template.email_template_langs.find((l) => l.lang === lang);
    const form = useForm({
        lang: 'en',
        from: template.from ?? '',
        subject: translation('en')?.subject ?? '',
        content: translation('en')?.content ?? '',
    });

    const switchLanguage = (lang: string) => {
        setPreview(null);
        form.setData({
            ...form.data,
            lang,
            subject: translation(lang)?.subject ?? '',
            content: translation(lang)?.content ?? '',
        });
    };

    // Insert "{name}" at the cursor (or the end) of the body.
    const insertPlaceholder = (name: string) => {
        const el = contentRef.current;
        const at = el?.selectionStart ?? form.data.content.length;
        const token = `{${name}}`;
        form.setData(
            'content',
            form.data.content.slice(0, at) +
                token +
                form.data.content.slice(at),
        );
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(at + token.length, at + token.length);
        });
    };

    const loadPreview = async () => {
        http.transform(() => ({
            subject: form.data.subject,
            content: form.data.content,
        }));
        setPreview(await http.post(templateRoutes.preview.url()));
    };

    const editor = (
        <form
            noValidate
            className="grid gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.put(templateRoutes.update.url(template.id), {
                    preserveScroll: true,
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="tpl-lang">{t('Language')}</Label>
                    <SelectField
                        id="tpl-lang"
                        value={form.data.lang}
                        onChange={(e) => switchLanguage(e.target.value)}
                    >
                        {languages.map((l) => (
                            <option key={l.code} value={l.code}>
                                {l.name}
                                {translation(l.code)
                                    ? ''
                                    : ` (${t('not translated')})`}
                            </option>
                        ))}
                    </SelectField>
                    <InputError message={form.errors.lang} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="tpl-from">{t('From')}</Label>
                    <Input
                        id="tpl-from"
                        value={form.data.from}
                        onChange={(e) => form.setData('from', e.target.value)}
                    />
                    <InputError message={form.errors.from} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="tpl-subject">
                    {t('Subject')}
                    <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="tpl-subject"
                    required
                    value={form.data.subject}
                    onChange={(e) => form.setData('subject', e.target.value)}
                />
                <InputError message={form.errors.subject} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="tpl-content">
                    {t('Body (HTML)')}
                    <span className="text-destructive">*</span>
                </Label>
                <textarea
                    id="tpl-content"
                    ref={contentRef}
                    required
                    dir="auto"
                    className={textareaClass}
                    value={form.data.content}
                    onChange={(e) => form.setData('content', e.target.value)}
                />
                <InputError message={form.errors.content} />
            </div>
            <div className="grid gap-2">
                <div className="text-sm text-muted-foreground">
                    {t('Placeholders')} · {t('Click to insert at the cursor.')}
                </div>
                <div className="flex flex-wrap gap-2">
                    {placeholders.map((p) => (
                        <Button
                            key={p}
                            type="button"
                            variant="outline"
                            size="sm"
                            className="font-mono text-xs"
                            onClick={() => insertPlaceholder(p)}
                        >
                            {`{${p}}`}
                        </Button>
                    ))}
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>
                    {t('Save')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={http.processing}
                    onClick={loadPreview}
                >
                    <Eye /> {t('Preview')}
                </Button>
            </div>
            {preview && (
                <section className="grid gap-2">
                    <h4 className="text-sm font-medium">
                        {t('Preview')}:{' '}
                        <span className="font-normal">{preview.subject}</span>
                    </h4>
                    {/* Sandboxed: template HTML can't run scripts or reach the app. */}
                    <iframe
                        title={t('Preview')}
                        sandbox=""
                        className="h-[60dvh] w-full rounded-md border bg-white"
                        srcDoc={`<body style="font-family:system-ui,sans-serif;font-size:14px;margin:16px;color:#111">${preview.content}</body>`}
                    />
                </section>
            )}
        </form>
    );

    return (
        <>
            <Head title={t(template.name)} />
            <DetailPage
                title={template.name}
                description="Placeholders in curly braces are replaced when the email is sent."
                back={templateRoutes.index()}
                summary={
                    <Summary
                        media={<SummaryIcon icon={Mail} />}
                        title={t(template.name)}
                        subtitle={template.from}
                        facts={[
                            [
                                Languages,
                                t(':count of :total languages translated', {
                                    count: template.email_template_langs.length,
                                    total: languages.length,
                                }),
                            ],
                            [
                                Code,
                                t(':count placeholders', {
                                    count: placeholders.length,
                                }),
                            ],
                        ]}
                    />
                }
                tabs={[
                    {
                        label: 'Template',
                        heading: 'Edit Template',
                        content: editor,
                    },
                    {
                        label: 'Translations',
                        content: (
                            <RecordList
                                items={languages.map((l, id) => ({
                                    id,
                                    ...l,
                                    subject: translation(l.code)?.subject,
                                }))}
                                empty="No languages"
                                render={(l) => (
                                    <>
                                        <div className="min-w-0">
                                            <div className="font-medium">
                                                {l.name}
                                            </div>
                                            <div className="truncate text-sm text-muted-foreground">
                                                {l.subject ?? '-'}
                                            </div>
                                        </div>
                                        <StatusBadge
                                            status={
                                                l.subject
                                                    ? 'Translated'
                                                    : 'Not translated'
                                            }
                                        />
                                    </>
                                )}
                            />
                        ),
                    },
                ]}
            />
        </>
    );
}

EmailTemplateEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Email Templates', href: templateRoutes.index() },
        { title: 'Template Details', href: templateRoutes.index() },
    ],
};

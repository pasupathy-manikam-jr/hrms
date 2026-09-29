import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { settings } from '@/routes/landing-page';
import pageRoutes from '@/routes/landing-page/custom-pages';

type CustomPage = {
    id: number;
    title: string;
    slug: string;
    content: string;
    meta_title: string | null;
    meta_description: string | null;
    is_active: boolean;
    sort_order: number;
};

// Mirrors Str::slug on the server, which has the final say.
const slugify = (value: string) =>
    value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const textareaClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

export default function CustomPageForm({ page }: { page: CustomPage | null }) {
    const { t } = useTranslation();
    const form = useForm({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        content: page?.content ?? '',
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
        is_active: page?.is_active ?? true,
        sort_order: page?.sort_order ?? 0,
    });
    // Keep generating the slug from the title until the user edits it.
    const [slugTouched, setSlugTouched] = useState(page !== null);
    const title = page ? 'Edit Page' : 'Add Page';

    return (
        <>
            <Head title={t(title)} />
            <form
                noValidate
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        page ? pageRoutes.update(page.id) : pageRoutes.store(),
                    );
                }}
            >
                <PageHeader
                    title={title}
                    description="Content accepts simple HTML: b, strong, i, em, br, p, ul, ol, li, a, h2 and h3. Anything else is removed."
                    action={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={pageRoutes.index()}>
                                    {t('Cancel')}
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {t('Save')}
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-4 rounded-xl border bg-card p-6 shadow-sm sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="page-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="page-title"
                            required
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData((data) => ({
                                    ...data,
                                    title: e.target.value,
                                    slug: slugTouched
                                        ? data.slug
                                        : slugify(e.target.value),
                                }))
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="page-slug">{t('Slug')}</Label>
                        <Input
                            id="page-slug"
                            value={form.data.slug}
                            onChange={(e) => {
                                setSlugTouched(true);
                                form.setData('slug', e.target.value);
                            }}
                        />
                        <InputError message={form.errors.slug} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="page-content">
                            {t('Content')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="page-content"
                            required
                            rows={14}
                            className={textareaClass}
                            value={form.data.content}
                            onChange={(e) =>
                                form.setData('content', e.target.value)
                            }
                        />
                        <InputError message={form.errors.content} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="page-meta-title">
                            {t('Meta Title')}
                        </Label>
                        <Input
                            id="page-meta-title"
                            value={form.data.meta_title}
                            onChange={(e) =>
                                form.setData('meta_title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.meta_title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="page-sort-order">
                            {t('Sort Order')}
                        </Label>
                        <Input
                            id="page-sort-order"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData(
                                    'sort_order',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.sort_order} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="page-meta-description">
                            {t('Meta Description')}
                        </Label>
                        <textarea
                            id="page-meta-description"
                            rows={2}
                            className={textareaClass}
                            value={form.data.meta_description}
                            onChange={(e) =>
                                form.setData('meta_description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.meta_description} />
                    </div>
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <Switch
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        {t('Active')}
                    </label>
                </div>
            </form>
        </>
    );
}

CustomPageForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Landing Page', href: settings() },
        { title: 'Custom Pages', href: pageRoutes.index() },
    ],
};

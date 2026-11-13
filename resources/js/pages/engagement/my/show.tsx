import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import myRoutes from '@/routes/engagement/my';

type Statement = { id: number; text: string; element: string | null };

export default function AnswerSurvey({
    campaign,
    statements,
    ratings,
}: {
    campaign: { id: number; name: string; description: string | null };
    statements: Statement[];
    ratings: { value: number; label: string }[];
}) {
    const { t } = useTranslation();
    const form = useForm<{ answers: Record<number, number> }>({ answers: {} });
    const answered = Object.keys(form.data.answers).length;
    const errors = form.errors as Record<string, string | undefined>;

    // Statements arrive grouped by element; show a heading whenever the element changes.
    const sections = statements.reduce<
        { element: string | null; statements: Statement[] }[]
    >((groups, statement) => {
        const last = groups[groups.length - 1];

        if (last && last.element === statement.element) {
            last.statements.push(statement);
        } else {
            groups.push({
                element: statement.element,
                statements: [statement],
            });
        }

        return groups;
    }, []);
    let number = 0;

    return (
        <>
            <Head title={campaign.name} />
            <form
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(myRoutes.store(campaign.id).url, {
                        preserveScroll: true,
                    });
                }}
            >
                <PageHeader
                    title={campaign.name}
                    description={
                        campaign.description ??
                        'Tell us how much you agree with each statement. There are no right or wrong answers.'
                    }
                    action={
                        <Button variant="outline" asChild>
                            <Link href={myRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                {sections.map((section) => (
                    <section
                        key={`${section.element}-${section.statements[0].id}`}
                        className="rounded-xl border bg-card shadow-sm"
                    >
                        <h2 className="border-b px-5 py-3 font-semibold">
                            {section.element ?? t('Overall')}
                        </h2>
                        <ol className="divide-y">
                            {section.statements.map((statement) => {
                                number += 1;
                                const error = errors[`answers.${statement.id}`];

                                return (
                                    <li
                                        key={statement.id}
                                        className="grid gap-3 px-5 py-4 md:grid-cols-[1fr_auto] md:items-center"
                                    >
                                        <div>
                                            <span className="me-2 text-muted-foreground">
                                                {number}.
                                            </span>
                                            {statement.text}
                                            <InputError message={error} />
                                        </div>
                                        <div
                                            role="radiogroup"
                                            aria-label={statement.text}
                                            className="grid grid-cols-4 gap-1"
                                        >
                                            {ratings.map((rating) => {
                                                const checked =
                                                    form.data.answers[
                                                        statement.id
                                                    ] === rating.value;

                                                return (
                                                    <label
                                                        key={rating.value}
                                                        className={`flex min-w-20 cursor-pointer flex-col items-center gap-1 rounded-md border px-2 py-2 text-center text-xs ${checked ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'}`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            className="sr-only"
                                                            name={`statement-${statement.id}`}
                                                            value={rating.value}
                                                            checked={checked}
                                                            onChange={() =>
                                                                form.setData(
                                                                    'answers',
                                                                    {
                                                                        ...form
                                                                            .data
                                                                            .answers,
                                                                        [statement.id]:
                                                                            rating.value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        {t(rating.label)}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                ))}

                <div className="sticky bottom-0 flex items-center justify-between gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <span className="text-sm text-muted-foreground">
                        {t(':answered of :total answered', {
                            answered,
                            total: statements.length,
                        })}
                    </span>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || answered < statements.length
                        }
                    >
                        {t('Submit answers')}
                    </Button>
                </div>
            </form>
        </>
    );
}

AnswerSurvey.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My Surveys', href: myRoutes.index() },
    ],
};

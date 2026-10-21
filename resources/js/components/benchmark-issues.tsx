import { router } from '@inertiajs/react';
import { CircleCheck, TriangleAlert, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import participantRoutes from '@/routes/benchmark/participants';

/** One finding on a submission; `key` is set when it can be accepted as it stands. */
export type Issue = {
    type: 'error' | 'warning';
    message: string;
    target: string | null;
    rule: string | null;
    key: string | null;
    accepted: boolean;
};

/** Answers that failed a check, as the company typed them, keyed "target.field" (e.g. "row:7.median_salary"). */
export type TypedAnswers = Record<
    string,
    { value: string; message: string; rule: string | null }
>;

/** An answer as the company typed it, shown where the saved value is empty because it failed a check. */
export function Typed({ value }: { value: string }) {
    const { t } = useTranslation();

    return (
        <span
            className="text-destructive underline decoration-dotted"
            title={t('As typed in the workbook; it failed a check')}
        >
            {value}
        </span>
    );
}

/** Shows the saved value, or else the typed answer that failed a check. */
export function answer(
    typed: TypedAnswers,
    key: string,
    value: ReactNode,
): ReactNode {
    if (value !== null && value !== undefined && value !== '') {
        return value;
    }

    return typed[key] ? <Typed value={typed[key].value} /> : value;
}

/** The action-column marker for a row's findings: red for open errors, amber for warnings, a tick once all accepted. */
export function IssueButton({
    issues,
    onClick,
}: {
    issues: Issue[];
    onClick: () => void;
}) {
    const { t } = useTranslation();
    const open = issues.filter((issue) => !issue.accepted);

    if (issues.length === 0) {
        return null;
    }

    const errors = open.some((issue) => issue.type === 'error');

    return (
        <Button
            variant="ghost"
            size="sm"
            className={cn(
                'gap-1 px-2',
                open.length === 0
                    ? 'text-emerald-600'
                    : errors
                      ? 'text-destructive'
                      : 'text-amber-600',
            )}
            aria-label={t('Show issues')}
            onClick={onClick}
        >
            {open.length === 0 ? <CircleCheck /> : <TriangleAlert />}
            {open.length > 0 && open.length}
        </Button>
    );
}

/**
 * Findings with their actions: accept one as it stands, accept the same check wherever else it is open,
 * or undo an acceptance. Errors that can't be accepted must be corrected (Edit).
 */
export function IssueActions({
    issues,
    all,
    participantId,
    canAccept,
}: {
    issues: Issue[];
    all: Issue[];
    participantId: number;
    canAccept: boolean;
}) {
    const { t } = useTranslation();
    const send = (keys: string[], accepted: boolean) =>
        router.post(
            participantRoutes.accept.url(participantId),
            { keys, accepted },
            { preserveScroll: true },
        );
    const ordered = [...issues].sort(
        (a, b) =>
            Number(a.accepted) - Number(b.accepted) ||
            Number(a.type === 'warning') - Number(b.type === 'warning'),
    );

    return (
        <ul className="grid gap-2 text-sm [overflow-wrap:anywhere]">
            {ordered.map((issue, index) => {
                const sameCheck = all.filter(
                    (other) =>
                        other.rule === issue.rule &&
                        other.key !== null &&
                        !other.accepted,
                );

                return (
                    <li
                        key={index}
                        className={cn(
                            'grid gap-2 rounded-lg border p-3',
                            issue.accepted
                                ? 'border-dashed text-muted-foreground'
                                : issue.type === 'error'
                                  ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                                  : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
                        )}
                    >
                        <div>
                            {issue.accepted && (
                                <span className="me-1 font-medium">
                                    {t('Accepted:')}
                                </span>
                            )}
                            {issue.message}
                        </div>
                        {canAccept && issue.key && (
                            <div className="flex flex-wrap gap-2">
                                {issue.accepted ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            send([issue.key as string], false)
                                        }
                                    >
                                        <Undo2 /> {t('Undo')}
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                send(
                                                    [issue.key as string],
                                                    true,
                                                )
                                            }
                                        >
                                            <CircleCheck /> {t('Accept')}
                                        </Button>
                                        {sameCheck.length > 1 && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send(
                                                        sameCheck.map(
                                                            (other) =>
                                                                other.key as string,
                                                        ),
                                                        true,
                                                    )
                                                }
                                            >
                                                <CircleCheck />{' '}
                                                {t(
                                                    'Accept on all :n rows with this check',
                                                    { n: sameCheck.length },
                                                )}
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        )}
                        {canAccept &&
                            !issue.key &&
                            !issue.accepted &&
                            issue.type === 'error' && (
                                <div className="text-xs opacity-80">
                                    {t(
                                        'Must be corrected (Edit); the analytics cannot use it as it is.',
                                    )}
                                </div>
                            )}
                    </li>
                );
            })}
        </ul>
    );
}

/** A row's (or field group's) findings in a dialog, with an optional Edit shortcut. */
export function IssuesDialog({
    title,
    issues,
    all,
    participantId,
    canAccept,
    onEdit,
    onClose,
}: {
    title: string;
    issues: Issue[];
    all: Issue[];
    participantId: number;
    canAccept: boolean;
    onEdit?: () => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[90dvh] flex-col sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <div className="-mx-6 min-h-0 flex-1 overflow-y-auto px-6">
                    <IssueActions
                        issues={issues}
                        all={all}
                        participantId={participantId}
                        canAccept={canAccept}
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('Close')}
                    </Button>
                    {onEdit && (
                        <Button onClick={onEdit}>{t('Edit to correct')}</Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

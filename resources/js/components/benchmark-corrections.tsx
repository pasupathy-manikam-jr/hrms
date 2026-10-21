import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormDialog } from '@/components/form-dialog';
import type { TypedAnswers } from '@/components/benchmark-issues';
import InputError from '@/components/input-error';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import participantRoutes from '@/routes/benchmark/participants';
import rowRoutes from '@/routes/benchmark/participants/rows';

/** The cycle's dropdown lists, keyed by the template's list name (IndustryList, YesNo, …). */
export type Lookups = Record<string, string[]>;

type Answers = Record<string, unknown>;

/**
 * Form values from the saved answers; an answer that failed a check starts as the company typed it, so it can be
 * seen and corrected (sent back unchanged, it stays as typed and flagged). Blank inputs are sent as null and Yes/No
 * selects as booleans.
 */
const toForm = (
    answers: Answers,
    keys: string[],
    typed: TypedAnswers = {},
    targetOf: (key: string) => string = () => '',
) =>
    Object.fromEntries(
        keys.map((key) => {
            const value = answers[key];
            const asTyped = typed[`${targetOf(key)}.${key}`];

            if (asTyped) {
                return [key, asTyped.value];
            }

            return [
                key,
                typeof value === 'boolean'
                    ? value
                        ? 'Yes'
                        : 'No'
                    : typeof value === 'string' || typeof value === 'number'
                      ? String(value)
                      : '',
            ];
        }),
    ) as Record<string, string>;

const yesNo = (value: string) => (value === '' ? null : value === 'Yes');

function Field({
    id,
    label,
    error,
    wide,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    wide?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className={wide ? 'grid gap-2 sm:col-span-2' : 'grid gap-2'}>
            <Label htmlFor={id}>{t(label)}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

/** A dropdown from one of the cycle's lists, with "—" for no answer and the typed answer if it isn't on the list. */
function Choice({
    id,
    value,
    options,
    onChange,
}: {
    id: string;
    value: string;
    options: string[];
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <SelectField
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">—</option>
            {value !== '' && !options.includes(value) && (
                <option value={value}>
                    {value} {t('(not allowed)')}
                </option>
            )}
            {options.map((option) => (
                <option key={option} value={option}>
                    {option}
                </option>
            ))}
        </SelectField>
    );
}

type DialogProps = { open: boolean; onOpenChange: (open: boolean) => void };

const PROFILE_FIELDS: [string, string, string | null][] = [
    ['company_name', 'Company Name', null],
    ['industry', 'Industry', 'IndustryList'],
    ['state', 'State (HQ)', 'StateList'],
    ['employee_band', 'Number of Employees', 'EmployeeBandList'],
    ['revenue_band', 'Annual Revenue', 'RevenueBandList'],
    ['ownership_type', 'Ownership', 'OwnershipTypeList'],
    ['locations', 'Operating Locations', null],
    ['listed_status', 'Listed Status', 'ListedStatusList'],
    ['unionised', 'Unionised Workforce', 'YesNo'],
    ['consented', 'Consent Given', 'YesNo'],
    ['authorised_name', 'Authorised Person', null],
    ['authorised_designation', 'Designation', null],
    ['consent_date', 'Consent Date', null],
];

export function ProfileDialog({
    participantId,
    answers,
    typed,
    lookups,
    ...dialog
}: DialogProps & {
    participantId: number;
    answers: Answers;
    typed: TypedAnswers;
    lookups: Lookups;
}) {
    const form = useForm(
        toForm(
            { ...answers, consent_date: answers.consent_date ?? null },
            PROFILE_FIELDS.map(([key]) => key),
            typed,
            (key) => (key === 'consent_date' ? 'consent' : 'profile'),
        ),
    );

    return (
        <FormDialog
            {...dialog}
            title="Edit Company Profile & Consent"
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    consented: yesNo(data.consented) ?? false,
                }));
                form.put(participantRoutes.profile.url(participantId), {
                    preserveScroll: true,
                    onSuccess: () => dialog.onOpenChange(false),
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {PROFILE_FIELDS.map(([key, label, list]) => (
                    <Field
                        key={key}
                        id={`profile-${key}`}
                        label={label}
                        error={form.errors[key]}
                    >
                        {list ? (
                            <Choice
                                id={`profile-${key}`}
                                value={form.data[key]}
                                options={lookups[list] ?? []}
                                onChange={(value) => form.setData(key, value)}
                            />
                        ) : (
                            <Input
                                id={`profile-${key}`}
                                // A typed date that isn't a real date stays editable as text.
                                type={
                                    key === 'consent_date' &&
                                    /^(\d{4}-\d{2}-\d{2})?$/.test(
                                        form.data[key],
                                    )
                                        ? 'date'
                                        : 'text'
                                }
                                inputMode={
                                    key === 'locations' ? 'numeric' : undefined
                                }
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        )}
                    </Field>
                ))}
            </div>
        </FormDialog>
    );
}

const ROW_FIELDS: [string, string, string | null][] = [
    ['job_title', 'Standard Job Title', null],
    ['own_title', 'Own Job Title', null],
    ['job_level', 'Job Level', 'JobLevelList'],
    ['headcount', 'Total Headcount', null],
    ['male', 'Male', null],
    ['female', 'Female', null],
    ['tenure_under_1', 'Tenure <1 yr', null],
    ['tenure_1_2', 'Tenure 1-2 yrs', null],
    ['tenure_3_4', 'Tenure 3-4 yrs', null],
    ['tenure_5_plus', 'Tenure 5+ yrs', null],
    ['experience_required', 'Typical Experience', 'ExperienceRequiredList'],
    ['shift_based', 'Shift-Based Role?', 'YesNo'],
    ['min_salary', 'Min Base Salary', null],
    ['median_salary', 'Median Base Salary', null],
    ['max_salary', 'Max Base Salary', null],
    ['avg_male', 'Avg. Male Base Salary', null],
    ['avg_female', 'Avg. Female Base Salary', null],
    ['guaranteed_bonus_months', 'Guaranteed Bonus (months)', null],
    ['allowance_transport', 'Transport', null],
    ['allowance_meal', 'Meal', null],
    ['allowance_housing', 'Housing', null],
    ['allowance_shift', 'Shift / Night Differential', null],
    ['allowance_phone', 'Phone / Internet', null],
    ['allowance_overtime', 'Overtime', null],
    ['allowance_outstation', 'Outstation / Travel', null],
    ['allowance_other', 'Other Allowance', null],
    ['other_allowance_note', 'Other Allowance Note', null],
];

const TEXT_ROW_FIELDS = ['job_title', 'own_title', 'other_allowance_note'];
const COUNT_FIELDS = [
    'headcount',
    'male',
    'female',
    'tenure_under_1',
    'tenure_1_2',
    'tenure_3_4',
    'tenure_5_plus',
];

/** Adds a salary row (row = null) or corrects one. */
export function SalaryRowDialog({
    participantId,
    row,
    typed,
    lookups,
    jobTitles,
    ...dialog
}: DialogProps & {
    participantId: number;
    row: (Answers & { id: number; line: number | null }) | null;
    typed: TypedAnswers;
    lookups: Lookups;
    jobTitles: string[];
}) {
    const { t } = useTranslation();
    const form = useForm(
        toForm(
            row ?? Object.fromEntries(COUNT_FIELDS.map((key) => [key, 0])),
            ROW_FIELDS.map(([key]) => key),
            row ? typed : {},
            () => `row:${row?.line ?? 0}`,
        ),
    );

    return (
        <FormDialog
            {...dialog}
            title={row ? 'Edit Salary Row' : 'Add Salary Row'}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    shift_based: yesNo(data.shift_based),
                }));
                const options = {
                    preserveScroll: true,
                    onSuccess: () => dialog.onOpenChange(false),
                };

                if (row) {
                    form.put(
                        rowRoutes.update.url({
                            participant: participantId,
                            salaryRow: row.id,
                        }),
                        options,
                    );
                } else {
                    form.post(rowRoutes.store.url(participantId), options);
                }
            }}
        >
            <datalist id="benchmark-job-titles">
                {jobTitles.map((title) => (
                    <option key={title} value={title} />
                ))}
            </datalist>
            <p className="text-sm text-muted-foreground">
                {t(
                    'Type to search the Standard Job Titles. Male + Female and the tenure columns must each add up to the Total Headcount.',
                )}
            </p>
            <div className="grid gap-4 sm:grid-cols-3">
                {ROW_FIELDS.map(([key, label, list]) => (
                    <Field
                        key={key}
                        id={`row-${key}`}
                        label={label}
                        error={form.errors[key]}
                        wide={key === 'job_title'}
                    >
                        {list ? (
                            <Choice
                                id={`row-${key}`}
                                value={form.data[key]}
                                options={lookups[list] ?? []}
                                onChange={(value) => form.setData(key, value)}
                            />
                        ) : (
                            <Input
                                id={`row-${key}`}
                                // Figures are text inputs so an answer typed as words or a formula can be shown.
                                inputMode={
                                    TEXT_ROW_FIELDS.includes(key)
                                        ? undefined
                                        : 'decimal'
                                }
                                list={
                                    key === 'job_title'
                                        ? 'benchmark-job-titles'
                                        : undefined
                                }
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        )}
                    </Field>
                ))}
            </div>
        </FormDialog>
    );
}

type Benefit = {
    item: string;
    same_for_all: boolean | null;
    company_value: string | null;
    exec_value: string | null;
    manager_value: string | null;
    remarks: string | null;
};

const BENEFIT_COLUMNS = [
    'company_value',
    'exec_value',
    'manager_value',
    'remarks',
] as const;

/** Every benefit question in one table; questions left blank are not saved. */
export function BenefitsDialog({
    participantId,
    benefits,
    typed,
    labels,
    ...dialog
}: DialogProps & {
    participantId: number;
    benefits: Benefit[];
    typed: TypedAnswers;
    labels: Record<string, string>;
}) {
    const { t } = useTranslation();
    const form = useForm({
        benefits: Object.keys(labels).map((item) => {
            const saved = benefits.find((b) => b.item === item);

            return {
                item,
                same_for_all:
                    typed[`benefit:${item}.same_for_all`]?.value ??
                    (saved?.same_for_all === null ||
                    saved?.same_for_all === undefined
                        ? ''
                        : saved.same_for_all
                          ? 'Yes'
                          : 'No'),
                company_value: saved?.company_value ?? '',
                exec_value: saved?.exec_value ?? '',
                manager_value: saved?.manager_value ?? '',
                remarks: saved?.remarks ?? '',
            };
        }),
    });
    const set = (index: number, key: string, value: string) =>
        form.setData(
            'benefits',
            form.data.benefits.map((b, i) =>
                i === index ? { ...b, [key]: value } : b,
            ),
        );
    const firstError = Object.values(form.errors)[0];

    return (
        <FormDialog
            {...dialog}
            title="Edit Benefits"
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    benefits: data.benefits.map((b) => ({
                        ...b,
                        same_for_all: yesNo(b.same_for_all),
                    })),
                }));
                form.put(participantRoutes.benefits.url(participantId), {
                    preserveScroll: true,
                    onSuccess: () => dialog.onOpenChange(false),
                });
            }}
        >
            <InputError message={firstError} />
            <div className="grid gap-4">
                {form.data.benefits.map((b, index) => (
                    <fieldset
                        key={b.item}
                        className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2"
                    >
                        <legend className="px-1 text-sm font-medium">
                            {t(labels[b.item])}
                        </legend>
                        <Field
                            id={`benefit-${b.item}-same`}
                            label="Same for all job levels?"
                        >
                            <Choice
                                id={`benefit-${b.item}-same`}
                                value={b.same_for_all}
                                options={['Yes', 'No']}
                                onChange={(value) =>
                                    set(index, 'same_for_all', value)
                                }
                            />
                        </Field>
                        {BENEFIT_COLUMNS.map((column) => (
                            <Field
                                key={column}
                                id={`benefit-${b.item}-${column}`}
                                label={
                                    {
                                        company_value: 'All Levels',
                                        exec_value: 'Non-Exec / Executive',
                                        manager_value: 'Managerial & Above',
                                        remarks: 'Remarks',
                                    }[column]
                                }
                            >
                                <Input
                                    id={`benefit-${b.item}-${column}`}
                                    value={b[column]}
                                    onChange={(e) =>
                                        set(index, column, e.target.value)
                                    }
                                />
                            </Field>
                        ))}
                    </fieldset>
                ))}
            </div>
        </FormDialog>
    );
}

/** Attrition & Hiring questions: [field, label, dropdown list (or "number" / "text")]. */
const ATTRITION_FIELDS: [string, string, string][] = [
    ['attrition_rate', 'Overall staff attrition rate (%)', 'number'],
    ['new_hire_attrition_rate', 'New-hire attrition rate (%)', 'number'],
    ['retirements', 'Retirements (headcount)', 'number'],
    [
        'involuntary_terminations',
        'Involuntary terminations (headcount)',
        'number',
    ],
    ['contract_non_renewals', 'Contract non-renewals (headcount)', 'number'],
    ['hardest_to_hire_1', 'Hardest to hire - Rank 1', 'JobFamilyList'],
    ['hardest_to_hire_2', 'Hardest to hire - Rank 2', 'JobFamilyList'],
    ['hardest_to_hire_3', 'Hardest to hire - Rank 3', 'JobFamilyList'],
    ['time_to_fill_days', 'Average time-to-fill (days)', 'number'],
    ['headcount_plan', 'Headcount plan (next 12 months)', 'HeadcountPlanList'],
    ['hardest_to_retain_1', 'Hardest to retain - Rank 1', 'JobFamilyList'],
    ['hardest_to_retain_2', 'Hardest to retain - Rank 2', 'JobFamilyList'],
    ['hardest_to_retain_3', 'Hardest to retain - Rank 3', 'JobFamilyList'],
    ['retention_initiatives', 'Retention initiatives', 'text'],
    ['pay_reason_for_leaving', 'Pay a reason for leaving?', 'YesNoUnsure'],
    ['other_leaving_reasons', 'Other leaving reasons', 'text'],
    ['retrenched', 'Retrenchment in the last 12 months?', 'YesNo'],
    ['retrenchment_driver', 'Retrenchment driver', 'RetrenchmentDriverList'],
    [
        'retrenchment_above_statutory',
        'Retrenchment benefits above statutory?',
        'YesNoNA',
    ],
    ['retrenchment_above_note', 'Retrenchment benefits note', 'text'],
    ['comments', 'Comments', 'text'],
];

export function AttritionDialog({
    participantId,
    answers,
    typed,
    lookups,
    ...dialog
}: DialogProps & {
    participantId: number;
    answers: Answers;
    typed: TypedAnswers;
    lookups: Lookups;
}) {
    const form = useForm(
        toForm(
            answers,
            ATTRITION_FIELDS.map(([key]) => key),
            typed,
            (key) => `attrition:${key}`,
        ),
    );

    return (
        <FormDialog
            {...dialog}
            title="Edit Attrition & Hiring"
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    retrenched: yesNo(data.retrenched),
                }));
                form.put(participantRoutes.attrition.url(participantId), {
                    preserveScroll: true,
                    onSuccess: () => dialog.onOpenChange(false),
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {ATTRITION_FIELDS.map(([key, label, kind]) => (
                    <Field
                        key={key}
                        id={`attrition-${key}`}
                        label={label}
                        error={form.errors[key]}
                    >
                        {kind === 'number' || kind === 'text' ? (
                            <Input
                                id={`attrition-${key}`}
                                inputMode={
                                    kind === 'number' ? 'decimal' : undefined
                                }
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        ) : (
                            <Choice
                                id={`attrition-${key}`}
                                value={form.data[key]}
                                options={lookups[kind] ?? []}
                                onChange={(value) => form.setData(key, value)}
                            />
                        )}
                    </Field>
                ))}
            </div>
        </FormDialog>
    );
}

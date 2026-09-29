import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Banknote,
    Briefcase,
    Check,
    FileText,
    Phone,
    User,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UserAvatar } from '@/components/user-avatar';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import employeeRoutes from '@/routes/hr/employees';

type Option = { id: number; name: string };

type EmployeeDocument = {
    id: number;
    document_type_id: number;
    file_name: string;
};

type Employee = Record<string, string | number | null> & {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        reports_to_id: number | null;
        avatar: string | null;
    };
    documents: EmployeeDocument[];
};

const STATUSES = ['active', 'inactive', 'probation', 'terminated'];
const EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Contract', 'Temporary'];
const GENDERS = ['male', 'female', 'other'];

const blank = {
    name: '',
    email: '',
    password: '',
    employee_id: '',
    phone: '',
    date_of_birth: '',
    gender: '',
    id_type: 'mykad',
    id_number: '',
    branch_id: '' as number | '',
    department_id: '' as number | '',
    designation_id: '' as number | '',
    shift_id: '' as number | '',
    reports_to_id: '' as number | '',
    date_of_joining: '',
    employment_type: 'Full-time',
    employee_status: 'active',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    country: 'Malaysia',
    postal_code: '',
    emergency_contact_name: '',
    emergency_contact_relationship: '',
    emergency_contact_number: '',
    bank_name: '',
    account_holder_name: '',
    account_number: '',
    bank_identifier_code: '',
    bank_branch: '',
    tax_payer_id: '',
};

type Field = keyof typeof blank;

/** The five steps, and the fields each one owns (used to jump to the first step with an error). */
const STEPS: {
    title: string;
    heading: string;
    icon: LucideIcon;
    fields: (Field | 'photo' | 'documents')[];
}[] = [
    {
        title: 'Personal',
        heading: 'Basic Information',
        icon: User,
        fields: [
            'name',
            'email',
            'password',
            'employee_id',
            'phone',
            'date_of_birth',
            'gender',
            'id_type',
            'id_number',
            'photo',
        ],
    },
    {
        title: 'Employment',
        heading: 'Employment Details',
        icon: Briefcase,
        fields: [
            'branch_id',
            'department_id',
            'designation_id',
            'shift_id',
            'reports_to_id',
            'date_of_joining',
            'employment_type',
            'employee_status',
        ],
    },
    {
        title: 'Contact',
        heading: 'Contact Information',
        icon: Phone,
        fields: [
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'country',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_number',
        ],
    },
    {
        title: 'Banking',
        heading: 'Banking Information',
        icon: Banknote,
        fields: [
            'bank_name',
            'account_holder_name',
            'account_number',
            'bank_identifier_code',
            'bank_branch',
            'tax_payer_id',
        ],
    },
    {
        title: 'Documents',
        heading: 'Documents',
        icon: FileText,
        fields: ['documents'],
    },
];

export default function EmployeeForm({
    employee,
    branches,
    departments,
    designations,
    shifts,
    managers,
    documentTypes,
    nextEmployeeId,
}: {
    employee: Employee | null;
    branches: Option[];
    departments: (Option & { branch_id: number })[];
    designations: (Option & { department_id: number })[];
    shifts: Option[];
    managers: Option[];
    documentTypes: (Option & { is_required: boolean })[];
    nextEmployeeId: string;
}) {
    const { t } = useTranslation();
    const [step, setStep] = useState(0);
    const editing = employee !== null;

    const form = useForm({
        ...(employee
            ? (Object.fromEntries(
                  Object.keys(blank).map((key) => [key, employee[key] ?? '']),
              ) as typeof blank)
            : { ...blank, employee_id: nextEmployeeId }),
        ...(employee && {
            name: employee.user.name,
            email: employee.user.email,
            reports_to_id: employee.user.reports_to_id ?? '',
            password: '',
            id_type: (employee.id_type as string | null) ?? 'mykad',
        }),
        photo: null as File | null,
        documents: {} as Record<number, File>,
    }).withPrecognition(
        // Laravel Precognition: "Next" asks the server to validate only the current step.
        employee ? employeeRoutes.update(employee.id) : employeeRoutes.store(),
    );

    const next = () =>
        form.validate({
            // Files are checked when the employee is saved, not per step.
            only: STEPS[step].fields.filter(
                (key): key is Field => key !== 'photo' && key !== 'documents',
            ),
            onSuccess: () => setStep(step + 1),
        });

    const uploaded = new Map(
        (employee?.documents ?? []).map((d) => [d.document_type_id, d]),
    );
    const errorFor = (key: string) =>
        (form.errors as Record<string, string | undefined>)[key];

    const submit = () =>
        // Files need multipart; PHP only parses it on POST, so updates spoof PUT via _method.
        form.post(
            editing
                ? employeeRoutes.update.form(employee.id).action
                : employeeRoutes.store().url,
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => {
                    // Show the first step Laravel found a problem on.
                    const first = STEPS.findIndex((s) =>
                        Object.keys(errors).some((key) =>
                            (s.fields as string[]).includes(key.split('.')[0]),
                        ),
                    );
                    setStep(first === -1 ? 0 : first);
                },
            },
        );

    const field = (
        key: string,
        label: string,
        input: ReactNode,
        { required = false, hint }: { required?: boolean; hint?: string } = {},
    ) => (
        <div key={key} className="grid content-start gap-2">
            <Label htmlFor={`employee-${key}`}>
                {t(label)}
                {required && <span className="text-destructive">*</span>}
            </Label>
            {input}
            {hint && <p className="text-xs text-muted-foreground">{t(hint)}</p>}
            <InputError message={errorFor(key)} />
        </div>
    );

    const text = (
        key: Field,
        label: string,
        {
            type = 'text',
            required = false,
            placeholder,
            hint,
        }: {
            type?: string;
            required?: boolean;
            placeholder?: string;
            hint?: string;
        } = {},
    ) =>
        field(
            key,
            label,
            <Input
                id={`employee-${key}`}
                type={type}
                placeholder={placeholder && t(placeholder)}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />,
            { required, hint },
        );

    const select = (
        key: Field,
        label: string,
        options: { value: string | number; label: string }[],
        {
            required = true,
            empty,
            onChange = (value: string) => form.setData(key, value),
        }: {
            required?: boolean;
            empty?: string;
            onChange?: (value: string) => void;
        } = {},
    ) =>
        field(
            key,
            label,
            <SelectField
                id={`employee-${key}`}
                value={form.data[key]}
                onChange={(e) => onChange(e.target.value)}
            >
                <option value="">
                    {empty ?? t('Select :field', { field: t(label) })}
                </option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </SelectField>,
            { required },
        );

    const toOptions = (items: Option[]) =>
        items.map((item) => ({ value: item.id, label: item.name }));
    const numeric = (key: Field) => (value: string) =>
        form.setData(key, value ? Number(value) : '');

    const panels: ReactNode[] = [
        <>
            {text('name', 'Full Name', {
                required: true,
                placeholder: 'e.g. Nur Aisyah binti Hassan',
            })}
            {text('employee_id', 'Employee ID', {
                hint: 'Leave as is to use the next number automatically.',
            })}
            {text('email', 'Email', {
                type: 'email',
                required: true,
                placeholder: 'e.g. aisyah@example.com',
            })}
            {text('password', 'Password', {
                type: 'password',
                required: !editing,
                hint: editing
                    ? 'Leave blank to keep the current password.'
                    : undefined,
            })}
            {text('phone', 'Phone Number', {
                type: 'tel',
                placeholder: 'e.g. +60 12-345 6789',
            })}
            {text('date_of_birth', 'Date of Birth', { type: 'date' })}
            {select(
                'id_type',
                'Identity Document',
                [
                    { value: 'mykad', label: t('MyKad (NRIC)') },
                    { value: 'passport', label: t('Passport') },
                ],
                { required: false, empty: t('None') },
            )}
            {text(
                'id_number',
                form.data.id_type === 'passport' ? 'Passport No.' : 'MyKad No.',
                {
                    placeholder:
                        form.data.id_type === 'passport'
                            ? 'e.g. A12345678'
                            : 'e.g. 950412-14-5678',
                    hint:
                        form.data.id_type === 'passport'
                            ? 'For foreign employees.'
                            : '12 digits; dashes are added for you.',
                },
            )}
            {field(
                'gender',
                'Gender',
                <div className="flex h-9 items-center gap-5">
                    {GENDERS.map((gender) => (
                        <label
                            key={gender}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="radio"
                                name="gender"
                                value={gender}
                                checked={form.data.gender === gender}
                                onChange={() => form.setData('gender', gender)}
                                className="size-4 accent-primary"
                            />
                            {t(
                                gender.charAt(0).toUpperCase() +
                                    gender.slice(1),
                            )}
                        </label>
                    ))}
                </div>,
            )}
            <div className="sm:col-span-2">
                {field(
                    'photo',
                    'Profile Image',
                    <div className="flex items-center gap-4">
                        <UserAvatar
                            name={form.data.name || '?'}
                            src={
                                form.data.photo
                                    ? URL.createObjectURL(form.data.photo)
                                    : (employee?.user.avatar ?? null)
                            }
                            className="size-16"
                        />
                        <Input
                            id="employee-photo"
                            type="file"
                            accept="image/*"
                            onChange={(e) =>
                                form.setData(
                                    'photo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </div>,
                    { hint: 'JPG or PNG, up to 2 MB.' },
                )}
            </div>
        </>,
        <>
            {select('branch_id', 'Branch', toOptions(branches), {
                onChange: (v) =>
                    form.setData((data) => ({
                        ...data,
                        branch_id: v ? Number(v) : '',
                        department_id: '',
                        designation_id: '',
                    })),
            })}
            {select(
                'department_id',
                'Department',
                toOptions(
                    departments.filter(
                        (d) => d.branch_id === form.data.branch_id,
                    ),
                ),
                {
                    onChange: (v) =>
                        form.setData((data) => ({
                            ...data,
                            department_id: v ? Number(v) : '',
                            designation_id: '',
                        })),
                },
            )}
            {select(
                'designation_id',
                'Designation',
                toOptions(
                    designations.filter(
                        (d) => d.department_id === form.data.department_id,
                    ),
                ),
                { onChange: numeric('designation_id') },
            )}
            {text('date_of_joining', 'Date of Joining', {
                type: 'date',
                required: true,
            })}
            {select('shift_id', 'Shift', toOptions(shifts), {
                required: false,
                onChange: numeric('shift_id'),
            })}
            {select(
                'reports_to_id',
                'Reports To',
                toOptions(managers.filter((m) => m.id !== employee?.user.id)),
                {
                    required: false,
                    empty: t(editing ? 'No manager' : 'Me'),
                    onChange: numeric('reports_to_id'),
                },
            )}
            {select(
                'employment_type',
                'Employment Type',
                EMPLOYMENT_TYPES.map((type) => ({
                    value: type,
                    label: t(type),
                })),
            )}
            {select(
                'employee_status',
                'Status',
                STATUSES.map((status) => ({
                    value: status,
                    label: t(status.charAt(0).toUpperCase() + status.slice(1)),
                })),
            )}
        </>,
        <>
            <h3 className="font-medium sm:col-span-2">{t('Address')}</h3>
            {text('address_line_1', 'Address Line 1', {
                placeholder: 'e.g. No. 12, Jalan Sultan Ismail',
            })}
            {text('address_line_2', 'Address Line 2')}
            {text('city', 'City')}
            {text('state', 'State')}
            {text('postal_code', 'Postcode')}
            {text('country', 'Country')}
            <h3 className="mt-2 font-medium sm:col-span-2">
                {t('Emergency Contact')}
            </h3>
            {text('emergency_contact_name', 'Contact Name')}
            {text('emergency_contact_relationship', 'Relationship')}
            {text('emergency_contact_number', 'Contact Number', {
                type: 'tel',
            })}
        </>,
        <>
            {text('bank_name', 'Bank Name', { placeholder: 'e.g. Maybank' })}
            {text('account_holder_name', 'Account Holder Name')}
            {text('account_number', 'Account Number')}
            {text('bank_identifier_code', 'Bank Identifier Code', {
                placeholder: 'e.g. MBBEMYKL',
            })}
            {text('bank_branch', 'Bank Branch')}
            {text('tax_payer_id', 'Income Tax No.', {
                placeholder: 'e.g. IG12345678090',
            })}
        </>,
        <>
            {documentTypes.length === 0 && (
                <p className="text-sm text-muted-foreground sm:col-span-2">
                    {t('No document types have been set up.')}
                </p>
            )}
            {documentTypes.map((type) => {
                const current = uploaded.get(type.id);
                const picked = form.data.documents[type.id];

                return field(
                    `documents.${type.id}`,
                    type.name,
                    <>
                        <Input
                            id={`employee-documents.${type.id}`}
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                const next = { ...form.data.documents };

                                if (file) {
                                    next[type.id] = file;
                                } else {
                                    delete next[type.id];
                                }

                                form.setData('documents', next);
                            }}
                        />
                        {current && !picked && (
                            <a
                                href={
                                    employeeRoutes.document({
                                        employee: employee!.id,
                                        document: current.id,
                                    }).url
                                }
                                className="text-xs text-blue-600 hover:underline"
                            >
                                {t('Current: :name', {
                                    name: current.file_name,
                                })}
                            </a>
                        )}
                    </>,
                    { required: type.is_required && !current },
                );
            })}
        </>,
    ];

    return (
        <>
            <Head title={t(editing ? 'Edit Employee' : 'Create Employee')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={editing ? 'Edit Employee' : 'Create Employee'}
                    description={
                        editing
                            ? 'Update the employee’s details.'
                            : 'Add a new employee to your organization.'
                    }
                    action={
                        <Button variant="outline" asChild>
                            <Link href={employeeRoutes.index()}>
                                <ArrowLeft /> {t('Back')}
                            </Link>
                        </Button>
                    }
                />

                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();

                        if (step < STEPS.length - 1) {
                            next();
                        } else {
                            submit();
                        }
                    }}
                    className="rounded-xl border bg-card p-6 shadow-sm"
                >
                    <ol className="mb-6 flex flex-wrap items-center gap-2">
                        {STEPS.map((s, i) => {
                            const hasError = Object.keys(form.errors).some(
                                (key) =>
                                    (s.fields as string[]).includes(
                                        key.split('.')[0],
                                    ),
                            );

                            return (
                                <li
                                    key={s.title}
                                    className="flex flex-1 items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        onClick={() => setStep(i)}
                                        aria-current={
                                            i === step ? 'step' : undefined
                                        }
                                        className={cn(
                                            'flex items-center gap-2 text-sm font-medium whitespace-nowrap text-muted-foreground',
                                            i === step && 'text-primary',
                                            hasError && 'text-destructive',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-8 items-center justify-center rounded-full border-2',
                                                i === step && 'border-primary',
                                                i < step &&
                                                    'border-primary bg-primary text-primary-foreground',
                                                hasError &&
                                                    'border-destructive',
                                            )}
                                        >
                                            {i < step && !hasError ? (
                                                <Check className="size-4" />
                                            ) : (
                                                i + 1
                                            )}
                                        </span>
                                        {t(s.title)}
                                    </button>
                                    {i < STEPS.length - 1 && (
                                        <span className="hidden h-px flex-1 bg-border sm:block" />
                                    )}
                                </li>
                            );
                        })}
                    </ol>

                    <section className="rounded-xl border">
                        <h2 className="flex items-center gap-2 border-b px-6 py-4 text-lg font-semibold">
                            {(() => {
                                const Icon = STEPS[step].icon;

                                return (
                                    <Icon className="size-5 text-muted-foreground" />
                                );
                            })()}
                            {t(STEPS[step].heading)}
                        </h2>
                        <div className="grid gap-5 p-6 sm:grid-cols-2">
                            {panels[step]}
                        </div>
                    </section>

                    <div className="mt-6 flex justify-between gap-3">
                        {step > 0 ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(step - 1)}
                            >
                                <ArrowLeft /> {t('Previous')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        {step < STEPS.length - 1 ? (
                            <Button type="submit" disabled={form.validating}>
                                {t('Next')} <ArrowRight />
                            </Button>
                        ) : (
                            <Button type="submit" disabled={form.processing}>
                                {t(
                                    editing
                                        ? 'Update Employee'
                                        : 'Create Employee',
                                )}
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

EmployeeForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Employees', href: employeeRoutes.index() },
        { title: 'Employee', href: employeeRoutes.index() },
    ],
};

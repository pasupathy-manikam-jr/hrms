import { Form, Head } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    Copy,
    Lock,
    Mail,
    User,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    status?: string;
    canResetPassword: boolean;
    demo: boolean;
};

// Mirrors DatabaseSeeder.
const demoAccounts: { role: string; email: string; icon: LucideIcon }[] = [
    { role: 'Company', email: 'company@example.com', icon: Building2 },
    { role: 'HR', email: 'hr@example.com', icon: Briefcase },
    { role: 'Employee', email: 'employee@example.com', icon: User },
];
const demoPassword = 'Zx123456';

export default function Login({ status, canResetPassword, demo }: Props) {
    const [email, setEmail] = useState(demo ? demoAccounts[0].email : '');
    const [password, setPassword] = useState(demo ? demoPassword : '');
    const { t } = useTranslation();

    const fillAccount = (accountEmail: string) => {
        setEmail(accountEmail);
        setPassword(demoPassword);
        void navigator.clipboard?.writeText(accountEmail);
    };

    return (
        <>
            <Head title={t('Login')} />

            <PasskeyVerify />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Form
                noValidate
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div>
                            <Label htmlFor="email" className="mb-2">
                                {t('Email address')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <div className="relative">
                                <Mail className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-gray-400" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="company@example.com"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="h-11 ps-10"
                                />
                            </div>
                            <InputError message={errors.email} />
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <Label htmlFor="password">
                                    {t('Password')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm text-primary no-underline hover:underline"
                                        tabIndex={5}
                                    >
                                        {t('Forgot password?')}
                                    </TextLink>
                                )}
                            </div>
                            <div className="relative">
                                <Lock className="pointer-events-none absolute start-3 top-1/2 z-10 size-5 -translate-y-1/2 text-gray-400" />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="••••••••••••"
                                    value={password}
                                    onChange={(e) =>
                                        setPassword(e.target.value)
                                    }
                                    className="h-11 ps-10"
                                />
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label
                                htmlFor="remember"
                                className="font-normal text-gray-600 dark:text-gray-400"
                            >
                                {t('Remember me')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-full shadow-md transition-transform hover:scale-[1.02]"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t(processing ? 'Signing in...' : 'Login')}
                        </Button>
                    </>
                )}
            </Form>

            {demo && (
                <div className="mt-6 border-t border-gray-100 pt-4 dark:border-gray-700">
                    <div className="rounded-xl border border-gray-100 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div className="mb-2 flex items-center gap-2">
                            <div className="flex size-6 items-center justify-center rounded-full bg-primary/10">
                                <Users className="size-3.5 text-primary" />
                            </div>
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                                {t('Demo Login Credentials')}
                            </h3>
                        </div>
                        <div className="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-700">
                            <table className="w-full table-fixed text-start text-xs [&_th]:text-start">
                                <thead className="bg-primary/5">
                                    <tr className="border-b border-gray-100 text-gray-900 dark:border-gray-700 dark:text-gray-100">
                                        <th className="w-[30%] py-2 ps-2 font-semibold">
                                            {t('Role')}
                                        </th>
                                        <th className="w-[40%] py-2 font-semibold">
                                            {t('Email')}
                                        </th>
                                        <th className="w-[20%] py-2 font-semibold">
                                            {t('Password')}
                                        </th>
                                        <th className="w-[10%] py-2 pe-3">
                                            <span className="sr-only">Use</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {demoAccounts.map(
                                        ({
                                            role,
                                            email: accountEmail,
                                            icon: Icon,
                                        }) => (
                                            <tr key={accountEmail}>
                                                <td className="py-1.5 ps-2">
                                                    <div className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                                                        <span className="hidden size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 sm:flex">
                                                            <Icon className="size-3 text-primary" />
                                                        </span>
                                                        <span className="truncate">
                                                            {t(role)}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td
                                                    className="truncate py-1.5 pe-2 text-gray-600 dark:text-gray-300"
                                                    title={accountEmail}
                                                >
                                                    {accountEmail}
                                                </td>
                                                <td className="truncate py-1.5 font-mono text-sm text-gray-600 dark:text-gray-300">
                                                    {demoPassword}
                                                </td>
                                                <td className="py-1.5 pe-3 text-end">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            fillAccount(
                                                                accountEmail,
                                                            )
                                                        }
                                                        className="inline-flex cursor-pointer items-center justify-center rounded bg-primary/10 p-1.5 hover:opacity-80"
                                                        aria-label={`${t('Use')} ${t(role)}`}
                                                    >
                                                        <Copy className="size-3.5 text-primary" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Welcome back!',
    description: 'Sign in to continue to your account',
};

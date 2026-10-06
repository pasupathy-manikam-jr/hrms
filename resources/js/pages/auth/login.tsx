import { Form, Head } from '@inertiajs/react';
import { Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { useTranslation } from '@/hooks/use-translation';

type DemoLogin = { name: string; email: string; password: string };

type Props = {
    status?: string;
    canResetPassword: boolean;
    demoLogins: DemoLogin[];
};

export default function Login({ status, canResetPassword, demoLogins }: Props) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const { t } = useTranslation();

    const fill = (loginEmail: string) => {
        const login = demoLogins.find((l) => l.email === loginEmail);

        if (login) {
            setEmail(login.email);
            setPassword(login.password);
        }
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
                        {demoLogins.length > 0 && (
                            <div className="grid gap-3 rounded-lg border bg-muted/50 p-4">
                                <Label id="quick-login">
                                    {t('Quick login')}
                                </Label>
                                <RadioGroup
                                    aria-labelledby="quick-login"
                                    onValueChange={fill}
                                >
                                    {demoLogins.map((login) => (
                                        <Label
                                            key={login.email}
                                            className="flex cursor-pointer items-center gap-3 font-normal"
                                        >
                                            <RadioGroupItem
                                                value={login.email}
                                            />
                                            <span className="font-medium">
                                                {t(login.name)}
                                            </span>
                                            <span className="truncate text-muted-foreground">
                                                {login.email}
                                            </span>
                                        </Label>
                                    ))}
                                </RadioGroup>
                            </div>
                        )}

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
        </>
    );
}

Login.layout = {
    title: 'Welcome back!',
    description: 'Sign in to continue to your account',
};

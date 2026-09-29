import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LayoutDashboard,
    Linkedin,
    Mail,
    MapPin,
    Megaphone,
    Phone,
    Play,
    Quote,
    Rocket,
    Send,
    Sparkles,
    Star,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import {
    LandingFooter,
    LandingHeader,
    LandingShell,
    landingIcon,
} from '@/components/landing-shell';
import type {
    CustomPageLink,
    LandingContent,
    LandingSections,
} from '@/components/landing-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, login } from '@/routes';
import { store as contactStore } from '@/routes/contact';
import { store as newsletterStore } from '@/routes/newsletter';
import { useTranslation } from '@/hooks/use-translation';

// Content comes from Landing Page settings (landing_page_settings) and is always
// rendered as plain text: admins can't inject markup into the public page.

const initials = (name: string) =>
    name
        .split(' ')
        .map((part) => part[0])
        .join('');

function SectionHeading({
    title,
    subtitle,
}: {
    title: string;
    subtitle: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="mx-auto mb-12 max-w-3xl text-center">
            <h2 className="mb-4 text-3xl font-bold text-gray-900 md:text-4xl dark:text-white">
                {t(title)}
            </h2>
            <p className="text-lg text-gray-600 dark:text-gray-400">
                {subtitle}
            </p>
        </div>
    );
}

function IconBadge({ icon: Icon }: { icon: LucideIcon }) {
    return (
        <div className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
            <Icon className="size-6 text-primary" />
        </div>
    );
}

function Section({
    id,
    muted = false,
    children,
}: {
    id: string;
    muted?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className={`scroll-mt-16 py-20 ${muted ? 'bg-slate-50 dark:bg-gray-950' : 'bg-white dark:bg-gray-900'}`}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {children}
            </div>
        </section>
    );
}

// Stand-in for the demo's product screenshots until the app has real screens to capture.
function MockScreen({ icon: Icon }: { icon: LucideIcon }) {
    return (
        <div className="flex h-48 gap-2 bg-slate-50 p-3 dark:bg-gray-800">
            <div className="w-1/5 space-y-1.5 rounded bg-white p-2 dark:bg-gray-900">
                <div className="mb-3 h-2 w-8 rounded bg-primary/60" />
                {Array.from({ length: 7 }, (_, i) => (
                    <div
                        key={i}
                        className={`h-1.5 rounded ${i === 1 ? 'bg-primary/50' : 'bg-gray-200 dark:bg-gray-700'}`}
                    />
                ))}
            </div>
            <div className="flex flex-1 flex-col gap-2">
                <div className="grid grid-cols-4 gap-2">
                    {Array.from({ length: 4 }, (_, i) => (
                        <div
                            key={i}
                            className="h-10 rounded bg-white p-1.5 dark:bg-gray-900"
                        >
                            <div className="h-1.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700" />
                            <div className="mt-1.5 h-2.5 w-1/2 rounded bg-primary/40" />
                        </div>
                    ))}
                </div>
                <div className="flex flex-1 items-center justify-center rounded bg-white dark:bg-gray-900">
                    <Icon className="size-10 text-primary/40" />
                </div>
            </div>
        </div>
    );
}

function StatGrid({ stats }: { stats: LandingSections['hero']['stats'] }) {
    const { t } = useTranslation();

    return (
        <div className="mt-16 grid max-w-md grid-cols-3 gap-8 text-center">
            {stats.map((stat, i) => (
                <div key={i}>
                    <div className="text-4xl font-bold">{stat.value}</div>
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                        {t(stat.label)}
                    </div>
                </div>
            ))}
        </div>
    );
}

function Hero({ s }: { s: LandingSections['hero'] }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <section id="home" className="bg-slate-50 py-20 dark:bg-gray-950">
            <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div>
                    {s.announcement_text && (
                        <div className="mb-8 inline-flex items-center gap-2 rounded-full border border-primary/40 bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                            <Megaphone className="size-4" />
                            {s.announcement_text}
                        </div>
                    )}
                    <h1 className="mb-8 text-5xl leading-tight font-bold tracking-tight md:text-6xl">
                        {t(s.title)}
                    </h1>
                    <p className="mb-8 max-w-xl text-xl leading-relaxed text-gray-600 dark:text-gray-400">
                        {t(s.subtitle)}
                    </p>
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="inline-flex items-center gap-2 rounded-lg border-2 border-primary px-8 py-3.5 text-lg font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
                    >
                        <Play className="size-5" />
                        {auth.user ? 'Dashboard' : 'Login'}
                    </Link>
                    <StatGrid stats={s.stats} />
                </div>
                <div className="overflow-hidden rounded-xl border border-gray-200 shadow-2xl dark:border-gray-700 [&>div]:h-96">
                    <MockScreen icon={LayoutDashboard} />
                </div>
            </div>
        </section>
    );
}

function Features({ s }: { s: LandingSections['features'] }) {
    const { t } = useTranslation();

    return (
        <Section id="features">
            <SectionHeading title={s.title} subtitle={s.description} />
            <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                {s.features_list.map((feature, i) => (
                    <div
                        key={i}
                        className="rounded-xl border border-gray-200 p-8 transition-shadow hover:shadow-lg dark:border-gray-700"
                    >
                        <IconBadge icon={landingIcon(feature.icon)} />
                        <h3 className="mt-6 mb-4 text-xl font-semibold">
                            {t(feature.title)}
                        </h3>
                        <p className="leading-relaxed text-gray-600 dark:text-gray-400">
                            {t(feature.description)}
                        </p>
                    </div>
                ))}
            </div>
        </Section>
    );
}

function Screenshots({ s }: { s: LandingSections['screenshots'] }) {
    const { t } = useTranslation();

    return (
        <Section id="screenshots">
            <SectionHeading title={s.title} subtitle={s.subtitle} />
            <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                {s.screenshots_list.map((shot, i) => (
                    <div
                        key={i}
                        className="overflow-hidden rounded-xl border border-gray-200 transition-shadow hover:shadow-lg dark:border-gray-700"
                    >
                        <MockScreen icon={landingIcon(shot.icon)} />
                        <div className="p-6">
                            <h3 className="mb-3 text-lg font-semibold">
                                {t(shot.title)}
                            </h3>
                            <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                {t(shot.description)}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-12 text-center">
                <span className="inline-flex items-center gap-2 rounded-full border-2 border-primary px-6 py-3 font-semibold text-primary">
                    <Sparkles className="size-4" />
                    {t('And many more features to discover')}
                </span>
            </div>
        </Section>
    );
}

function WhyChooseUs({ s }: { s: LandingSections['why_choose_us'] }) {
    const { t } = useTranslation();

    return (
        <Section id="why-choose-us">
            <div className="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h2 className="mb-4 text-4xl font-bold">{t(s.title)}</h2>
                    <p className="mb-8 text-lg text-gray-600 dark:text-gray-400">
                        {t(s.subtitle)}
                    </p>
                    <div className="space-y-6">
                        {s.reasons.map((reason, i) => {
                            const Icon = landingIcon(reason.icon);

                            return (
                                <div key={i} className="flex gap-4">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                        <Icon className="size-5 text-primary" />
                                    </div>
                                    <div>
                                        <h3 className="mb-1 text-lg font-semibold">
                                            {t(reason.title)}
                                        </h3>
                                        <p className="text-gray-600 dark:text-gray-400">
                                            {t(reason.description)}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
                <div className="rounded-xl border border-gray-200 bg-slate-50 p-8 dark:border-gray-700 dark:bg-gray-800">
                    <div className="mb-8 text-center">
                        <h3 className="mb-2 text-2xl font-bold">
                            {t('Trusted by Industry Leaders')}
                        </h3>
                        <p className="text-gray-600 dark:text-gray-400">
                            {t('Join the growing community of professionals')}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-6">
                        {s.stats.map((stat, i) => (
                            <div
                                key={i}
                                className="rounded-lg border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900"
                            >
                                <div className="mb-2 text-3xl font-bold">
                                    {stat.value}
                                </div>
                                <div className="text-gray-700 dark:text-gray-300">
                                    {t(stat.label)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Section>
    );
}

function About({ s }: { s: LandingSections['about'] }) {
    const { t } = useTranslation();

    return (
        <Section id="about" muted>
            <SectionHeading title={s.title} subtitle={s.description} />
            <div className="mb-16 grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <h3 className="mb-6 text-2xl font-bold">
                        {t(s.story_title)}
                    </h3>
                    <p className="mb-8 leading-relaxed text-gray-600 dark:text-gray-400">
                        {t(s.story_content)}
                    </p>
                    <div className="flex flex-wrap gap-8">
                        {s.stats.map((stat, i) => (
                            <div key={i} className="text-center">
                                <div className="text-2xl font-bold">
                                    {stat.value}
                                </div>
                                <div className="text-sm text-gray-600 dark:text-gray-400">
                                    {t(stat.label)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="flex h-80 flex-col items-center justify-center rounded-xl border border-gray-200 bg-white text-center dark:border-gray-700 dark:bg-gray-900">
                    <div className="mb-6 flex size-24 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <Rocket className="size-10 text-primary" />
                    </div>
                    <h4 className="mb-2 text-xl font-semibold">
                        {t('Innovation Driven')}
                    </h4>
                    <p className="text-gray-600 dark:text-gray-400">
                        {t('Building the future of HR management')}
                    </p>
                </div>
            </div>
            <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                {s.values.map((value, i) => (
                    <div
                        key={i}
                        className="flex flex-col items-center rounded-xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-900"
                    >
                        <IconBadge icon={landingIcon(value.icon)} />
                        <h4 className="mt-6 mb-4 text-lg font-semibold">
                            {t(value.title)}
                        </h4>
                        <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {t(value.description)}
                        </p>
                    </div>
                ))}
            </div>
        </Section>
    );
}

function Team({ s }: { s: LandingSections['team'] }) {
    const { t } = useTranslation();

    return (
        <Section id="team">
            <SectionHeading title={s.title} subtitle={s.subtitle} />
            <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                {s.members.map((member, i) => (
                    <div
                        key={i}
                        className="rounded-xl border border-gray-200 bg-slate-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div className="mx-auto mb-4 flex size-20 items-center justify-center rounded-full bg-primary text-xl font-bold text-white">
                            {initials(member.name)}
                        </div>
                        <h3 className="text-lg font-semibold">{member.name}</h3>
                        <p className="mb-4 text-gray-700 dark:text-gray-300">
                            {t(member.role)}
                        </p>
                        <p className="mb-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {t(member.bio)}
                        </p>
                        <div className="flex justify-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <Linkedin className="size-4" />
                            </span>
                            {member.email && (
                                <a
                                    href={`mailto:${member.email}`}
                                    aria-label={`Email ${member.name}`}
                                    className="flex size-8 items-center justify-center rounded-full border border-gray-200 bg-white hover:text-primary dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <Mail className="size-4" />
                                </a>
                            )}
                        </div>
                    </div>
                ))}
            </div>
            <div className="mx-auto mt-16 max-w-2xl rounded-xl border border-gray-200 bg-slate-50 p-8 text-center dark:border-gray-700 dark:bg-gray-800">
                <h3 className="mb-4 text-2xl font-bold">{t(s.cta_title)}</h3>
                <p className="mb-6 text-gray-600 dark:text-gray-400">
                    {t(s.cta_description)}
                </p>
                <Button asChild size="lg">
                    <a href="#contact">{t(s.cta_button_text)}</a>
                </Button>
            </div>
        </Section>
    );
}

function Testimonials({ s }: { s: LandingSections['testimonials'] }) {
    const { t } = useTranslation();

    return (
        <Section id="testimonials" muted>
            <SectionHeading title={s.title} subtitle={s.subtitle} />
            <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                {s.testimonials.map((testimonial, i) => (
                    <div
                        key={i}
                        className="relative rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div className="absolute -top-3 left-6 flex size-6 items-center justify-center rounded-full bg-primary">
                            <Quote className="size-3 text-white" />
                        </div>
                        <div className="mb-4 flex gap-1">
                            {Array.from({ length: 5 }, (_, i) => (
                                <Star
                                    key={i}
                                    className="size-4 fill-primary text-primary"
                                />
                            ))}
                        </div>
                        <p className="mb-6 leading-relaxed text-gray-700 dark:text-gray-300">
                            "{t(testimonial.content)}"
                        </p>
                        <div className="flex items-center gap-4">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary font-bold text-white">
                                {initials(testimonial.name)}
                            </div>
                            <div>
                                <div className="font-semibold">
                                    {testimonial.name}
                                </div>
                                <div className="text-sm text-gray-600 dark:text-gray-400">
                                    {t(testimonial.role)}
                                    <span className="text-gray-400">
                                        {' '}
                                        • {testimonial.company}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-16 rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900">
                <h3 className="mb-4 text-2xl font-bold">{t(s.trust_title)}</h3>
                <div className="flex justify-center gap-8">
                    {s.trust_stats.map((stat, i) => (
                        <div key={i}>
                            <div className="text-3xl font-bold">
                                {stat.value}
                            </div>
                            <div className="text-gray-600 dark:text-gray-400">
                                {t(stat.label)}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </Section>
    );
}

function Faq({ s }: { s: LandingSections['faq'] }) {
    const { t } = useTranslation();

    return (
        <Section id="faq">
            <SectionHeading title={s.title} subtitle={s.subtitle} />
            <div className="mx-auto max-w-4xl space-y-3">
                {s.faqs.map((faq, i) => (
                    <details
                        key={i}
                        className="group rounded-lg border border-gray-200 bg-slate-50 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <summary className="flex cursor-pointer list-none items-center justify-between px-6 py-4 text-lg font-semibold">
                            {t(faq.question)}
                            <ChevronDown className="size-5 shrink-0 transition-transform group-open:rotate-180" />
                        </summary>
                        <p className="px-6 pb-4 leading-relaxed text-gray-600 dark:text-gray-400">
                            {t(faq.answer)}
                        </p>
                    </details>
                ))}
            </div>
            <div className="mt-12 text-center">
                <p className="mb-4 text-gray-600 dark:text-gray-400">
                    {t(s.cta_text)}
                </p>
                <Button asChild size="lg">
                    <a href="#contact">{t(s.button_text)}</a>
                </Button>
            </div>
        </Section>
    );
}

function Newsletter({ s }: { s: LandingSections['newsletter'] }) {
    const { t } = useTranslation();

    return (
        <Section id="newsletter" muted>
            <div className="mx-auto max-w-3xl text-center">
                <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-primary/10">
                    <Mail className="size-8 text-primary" />
                </div>
                <h2 className="mb-4 text-3xl font-bold md:text-4xl">
                    {t(s.title)}
                </h2>
                <p className="mb-8 text-lg text-gray-600 dark:text-gray-400">
                    {t(s.subtitle)}
                </p>
                <Form
                    noValidate
                    {...newsletterStore.form()}
                    resetOnSuccess
                    className="mx-auto max-w-md"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex gap-3">
                                <Input
                                    type="email"
                                    name="email"
                                    required
                                    aria-label="Email address"
                                    placeholder="Enter your email address"
                                    className="h-11 bg-white dark:bg-gray-900"
                                />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="h-11"
                                    disabled={processing}
                                >
                                    {t('Subscribe')}
                                </Button>
                            </div>
                            <InputError
                                message={errors.email}
                                className="mt-2 text-left"
                            />
                        </>
                    )}
                </Form>
                <p className="mt-4 text-sm text-gray-500">
                    {t(s.privacy_text)}
                </p>
                <div className="mt-12 grid gap-8 sm:grid-cols-3">
                    {s.benefits.map((benefit, i) => (
                        <div key={i}>
                            <div className="mb-2 text-2xl">{benefit.icon}</div>
                            <h3 className="mb-1 font-semibold">
                                {t(benefit.title)}
                            </h3>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {t(benefit.description)}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </Section>
    );
}

function Contact({ s }: { s: LandingSections['contact'] }) {
    const { t } = useTranslation();
    const info: {
        icon: LucideIcon;
        label: string;
        value: string;
        href?: string;
    }[] = [
        {
            icon: Mail,
            label: 'Email Us',
            value: s.email,
            href: `mailto:${s.email}`,
        },
        {
            icon: Phone,
            label: 'Call Us',
            value: s.phone,
            href: `tel:${s.phone.replace(/[^+\d]/g, '')}`,
        },
        { icon: MapPin, label: 'Visit Us', value: s.address },
    ];

    return (
        <Section id="contact">
            <SectionHeading title={s.title} subtitle={s.subtitle} />
            <div className="grid gap-12 lg:grid-cols-2">
                <div className="rounded-xl border border-gray-200 bg-slate-50 p-8 dark:border-gray-700 dark:bg-gray-800">
                    <h3 className="mb-6 text-xl font-semibold">
                        {t(s.form_title)}
                    </h3>
                    <Form
                        noValidate
                        {...contactStore.form()}
                        resetOnSuccess
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="contact-name">
                                            {t('Full Name')}
                                        </Label>
                                        <Input
                                            id="contact-name"
                                            name="name"
                                            required
                                            placeholder="Your full name"
                                            className="bg-white dark:bg-gray-900"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="contact-email">
                                            {t('Email Address')}
                                        </Label>
                                        <Input
                                            id="contact-email"
                                            type="email"
                                            name="email"
                                            required
                                            placeholder="your@email.com"
                                            className="bg-white dark:bg-gray-900"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contact-subject">
                                        {t('Subject')}
                                    </Label>
                                    <Input
                                        id="contact-subject"
                                        name="subject"
                                        required
                                        placeholder="What is this about?"
                                        className="bg-white dark:bg-gray-900"
                                    />
                                    <InputError message={errors.subject} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contact-message">
                                        {t('Message')}
                                    </Label>
                                    <textarea
                                        id="contact-message"
                                        name="message"
                                        required
                                        rows={5}
                                        placeholder="Tell us more about your inquiry..."
                                        className="rounded-md border border-input bg-white px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-gray-900"
                                    />
                                    <InputError message={errors.message} />
                                </div>
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Send className="size-4" />
                                    {t('Send Message')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
                <div>
                    <h3 className="mb-4 text-xl font-semibold">
                        {t(s.info_title)}
                    </h3>
                    <p className="mb-8 text-gray-600 dark:text-gray-400">
                        {t(s.info_description)}
                    </p>
                    <div className="space-y-6">
                        {info
                            .filter(({ value }) => value)
                            .map(({ icon: Icon, label, value, href }) => (
                                <div
                                    key={label}
                                    className="flex items-center gap-4"
                                >
                                    <div className="flex size-12 items-center justify-center rounded-lg bg-primary/10">
                                        <Icon className="size-5 text-primary" />
                                    </div>
                                    <div>
                                        <div className="font-semibold">
                                            {t(label)}
                                        </div>
                                        {href ? (
                                            <a
                                                href={href}
                                                className="text-gray-600 hover:text-primary dark:text-gray-400"
                                            >
                                                {value}
                                            </a>
                                        ) : (
                                            <span className="text-gray-600 dark:text-gray-400">
                                                {value}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                    </div>
                </div>
            </div>
        </Section>
    );
}

const SECTIONS: {
    [K in LandingContent['section_order'][number]]: (props: {
        s: LandingSections[K];
    }) => ReactNode;
} = {
    hero: Hero,
    features: Features,
    screenshots: Screenshots,
    why_choose_us: WhyChooseUs,
    about: About,
    team: Team,
    testimonials: Testimonials,
    faq: Faq,
    newsletter: Newsletter,
    contact: Contact,
};

function LandingSection<K extends LandingContent['section_order'][number]>({
    name,
    s,
}: {
    name: K;
    s: LandingSections[K];
}) {
    const Component = SECTIONS[name] as (props: {
        s: LandingSections[K];
    }) => ReactNode;

    return <Component s={s} />;
}

export default function Welcome({
    landing,
    customPages,
}: {
    landing: LandingContent;
    customPages: CustomPageLink[];
}) {
    const { sections, section_order, section_visibility } = landing;

    return (
        <>
            <Head title={sections.hero.title} />

            <LandingShell>
                <LandingHeader
                    visibility={section_visibility}
                    pages={customPages}
                />

                {section_order
                    .filter((name) => section_visibility[name])
                    .map((name) => (
                        <LandingSection
                            key={name}
                            name={name}
                            s={sections[name]}
                        />
                    ))}

                {section_visibility.footer && (
                    <LandingFooter
                        footer={sections.footer}
                        pages={customPages}
                    />
                )}
            </LandingShell>
        </>
    );
}

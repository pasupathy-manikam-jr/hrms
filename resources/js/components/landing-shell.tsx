import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    BarChart2,
    Briefcase,
    CalendarDays,
    CheckCircle,
    ChevronDown,
    Clock,
    Banknote,
    Facebook,
    FileText,
    Globe,
    Heart,
    Layers,
    LayoutDashboard,
    Lightbulb,
    Linkedin,
    Rocket,
    Shield,
    Star,
    Target,
    Twitter,
    UserPlus,
    Users,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import HrmWordmark from '@/components/hrm-wordmark';
import { LanguageSwitcher } from '@/components/language-switcher';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, login } from '@/routes';
import { show as customPageRoute } from '@/routes/custom-page';

/** Icon names stored in the landing content; keep in sync with LandingPageSetting::ICONS. */
export const LANDING_ICONS: Record<string, LucideIcon> = {
    users: Users,
    'dollar-sign': Banknote,
    clock: Clock,
    'user-plus': UserPlus,
    award: Award,
    'bar-chart-2': BarChart2,
    'layout-dashboard': LayoutDashboard,
    'file-text': FileText,
    'calendar-days': CalendarDays,
    layers: Layers,
    shield: Shield,
    target: Target,
    heart: Heart,
    lightbulb: Lightbulb,
    star: Star,
    rocket: Rocket,
    briefcase: Briefcase,
    globe: Globe,
    zap: Zap,
    'check-circle': CheckCircle,
};

export const landingIcon = (name: string): LucideIcon =>
    LANDING_ICONS[name] ?? Star;

export type Stat = { value: string; label: string };
export type Card = { icon: string; title: string; description: string };

export type LandingSections = {
    hero: {
        announcement_text: string;
        title: string;
        subtitle: string;
        stats: Stat[];
    };
    features: { title: string; description: string; features_list: Card[] };
    screenshots: { title: string; subtitle: string; screenshots_list: Card[] };
    why_choose_us: {
        title: string;
        subtitle: string;
        reasons: Card[];
        stats: Stat[];
    };
    about: {
        title: string;
        description: string;
        story_title: string;
        story_content: string;
        stats: Stat[];
        values: Card[];
    };
    team: {
        title: string;
        subtitle: string;
        cta_title: string;
        cta_description: string;
        cta_button_text: string;
        members: { name: string; role: string; bio: string; email: string }[];
    };
    testimonials: {
        title: string;
        subtitle: string;
        trust_title: string;
        testimonials: {
            name: string;
            role: string;
            company: string;
            content: string;
        }[];
        trust_stats: Stat[];
    };
    faq: {
        title: string;
        subtitle: string;
        cta_text: string;
        button_text: string;
        faqs: { question: string; answer: string }[];
    };
    newsletter: {
        title: string;
        subtitle: string;
        privacy_text: string;
        benefits: Card[];
    };
    contact: {
        title: string;
        subtitle: string;
        form_title: string;
        info_title: string;
        info_description: string;
        email: string;
        phone: string;
        address: string;
    };
    footer: {
        description: string;
        newsletter_title: string;
        newsletter_subtitle: string;
    };
};

export type SectionKey = keyof LandingSections;

export type LandingContent = {
    section_order: Exclude<SectionKey, 'footer'>[];
    section_visibility: Record<SectionKey, boolean>;
    sections: LandingSections;
};

export type CustomPageLink = { title: string; slug: string };

const NAV: { label: string; anchor: string; section: SectionKey }[] = [
    { label: 'Home', anchor: 'home', section: 'hero' },
    { label: 'Features', anchor: 'features', section: 'features' },
    { label: 'About Us', anchor: 'about', section: 'about' },
    { label: 'Testimonials', anchor: 'testimonials', section: 'testimonials' },
    { label: 'FAQ', anchor: 'faq', section: 'faq' },
    { label: 'Contact Us', anchor: 'contact', section: 'contact' },
];

/**
 * Sticky landing header. `base` is '' on the landing page itself and the home URL
 * elsewhere, so section anchors keep working from custom pages.
 */
export function LandingHeader({
    base = '',
    visibility,
    pages,
}: {
    base?: string;
    visibility?: Partial<Record<SectionKey, boolean>>;
    pages: CustomPageLink[];
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <header className="sticky top-0 z-50 border-b border-gray-100 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href={`${base}#home`}>
                    <HrmWordmark className="text-3xl" />
                </a>
                <nav className="hidden items-center gap-8 lg:flex">
                    {NAV.filter(
                        (item) => visibility?.[item.section] !== false,
                    ).map((item) => (
                        <a
                            key={item.anchor}
                            href={`${base}#${item.anchor}`}
                            className="text-sm font-medium text-gray-700 hover:text-primary dark:text-gray-300"
                        >
                            {t(item.label)}
                        </a>
                    ))}
                    {pages.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="flex items-center gap-1 text-sm font-medium text-gray-700 hover:text-primary dark:text-gray-300">
                                {t('Pages')}
                                <ChevronDown className="size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {pages.map((page) => (
                                    <DropdownMenuItem key={page.slug} asChild>
                                        <Link href={customPageRoute(page.slug)}>
                                            {t(page.title)}
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </nav>
                <div className="flex items-center gap-3">
                    <LanguageSwitcher />
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="text-sm font-medium text-gray-700 hover:text-primary dark:text-gray-300"
                    >
                        {auth.user ? 'Dashboard' : 'Login'}
                    </Link>
                </div>
            </div>
        </header>
    );
}

function FooterColumn({
    title,
    links,
}: {
    title: string;
    links: [string, string][];
}) {
    const { t } = useTranslation();

    return (
        <div>
            <h4 className="mb-4 font-semibold text-white">{t(title)}</h4>
            <ul className="space-y-2 text-sm">
                {links.map(([label, href]) => (
                    <li key={label}>
                        <a href={href} className="hover:text-white">
                            {t(label)}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function LandingFooter({
    base = '',
    footer,
    pages,
}: {
    base?: string;
    footer: LandingSections['footer'];
    pages: CustomPageLink[];
}) {
    const { name } = usePage().props;
    const { t } = useTranslation();

    return (
        <footer className="bg-gray-900 text-gray-300">
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
                <div className="lg:col-span-2">
                    <HrmWordmark className="text-3xl text-white" />
                    <p className="mt-4 max-w-sm text-gray-400">
                        {t(footer.description)}
                    </p>
                    <div className="mt-6 flex gap-3">
                        {[Facebook, Twitter, Linkedin].map((Icon, i) => (
                            <span
                                key={i}
                                className="flex size-9 items-center justify-center rounded-full bg-gray-800"
                            >
                                <Icon className="size-4" />
                            </span>
                        ))}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-8">
                    <FooterColumn
                        title="Product"
                        links={[
                            ['Features', `${base}#features`],
                            ['FAQ', `${base}#faq`],
                        ]}
                    />
                    <FooterColumn
                        title="Company"
                        links={[
                            ['About Us', `${base}#about`],
                            ['Contact', `${base}#contact`],
                        ]}
                    />
                </div>
                <div>
                    <h4 className="mb-4 font-semibold text-white">
                        {t(footer.newsletter_title)}
                    </h4>
                    <p className="text-sm text-gray-400">
                        {t(footer.newsletter_subtitle)}
                    </p>
                    <a
                        href={`${base}#newsletter`}
                        className="mt-4 inline-block text-sm font-medium text-primary hover:underline"
                    >
                        {t('Subscribe →')}
                    </a>
                </div>
            </div>
            <div className="border-t border-gray-800 py-6 text-center text-sm text-gray-500">
                {pages.length > 0 && (
                    <nav className="mb-3 flex flex-wrap justify-center gap-x-6 gap-y-2">
                        {pages.map((page) => (
                            <Link
                                key={page.slug}
                                href={customPageRoute(page.slug)}
                                className="hover:text-white"
                            >
                                {t(page.title)}
                            </Link>
                        ))}
                    </nav>
                )}
                © {new Date().getFullYear()} {name}. All rights reserved.
            </div>
        </footer>
    );
}

/** Page chrome shared by the landing page and custom pages. */
export function LandingShell({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-screen bg-white text-gray-900 dark:bg-gray-900 dark:text-white">
            {children}
        </div>
    );
}

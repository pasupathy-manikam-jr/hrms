import type { Auth } from '@/types/auth';

export type GlobalSettings = {
    dateFormat: string;
    timeFormat: string;
    calendarStartDay: 'sunday' | 'monday';
    footerText: string | null;
    themeColor: string;
    customColor: string;
    primaryColor: string;
    defaultCurrency: string;
    currencySymbol: string;
    decimalFormat: number;
    decimalSeparator: string;
    thousandsSeparator: string;
    currencySymbolPosition: 'before' | 'after';
    currencySymbolSpace: boolean;
    workingDays: number[];
};

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            locales: Record<string, [name: string, countryCode: string]>;
            translationsVersion: string;
            globalSettings: GlobalSettings;
            [key: string]: unknown;
        };
    }
}

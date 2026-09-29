// Local-date helpers for "Y-m-d" strings (no timezone shifts from toISOString()).

export const pad = (n: number) => String(n).padStart(2, '0');

export const ymd = (d: Date) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const parseYmd = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(y, m - 1, d);
};

export const addDays = (d: Date, days: number) =>
    new Date(d.getFullYear(), d.getMonth(), d.getDate() + days);

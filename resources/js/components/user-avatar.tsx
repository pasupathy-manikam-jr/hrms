import { createAvatar } from '@dicebear/core';
import * as avataaars from '@dicebear/avataaars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

type Gender = 'male' | 'female' | 'other' | null | undefined;

const SKIN = ['d08b5b', 'ae5d29', 'edb98a', 'ffdbb4', '614335'];
const BACKGROUND = ['b6e3f4', 'c0aede', 'd1d4f9', 'ffd5dc', 'ffdfbf', 'd1f4e0'];
const LONG_HAIR = [
    'bob',
    'bun',
    'curly',
    'curvy',
    'longButNotTooLong',
    'miaWallace',
    'straight01',
    'straight02',
    'straightAndStrand',
    'bigHair',
] as const;
const SHORT_HAIR = [
    'shortCurly',
    'shortFlat',
    'shortRound',
    'shortWaved',
    'sides',
    'theCaesar',
    'theCaesarAndSidePart',
    'shaggy',
] as const;

// Malaysian naming conventions give the gender when the record doesn't.
function inferGender(name: string, gender: Gender): Gender {
    if (gender === 'male' || gender === 'female') {
        return gender;
    }

    if (/\b(binti|bte|a\/p)\b/i.test(name)) {
        return 'female';
    }

    if (/\b(bin|a\/l)\b/i.test(name)) {
        return 'male';
    }

    return null;
}

const cache = new Map<string, string>();

/**
 * Illustrated avatar generated from the name (stable per person), used when no photo is uploaded.
 */
export function generatedAvatar(name: string, gender?: Gender): string {
    const resolved = inferGender(name, gender);
    const key = `${name}|${resolved ?? ''}`;

    if (!cache.has(key)) {
        cache.set(
            key,
            createAvatar(avataaars, {
                seed: name,
                skinColor: SKIN,
                backgroundColor: BACKGROUND,
                top:
                    resolved === 'female'
                        ? /\bbinti\b/i.test(name)
                            ? ['hijab']
                            : [...LONG_HAIR]
                        : resolved === 'male'
                          ? [...SHORT_HAIR]
                          : [...SHORT_HAIR],
                facialHairProbability: resolved === 'male' ? 25 : 0,
                // Friendly, professional expressions only (the style also has sad, crying, vomit…).
                mouth: ['smile', 'default', 'twinkle'],
                eyes: ['default', 'happy', 'squint'],
                eyebrows: ['defaultNatural', 'default', 'raisedExcitedNatural'],
                accessoriesProbability: 10,
                accessories: ['prescription01', 'prescription02', 'round'],
                clothing: [
                    'blazerAndShirt',
                    'blazerAndSweater',
                    'collarAndSweater',
                    'shirtCrewNeck',
                    'shirtVNeck',
                ],
            }).toDataUri(),
        );
    }

    return cache.get(key)!;
}

/**
 * A person's avatar everywhere in the app: their uploaded photo, else a generated illustration.
 */
export function UserAvatar({
    name,
    src,
    gender,
    className,
}: {
    name: string;
    src?: string | null;
    gender?: Gender;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-10 shrink-0', className)}>
            <AvatarImage
                src={src || generatedAvatar(name, gender)}
                alt={name}
                className="object-cover"
            />
            <AvatarFallback className="bg-primary/10 font-semibold text-primary">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}

/**
 * Avatar + name + secondary line (email, designation…), the demo's person cell.
 */
export function PersonCell({
    name,
    detail,
    src,
    gender,
}: {
    name: string;
    detail?: string | null;
    src?: string | null;
    gender?: Gender;
}) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <UserAvatar name={name} src={src} gender={gender} />
            <div className="min-w-0">
                <div className="truncate font-medium">{name}</div>
                {detail && (
                    <div className="truncate text-sm text-muted-foreground">
                        {detail}
                    </div>
                )}
            </div>
        </div>
    );
}

const INITIALS_TONES = [
    'border-rose-300 bg-rose-50 text-rose-600',
    'border-amber-300 bg-amber-50 text-amber-600',
    'border-orange-300 bg-orange-50 text-orange-600',
    'border-emerald-300 bg-emerald-50 text-emerald-600',
    'border-sky-300 bg-sky-50 text-sky-600',
];

/**
 * The demo's candidate cell: coloured initials instead of a photo (candidates aren't users),
 * tinted by `id` so the same candidate keeps its colour.
 */
export function CandidateCell({
    id,
    name,
    detail,
}: {
    id: number;
    name: string;
    detail?: string | null;
}) {
    const initials = useInitials();

    return (
        <div className="flex min-w-0 items-center gap-3">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-full border text-sm font-semibold',
                    INITIALS_TONES[id % INITIALS_TONES.length],
                )}
            >
                {initials(name)}
            </span>
            <div className="min-w-0">
                <div className="truncate font-medium">{name}</div>
                {detail && (
                    <div className="truncate text-sm text-muted-foreground">
                        {detail}
                    </div>
                )}
            </div>
        </div>
    );
}

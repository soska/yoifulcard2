import type { CSSProperties } from 'react';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicCard, PublicOrganization } from '@/types';

/**
 * Relative luminance of a `#rgb` or `#rrggbb` color, from 0 (black) to 1
 * (white). The server only sends colors in those two shapes.
 */
function luminance(hex: string): number {
    const value = hex.replace('#', '');
    const full =
        value.length === 3
            ? value
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : value;
    const [r, g, b] = [0, 2, 4].map((i) => {
        const channel = parseInt(full.slice(i, i + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * The branded card on the public page. The organization's color is set as
 * the `--brand` CSS variable and read by Tailwind classes; text switches to
 * dark on light brand colors so the balance stays readable.
 */
export function CardFace({
    card,
    organization,
}: {
    card: PublicCard;
    organization: PublicOrganization;
}) {
    const light = luminance(organization.primary_color) > 0.5;

    return (
        <div
            style={{ '--brand': organization.primary_color } as CSSProperties}
            className={cn(
                'flex flex-col gap-6 rounded-2xl bg-(--brand) bg-linear-to-br from-white/15 to-transparent p-6 shadow-xl ring-1 ring-foreground/10',
                light ? 'text-neutral-950' : 'text-white',
            )}
        >
            <div className="flex items-start justify-between gap-4">
                <span className="text-sm font-medium opacity-90">
                    {organization.name}
                </span>
                {card.status === 'frozen' && (
                    <span className="rounded-full bg-current/15 px-3 py-1 text-xs font-semibold tracking-wide uppercase">
                        Frozen
                    </span>
                )}
            </div>
            <div className="flex flex-col gap-1">
                <span className="text-sm opacity-90">Current balance</span>
                <p className="text-5xl font-bold tracking-tight tabular-nums">
                    {formatMoney(card.balance, organization.currency)}
                </p>
            </div>
        </div>
    );
}

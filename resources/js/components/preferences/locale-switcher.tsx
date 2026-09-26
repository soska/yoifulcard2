import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { __ } from '@/i18n';
import { cn } from '@/lib/utils';
import { update as updateLocale } from '@/routes/locale';

/** The switcher's value for "match my browser" (LocaleController::BROWSER). */
const BROWSER = 'browser';

/**
 * A language's name in that language ("English", "Español"), from Intl, so
 * the list of languages comes only from the server (`locale.available`).
 */
function nativeName(locale: string): string {
    let name: string | undefined;

    try {
        name = new Intl.DisplayNames([locale], { type: 'language' }).of(locale);
    } catch {
        name = undefined;
    }

    if (!name) {
        return locale;
    }

    return name.charAt(0).toLocaleUpperCase(locale) + name.slice(1);
}

/**
 * Switches the interface language. Posts to `/locale`: signed-in users save
 * it on their account (or clear it with "Match my browser"), guests get a
 * cookie. The server answers with a full page reload, so every string,
 * including the server's, comes back in the new language.
 */
export function LocaleSwitcher({
    className,
    showLabel = false,
}: {
    className?: string;
    showLabel?: boolean;
}) {
    const { t } = useTranslation();
    const { auth, locale } = usePage().props;
    const signedIn = Boolean(auth.user);
    const saved = signedIn
        ? typeof auth.user.locale === 'string'
            ? auth.user.locale
            : BROWSER
        : locale.current;

    const change = (value: string) => {
        if (value === saved) {
            return;
        }

        router.post(updateLocale.url(), { locale: value });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size={showLabel ? 'default' : 'icon'}
                        className={cn(className)}
                        aria-label={t('Language')}
                        data-test="locale-switcher"
                    />
                }
            >
                <Languages />
                {showLabel && <span>{nativeName(locale.current)}</span>}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{t('Language')}</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={saved}
                        onValueChange={change}
                    >
                        {locale.available.map((value) => (
                            <DropdownMenuRadioItem
                                key={value}
                                value={value}
                                lang={value}
                            >
                                {nativeName(value)}
                            </DropdownMenuRadioItem>
                        ))}
                        {signedIn && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioItem value={BROWSER}>
                                    {__('Match my browser')}
                                </DropdownMenuRadioItem>
                            </>
                        )}
                    </DropdownMenuRadioGroup>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update as updateLocale } from '@/routes/locale';

/** Language names are shown in their own language. */
const LANGUAGES = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Español' },
] as const;

/**
 * Switches the interface language. Posts to `/locale`, which stores the
 * choice in a cookie and sends the page back in the new language.
 */
export function LocaleSwitcher({
    className,
    showLabel = false,
}: {
    className?: string;
    showLabel?: boolean;
}) {
    const { t, locale } = useTranslation();
    const current = LANGUAGES.find((language) => language.value === locale);

    const change = (value: string) => {
        if (value === locale) {
            return;
        }

        router.post(
            updateLocale.url(),
            { locale: value },
            { preserveScroll: true, preserveState: false },
        );
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
                {showLabel && <span>{current?.label}</span>}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{t('Language')}</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={locale}
                        onValueChange={change}
                    >
                        {LANGUAGES.map((language) => (
                            <DropdownMenuRadioItem
                                key={language.value}
                                value={language.value}
                                lang={language.value}
                            >
                                {language.label}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

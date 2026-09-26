import { Monitor, Moon, Sun } from 'lucide-react';
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
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { __ } from '@/i18n';
import { appearanceLabel } from '@/lib/labels';
import { cn } from '@/lib/utils';

const OPTIONS = [
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
    { value: 'system', icon: Monitor },
] as const;

const isAppearance = (value: unknown): value is Appearance =>
    OPTIONS.some((option) => option.value === value);

/**
 * Light, dark, or system. Uses the same `theme` cookie as the appearance
 * settings page (see useAppearance).
 */
export function ThemeSwitcher({ className }: { className?: string }) {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Icon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon"
                        className={cn(className)}
                        aria-label={__('Theme')}
                        data-test="theme-switcher"
                    />
                }
            >
                <Icon />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-40">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{__('Theme')}</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={appearance}
                        onValueChange={(value) => {
                            if (isAppearance(value)) {
                                updateAppearance(value);
                            }
                        }}
                    >
                        {OPTIONS.map(({ value, icon: OptionIcon }) => (
                            <DropdownMenuRadioItem key={value} value={value}>
                                <OptionIcon />
                                {appearanceLabel(value)}
                            </DropdownMenuRadioItem>
                        ))}
                    </DropdownMenuRadioGroup>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

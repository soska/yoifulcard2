import { LocaleSwitcher } from '@/components/preferences/locale-switcher';
import { ThemeSwitcher } from '@/components/preferences/theme-switcher';
import { cn } from '@/lib/utils';

/** Language and theme switchers, side by side. */
export function PreferenceSwitchers({ className }: { className?: string }) {
    return (
        <div className={cn('flex items-center gap-1', className)}>
            <LocaleSwitcher />
            <ThemeSwitcher />
        </div>
    );
}

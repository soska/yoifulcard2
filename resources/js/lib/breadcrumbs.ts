import type { Translate } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types/navigation';

/**
 * The text shown for a breadcrumb: `titleKey` is translated, `title` is
 * data and is never passed through the translator.
 */
export function breadcrumbLabel(item: BreadcrumbItem, t: Translate): string {
    if (typeof item.titleKey === 'string') {
        return t(item.titleKey);
    }

    return item.title ?? '';
}

import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

/**
 * A breadcrumb label is either a static label or data, and says which:
 * - `titleKey`: a translation key (`'Cards'`), shown through `t()`.
 * - `title`: data (an organization name, a card code), shown as is, so a
 *   business called "Admin" is not turned into "Administración".
 */
export type BreadcrumbItem = {
    href: NonNullable<InertiaLinkProps['href']>;
} & ({ titleKey: string; title?: never } | { title: string; titleKey?: never });

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

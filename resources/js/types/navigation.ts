import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

/**
 * `title` is the text shown, already in the reader's language: a static label
 * is written as `__('Cards')` where the page builds its breadcrumbs (inside a
 * layout FUNCTION, so it runs at render time, after the catalog loads), and
 * data (an organization name, a card code) is passed as is. Nothing
 * translates it later, so a business called "Admin" stays "Admin".
 */
export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/** `title` is already translated (built with `__()` at render time). */
export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

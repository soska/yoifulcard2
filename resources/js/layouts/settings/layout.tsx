import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editBusiness } from '@/routes/settings';
import type { NavItem } from '@/types';
import { __ } from '@/i18n';

/** Built per render: `__()` at module scope would freeze English. */
function sidebarNavItems(): NavItem[] {
    return [
        {
            title: __('Business'),
            href: editBusiness(),
            icon: null,
        },
        {
            title: __('Profile'),
            href: edit(),
            icon: null,
        },
        {
            title: __('Security'),
            href: editSecurity(),
            icon: null,
        },
        {
            title: __('Appearance'),
            href: editAppearance(),
            icon: null,
        },
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    // `/settings` is the parent of every settings page, so the business
    // item only matches exactly.
    const isActive = (item: NavItem) =>
        toUrl(item.href) === toUrl(editBusiness())
            ? isCurrentUrl(item.href)
            : isCurrentOrParentUrl(item.href);

    return (
        <div className="px-4 py-6">
            <Heading
                title={__('Settings')}
                description={__(
                    'Manage your business, profile, and account settings',
                )}
            />

            <Tabs
                value={toUrl(sidebarNavItems().find(isActive)?.href ?? '')}
                className="mb-8"
            >
                <TabsList
                    variant="line"
                    className="w-full justify-start overflow-x-auto border-b"
                    aria-label={__('Settings')}
                >
                    {sidebarNavItems().map((item) => (
                        <TabsTrigger
                            key={toUrl(item.href)}
                            value={toUrl(item.href)}
                            className="flex-none px-3"
                            nativeButton={false}
                            render={<Link href={item.href} />}
                        >
                            {item.icon && <item.icon className="h-4 w-4" />}
                            {item.title}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>

            <section className="max-w-xl space-y-12">{children}</section>
        </div>
    );
}

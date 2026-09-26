import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Store } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { __ } from '@/i18n';
import { switchMethod } from '@/routes/organizations';
import type { SwitchableOrganization } from '@/types';
import { roleLabel } from '@/lib/labels';

/**
 * The user's businesses and the current one. The server sends an empty list
 * unless the user has two or more memberships, so the switcher only shows
 * for them.
 */
function useSwitcher() {
    const { organizations, currentOrganization } = usePage().props;
    const current =
        organizations.find((org) => org.id === currentOrganization?.id) ?? null;

    return { organizations, current, visible: organizations.length >= 2 };
}

function switchTo(organization: SwitchableOrganization, reader: boolean) {
    router.post(switchMethod.url(organization.id), reader ? { reader: 1 } : {});
}

function SwitcherItems({
    organizations,
    currentId,
    reader,
}: {
    organizations: SwitchableOrganization[];
    currentId: string | null;
    reader: boolean;
}) {
    return (
        <DropdownMenuGroup>
            <DropdownMenuLabel className="text-xs text-muted-foreground">
                {__('Businesses')}
            </DropdownMenuLabel>
            {organizations.map((organization) => (
                <DropdownMenuItem
                    key={organization.id}
                    className="gap-2 p-2"
                    data-test="organization-switcher-item"
                    onClick={() => {
                        if (organization.id !== currentId) {
                            switchTo(organization, reader);
                        }
                    }}
                >
                    <div className="flex size-6 items-center justify-center rounded-md border">
                        <Store className="size-3.5 shrink-0" />
                    </div>
                    <div className="grid min-w-0 flex-1 leading-tight">
                        <span className="truncate font-medium">
                            {organization.name}
                        </span>
                        <span className="truncate text-xs text-muted-foreground">
                            {roleLabel(organization.role)}
                        </span>
                    </div>
                    {organization.id === currentId && (
                        <Check className="ml-auto size-4" />
                    )}
                </DropdownMenuItem>
            ))}
        </DropdownMenuGroup>
    );
}

/**
 * Sidebar version, after shadcn's team switcher block.
 */
export function SidebarOrganizationSwitcher() {
    const { organizations, current, visible } = useSwitcher();
    const { isMobile } = useSidebar();
    if (!visible) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <SidebarMenuButton
                                size="lg"
                                className="data-open:bg-sidebar-accent data-open:text-sidebar-accent-foreground"
                                aria-label={__('Switch business')}
                                data-test="organization-switcher"
                            />
                        }
                    >
                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <Store className="size-4" />
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-medium">
                                {current?.name}
                            </span>
                            {current && (
                                <span className="truncate text-xs">
                                    {roleLabel(current.role)}
                                </span>
                            )}
                        </div>
                        <ChevronsUpDown className="ml-auto size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--anchor-width) min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                    >
                        <SwitcherItems
                            organizations={organizations}
                            currentId={current?.id ?? null}
                            reader={false}
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

/**
 * Reader version: the business name in the reader header opens the list.
 * Switching goes back to the scanner.
 */
export function ReaderOrganizationSwitcher() {
    const { organizations, current, visible } = useSwitcher();
    if (!visible) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="xs"
                        className="-ml-2 max-w-full min-w-0 font-normal text-muted-foreground"
                        aria-label={__('Switch business')}
                        data-test="organization-switcher"
                    />
                }
            >
                <span className="truncate">{current?.name}</span>
                <ChevronsUpDown data-icon="inline-end" />
            </DropdownMenuTrigger>
            <DropdownMenuContent className="min-w-56 rounded-lg" align="start">
                <SwitcherItems
                    organizations={organizations}
                    currentId={current?.id ?? null}
                    reader
                />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

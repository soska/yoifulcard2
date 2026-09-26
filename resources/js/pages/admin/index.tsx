import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index } from '@/routes/admin';

export default function AdminIndex() {
    return (
        <>
            <Head title="Admin" />
            <div className="flex h-full flex-1 flex-col p-4">
                <Heading
                    title="Superadmin"
                    description="Manage organizations, plans, and users."
                />
            </div>
        </>
    );
}

AdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'Admin',
            href: index(),
        },
    ],
};

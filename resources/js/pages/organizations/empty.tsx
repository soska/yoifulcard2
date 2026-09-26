import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export default function NoOrganization() {
    return (
        <>
            <Head title="No organization" />
            <div className="flex h-full flex-1 flex-col p-4">
                <Empty className="border">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Building2 />
                        </EmptyMedia>
                        <EmptyTitle>
                            You are not part of a business yet
                        </EmptyTitle>
                        <EmptyDescription>
                            Your account is not linked to any organization.
                            Contact support or the business owner to get access.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useTranslation } from '@/hooks/use-translation';

export default function NoOrganization() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('No organization')} />
            <div className="flex h-full flex-1 flex-col p-4">
                <Empty className="border">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <Building2 />
                        </EmptyMedia>
                        <EmptyTitle>
                            {t('You are not part of a business yet')}
                        </EmptyTitle>
                        <EmptyDescription>
                            {t(
                                'Your account is not linked to any organization. Contact support or the business owner to get access.',
                            )}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </div>
        </>
    );
}

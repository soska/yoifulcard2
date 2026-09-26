import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { scan } from '@/routes';

export default function Scan() {
    return (
        <>
            <Head title="Reader" />
            <div className="flex h-full flex-1 flex-col p-4">
                <Heading
                    title="Reader"
                    description="Scan a card's QR code to charge or add funds."
                />
            </div>
        </>
    );
}

Scan.layout = {
    breadcrumbs: [
        {
            title: 'Reader',
            href: scan(),
        },
    ],
};

import { History, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useDateFormat } from '@/hooks/use-date-format';
import { useRecentScans } from '@/hooks/use-reader-storage';

/**
 * The cards scanned on this device, newest first. Only the code and the
 * time are stored (localStorage), never the QR token.
 */
export function RecentScans() {
    const { scans, clear } = useRecentScans();
    const { formatDateTime } = useDateFormat();

    return (
        <Sheet>
            <SheetTrigger
                render={<Button variant="outline" size="lg" className="h-12" />}
            >
                <History data-icon="inline-start" />
                Recent scans
                {scans.length > 0 && (
                    <span className="text-muted-foreground">
                        ({scans.length})
                    </span>
                )}
            </SheetTrigger>
            <SheetContent side="bottom" className="max-h-[80svh]">
                <SheetHeader>
                    <SheetTitle>Recent scans</SheetTitle>
                    <SheetDescription>
                        Kept on this device only.
                    </SheetDescription>
                </SheetHeader>
                <div className="overflow-y-auto px-4">
                    {scans.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <History />
                                </EmptyMedia>
                                <EmptyTitle>No recent scans</EmptyTitle>
                                <EmptyDescription>
                                    Cards you scan will show up here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <ul className="divide-y">
                            {scans.map((scan) => (
                                <li
                                    key={scan.code}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <span className="font-mono text-base font-semibold">
                                        {scan.code}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {formatDateTime(scan.scannedAt)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                {scans.length > 0 && (
                    <SheetFooter>
                        <Button variant="outline" size="lg" onClick={clear}>
                            <Trash2 data-icon="inline-start" />
                            Clear history
                        </Button>
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}

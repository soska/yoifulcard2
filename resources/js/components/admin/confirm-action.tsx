import { Form } from '@inertiajs/react';
import type { ReactElement, ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { __ } from '@/i18n';

type FormSpec = { action: string; method: 'get' | 'post' };

/**
 * A button that asks before it posts. The dialog submits an Inertia form
 * to the given Wayfinder route and closes when the request finishes.
 */
export function ConfirmAction({
    trigger,
    triggerLabel,
    title,
    description,
    confirmLabel,
    form,
    destructive = false,
}: {
    trigger: ReactElement;
    triggerLabel: ReactNode;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    form: FormSpec;
    destructive?: boolean;
}) {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={trigger}>{triggerLabel}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onFinish={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter>
                            <DialogClose
                                render={<Button variant="outline" />}
                                disabled={processing}
                            >
                                {__('Cancel')}
                            </DialogClose>
                            <Button
                                type="submit"
                                variant={
                                    destructive ? 'destructive' : 'default'
                                }
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {confirmLabel}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

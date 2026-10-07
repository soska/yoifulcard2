import { Form } from '@inertiajs/react';
import type { ReactElement, ReactNode } from 'react';
import { useId, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    confirmationEmail,
}: {
    trigger: ReactElement;
    triggerLabel: ReactNode;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    form: FormSpec;
    destructive?: boolean;
    confirmationEmail?: string;
}) {
    const [open, setOpen] = useState(false);
    const [typedEmail, setTypedEmail] = useState('');
    const confirmationId = useId();
    const confirmed =
        confirmationEmail === undefined ||
        typedEmail.trim() === confirmationEmail;
    const changeOpen = (value: boolean) => {
        setTypedEmail('');
        setOpen(value);
    };
    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger render={trigger}>{triggerLabel}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onFinish={() => changeOpen(false)}
                    onSubmit={(event) => {
                        if (!confirmed) event.preventDefault();
                    }}
                >
                    {({ processing }) => (
                        <>
                            {confirmationEmail !== undefined && (
                                <div className="mb-6 space-y-2">
                                    <Label htmlFor={confirmationId}>
                                        {__('Type {email} to confirm', {
                                            email: confirmationEmail,
                                        })}
                                    </Label>
                                    <Input
                                        id={confirmationId}
                                        type="text"
                                        inputMode="email"
                                        autoComplete="off"
                                        spellCheck={false}
                                        value={typedEmail}
                                        onChange={(event) =>
                                            setTypedEmail(event.target.value)
                                        }
                                        disabled={processing}
                                    />
                                </div>
                            )}
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
                                    disabled={processing || !confirmed}
                                >
                                    {processing && <Spinner />}
                                    {confirmLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

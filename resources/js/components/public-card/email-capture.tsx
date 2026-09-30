import { useForm } from '@inertiajs/react';
import { CheckCircle2, Mail } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { email as saveEmail } from '@/routes/public-card';
import { __ } from '@/i18n';

/**
 * The optional "get balance updates" form. v1 stores the email on the card
 * and sends nothing. Once the card has an email the form is replaced by a
 * note, on this visit and every later one.
 */
export function EmailCapture({
    token,
    hasEmail,
}: {
    token: string;
    hasEmail: boolean;
}) {
    const form = useForm({ email: '' });
    const [justSaved, setJustSaved] = useState(false);
    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(saveEmail.url(token), {
            preserveScroll: true,
            onSuccess: () => {
                setJustSaved(true);
                form.reset();
            },
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Mail className="size-4" />
                    {__('Get balance updates')}
                </CardTitle>
                <CardDescription>
                    {__(
                        "Leave your email and we'll let you know about your balance.",
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {hasEmail || justSaved ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        {justSaved
                            ? __('Thanks! Your email is saved.')
                            : __('Your email is saved.')}
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <Field data-invalid={!!form.errors.email}>
                            <FieldLabel htmlFor="public-card-email">
                                {__('Email')}
                            </FieldLabel>
                            <Input
                                id="public-card-email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                placeholder={__('you@example.com')}
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                disabled={form.processing}
                                aria-invalid={!!form.errors.email}
                                required
                            />
                            <FieldError>{form.errors.email}</FieldError>
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {__('Save email')}
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

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

/**
 * The optional "get balance updates" form. v1 stores the email on the card
 * and sends nothing.
 */
export function EmailCapture({ token }: { token: string }) {
    const form = useForm({ email: '' });
    const [saved, setSaved] = useState(false);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(saveEmail.url(token), {
            preserveScroll: true,
            onSuccess: () => {
                setSaved(true);
                form.reset();
            },
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Mail className="size-4" />
                    Get balance updates
                </CardTitle>
                <CardDescription>
                    Leave your email and we&apos;ll let you know about your
                    balance.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {saved ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        Thanks! Your email is saved.
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <Field data-invalid={!!form.errors.email}>
                            <FieldLabel htmlFor="public-card-email">
                                Email
                            </FieldLabel>
                            <Input
                                id="public-card-email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                placeholder="you@example.com"
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
                            Save email
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

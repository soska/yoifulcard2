import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index as adminIndex } from '@/routes/admin';
import { create, index, store } from '@/routes/admin/organizations';
import { useTranslation } from '@/hooks/use-translation';

/** Lowercase letters and numbers joined by single hyphens. */
function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100);
}

export default function AdminOrganizationsCreate() {
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugEdited, setSlugEdited] = useState(false);
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create organization')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title={t('Create organization')}
                    description={t(
                        'Onboard a business and its owner. A new owner email creates an account with a temporary password, shown once.',
                    )}
                />

                <Card className="max-w-2xl">
                    <CardContent>
                        <Form {...store.form()} disableWhileProcessing>
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    <FieldSet>
                                        <FieldLegend>
                                            {t('Business')}
                                        </FieldLegend>
                                        <FieldGroup>
                                            <Field data-invalid={!!errors.name}>
                                                <FieldLabel htmlFor="name">
                                                    {t('Business name')}
                                                </FieldLabel>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    value={name}
                                                    onChange={(event) => {
                                                        setName(
                                                            event.target.value,
                                                        );

                                                        if (!slugEdited) {
                                                            setSlug(
                                                                slugify(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                    required
                                                    maxLength={255}
                                                    placeholder={t(
                                                        'Acme Coffee Shop',
                                                    )}
                                                    aria-invalid={!!errors.name}
                                                />
                                                <FieldError>
                                                    {errors.name}
                                                </FieldError>
                                            </Field>

                                            <Field data-invalid={!!errors.slug}>
                                                <FieldLabel htmlFor="slug">
                                                    {t('Slug')}
                                                </FieldLabel>
                                                <Input
                                                    id="slug"
                                                    name="slug"
                                                    value={slug}
                                                    onChange={(event) => {
                                                        setSlugEdited(true);
                                                        setSlug(
                                                            event.target.value.toLowerCase(),
                                                        );
                                                    }}
                                                    required
                                                    maxLength={100}
                                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                                    placeholder={t(
                                                        'acme-coffee',
                                                    )}
                                                    aria-invalid={!!errors.slug}
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'Lowercase letters, numbers, and hyphens. Must be unique.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.slug}
                                                </FieldError>
                                            </Field>
                                        </FieldGroup>
                                    </FieldSet>

                                    <FieldSet>
                                        <FieldLegend>{t('Owner')}</FieldLegend>
                                        <FieldGroup>
                                            <Field
                                                data-invalid={
                                                    !!errors.owner_email
                                                }
                                            >
                                                <FieldLabel htmlFor="owner_email">
                                                    {t('Owner email')}
                                                </FieldLabel>
                                                <Input
                                                    id="owner_email"
                                                    name="owner_email"
                                                    type="email"
                                                    required
                                                    maxLength={255}
                                                    autoComplete="off"
                                                    placeholder={t(
                                                        'owner@example.com',
                                                    )}
                                                    aria-invalid={
                                                        !!errors.owner_email
                                                    }
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'An existing user with this email becomes the owner. Otherwise a new account is created.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.owner_email}
                                                </FieldError>
                                            </Field>

                                            <Field
                                                data-invalid={
                                                    !!errors.owner_name
                                                }
                                            >
                                                <FieldLabel htmlFor="owner_name">
                                                    {t('Owner name (optional)')}
                                                </FieldLabel>
                                                <Input
                                                    id="owner_name"
                                                    name="owner_name"
                                                    maxLength={255}
                                                    autoComplete="off"
                                                    aria-invalid={
                                                        !!errors.owner_name
                                                    }
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'Used only for a new account. Defaults to the part of the email before the @.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.owner_name}
                                                </FieldError>
                                            </Field>
                                        </FieldGroup>
                                    </FieldSet>

                                    <FieldSet>
                                        <FieldLegend>{t('Plan')}</FieldLegend>
                                        <FieldGroup>
                                            <Field
                                                data-invalid={
                                                    !!errors.card_limit
                                                }
                                            >
                                                <FieldLabel htmlFor="card_limit">
                                                    {t('Card limit')}
                                                </FieldLabel>
                                                <Input
                                                    id="card_limit"
                                                    name="card_limit"
                                                    type="number"
                                                    min={1}
                                                    step={1}
                                                    inputMode="numeric"
                                                    placeholder={t('Unlimited')}
                                                    className="w-40"
                                                    aria-invalid={
                                                        !!errors.card_limit
                                                    }
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'Leave empty for unlimited.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.card_limit}
                                                </FieldError>
                                            </Field>

                                            <Field
                                                data-invalid={
                                                    !!errors.plan_notes
                                                }
                                            >
                                                <FieldLabel htmlFor="plan_notes">
                                                    {t('Plan notes')}
                                                </FieldLabel>
                                                <Textarea
                                                    id="plan_notes"
                                                    name="plan_notes"
                                                    rows={3}
                                                    maxLength={5000}
                                                    placeholder={t(
                                                        'For example: Starter plan, billed annually',
                                                    )}
                                                    aria-invalid={
                                                        !!errors.plan_notes
                                                    }
                                                />
                                                <FieldDescription>
                                                    {t(
                                                        'Only superadmins see these.',
                                                    )}
                                                </FieldDescription>
                                                <FieldError>
                                                    {errors.plan_notes}
                                                </FieldError>
                                            </Field>
                                        </FieldGroup>
                                    </FieldSet>

                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('Create organization')}
                                        </Button>
                                    </div>
                                </FieldGroup>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOrganizationsCreate.layout = {
    breadcrumbs: [
        { titleKey: 'Admin', href: adminIndex() },
        { titleKey: 'Organizations', href: index() },
        { titleKey: 'Create', href: create() },
    ],
};

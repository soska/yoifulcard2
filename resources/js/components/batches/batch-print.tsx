import { Form, router, usePoll } from '@inertiajs/react';
import { AlertCircle, Download, FileText } from 'lucide-react';
import { useEffect } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateFormat } from '@/hooks/use-date-format';
import { cardBatchPdfActionLabel, cardTemplateLabel } from '@/lib/labels';
import type { CardBatchPdf, CardBatchPrint } from '@/types';
import { __ } from '@/i18n';

type FormSpec = { action: string; method: 'get' | 'post' };

/** How often the page checks on a PDF that is being made, in ms. */
const POLL_INTERVAL = 4000;

/**
 * Print the batch's cards in stock as a PDF: pick a template, wait for the
 * queue to make it (the page polls; an email also says when it's ready),
 * then download it once. Below, the batch's PDF log.
 */
export function BatchPrint({
    print,
    form,
    downloadHref,
}: {
    print: CardBatchPrint;
    /** Null when this user can't print (voided batch, read-only business). */
    form: FormSpec | null;
    downloadHref: (pdf: CardBatchPdf) => string;
}) {
    const { formatDateTime } = useDateFormat();
    const { pdf, logs, templates } = print;
    const pending = pdf?.status === 'pending';

    const { start, stop } = usePoll(
        POLL_INTERVAL,
        { only: ['print'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [pending, start, stop]);

    const items = templates.map((template) => ({
        value: template,
        label: cardTemplateLabel(template),
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Print')}</CardTitle>
                <CardDescription>
                    {__(
                        'Make a PDF of the cards still in stock. It holds the code of every card, so anyone with it can use the cards once they are activated. It can be downloaded once and expires after 24 hours.',
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {form && (
                    <Form
                        {...form}
                        disableWhileProcessing
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <FieldGroup>
                                <Field data-invalid={!!errors.template}>
                                    <FieldLabel htmlFor="template">
                                        {__('Template')}
                                    </FieldLabel>
                                    <Select
                                        name="template"
                                        defaultValue={items[0]?.value}
                                        items={items}
                                    >
                                        <SelectTrigger
                                            id="template"
                                            className="w-full sm:w-80"
                                            aria-invalid={!!errors.template}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {items.map((item) => (
                                                <SelectItem
                                                    key={item.value}
                                                    value={item.value}
                                                >
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        {__(
                                            'Cards are bank card size (85.6 × 54 mm). Print sheets at 100% scale.',
                                        )}
                                    </FieldDescription>
                                    <FieldError>{errors.template}</FieldError>
                                </Field>
                                <div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <FileText data-icon="inline-start" />
                                        )}
                                        {__('Make PDF')}
                                    </Button>
                                </div>
                            </FieldGroup>
                        )}
                    </Form>
                )}

                {pdf?.status === 'pending' && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        {__("Making the PDF. We'll email you when it's ready.")}
                    </p>
                )}

                {pdf?.status === 'ready' && (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm">
                            <p className="font-medium">
                                {cardTemplateLabel(pdf.template)}
                            </p>
                            <p className="text-muted-foreground">
                                {__(
                                    {
                                        one: '{count} card. Available until {date}.',
                                        other: '{count} cards. Available until {date}.',
                                    },
                                    {
                                        count: pdf.cards ?? 0,
                                        date: formatDateTime(pdf.expires_at),
                                    },
                                )}
                            </p>
                        </div>
                        <div>
                            <Button
                                size="sm"
                                render={
                                    <a
                                        href={downloadHref(pdf)}
                                        onClick={() =>
                                            // The file is gone once sent:
                                            // refresh the section after.
                                            window.setTimeout(
                                                () =>
                                                    router.reload({
                                                        only: ['print'],
                                                    }),
                                                1500,
                                            )
                                        }
                                    />
                                }
                            >
                                <Download data-icon="inline-start" />
                                {__('Download PDF')}
                            </Button>
                        </div>
                    </div>
                )}

                {pdf?.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>
                            {__('The PDF could not be made')}
                        </AlertTitle>
                        <AlertDescription>
                            {__(
                                'Try again. If it keeps failing, contact support.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {logs.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-muted-foreground">
                            {__('PDF history')}
                        </p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{__('Date')}</TableHead>
                                    <TableHead>{__('Action')}</TableHead>
                                    <TableHead>{__('By')}</TableHead>
                                    <TableHead>{__('Template')}</TableHead>
                                    <TableHead className="text-right">
                                        {__('Cards')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="text-muted-foreground">
                                            {formatDateTime(log.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            {cardBatchPdfActionLabel(
                                                log.action,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {log.user ?? 'Yoiful'}
                                        </TableCell>
                                        <TableCell>
                                            {cardTemplateLabel(log.template)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {log.cards}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

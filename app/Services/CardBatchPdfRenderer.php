<?php

namespace App\Services;

use App\Enums\CardTemplate;
use App\Http\Controllers\PublicCardController;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Lays out a card batch as a PDF with one of the templates in
 * App\Enums\CardTemplate, using dompdf.
 *
 * Each QR is the SVG from CardQrCode, which dompdf draws as vector paths, so
 * it stays sharp at any print size. Nothing is fetched over the network: the
 * logo is read from the public disk and embedded, and a logo hosted anywhere
 * else is left out.
 */
class CardBatchPdfRenderer
{
    public function __construct(private readonly CardQrCode $qr) {}

    /**
     * The PDF and its page count.
     *
     * @param  Collection<int, Card>  $cards
     * @return array{pdf: string, pages: int}
     */
    public function render(CardBatch $batch, CardTemplate $template, Collection $cards): array
    {
        $organization = $batch->organization;
        $color = PublicCardController::brandColor($organization->primary_color);
        [$pageWidth, $pageHeight] = $template->pageSize();

        $html = view($template->view(), [
            'template' => $template,
            'pageWidth' => $pageWidth,
            'pageHeight' => $pageHeight,
            'cardWidth' => CardTemplate::CARD_WIDTH_MM,
            'cardHeight' => CardTemplate::CARD_HEIGHT_MM,
            'bleed' => CardTemplate::BLEED_MM,
            'pages' => $cards->map(fn (Card $card) => [
                'code' => $card->code,
                'qr' => 'data:image/svg+xml;base64,'.base64_encode($this->qr->svg($card)),
            ])->chunk($template->cardsPerPage())->map(fn (Collection $page) => $page->values()->all())->values()->all(),
            'business' => [
                'name' => $organization->name,
                'logo' => self::logoDataUri($organization),
                'color' => $color,
                'ink' => self::inkOn($color),
            ],
            'termsUrl' => $batch->program->terms_url,
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot([resource_path('views/pdf')]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, self::points($pageWidth), self::points($pageHeight)]);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return [
            'pdf' => (string) $dompdf->output(),
            'pages' => $dompdf->getCanvas()->get_page_count(),
        ];
    }

    /**
     * Millimetres to PDF points.
     */
    public static function points(float $millimetres): float
    {
        return round($millimetres * 72 / 25.4, 3);
    }

    /**
     * Black or white, whichever reads better on `$color` (`#rgb` or
     * `#rrggbb`).
     */
    public static function inkOn(string $color): string
    {
        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        [$r, $g, $b] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Contrast with white is (1.05) / (L + 0.05); with black (L + 0.05) / 0.05.
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#111111';
    }

    /**
     * The logo as a data URI, when this app stored it on the public disk.
     */
    private static function logoDataUri(Organization $organization): ?string
    {
        $path = Organization::storedLogoPath($organization->logo_url);
        $disk = Storage::disk('public');

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path);

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}

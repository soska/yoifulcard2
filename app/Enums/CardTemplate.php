<?php

namespace App\Enums;

/**
 * The layouts a card batch can be printed with, defined in code. Each one
 * is a Blade view under resources/views/pdf that App\Services\CardBatchPdfRenderer
 * fills with the business's logo, brand color and name, and each card's QR
 * and code.
 *
 * Cards are CR80 (85.6 x 54 mm), the size of a bank card. The batch records
 * the template it was last printed with (card_batches.template).
 */
enum CardTemplate: string
{
    /** 10 cards on a US Letter sheet with cut marks, for printing in house. */
    case SheetLetter = 'sheet_letter';

    /** 10 cards on an A4 sheet with cut marks, for printing in house. */
    case SheetA4 = 'sheet_a4';

    /**
     * One card per page at CR80 plus bleed, for a print shop. When the
     * program has a terms URL, each front is followed by a back with it.
     */
    case PrintShop = 'print_shop';

    /** Card width, in millimetres. */
    public const CARD_WIDTH_MM = 85.6;

    /** Card height, in millimetres. */
    public const CARD_HEIGHT_MM = 54.0;

    /** Background that runs past the trim line on print shop pages. */
    public const BLEED_MM = 3.0;

    public function audience(): TemplateAudience
    {
        return match ($this) {
            self::SheetLetter, self::SheetA4 => TemplateAudience::Business,
            self::PrintShop => TemplateAudience::Admin,
        };
    }

    /**
     * Whether someone printing from `$audience` can use this template. The
     * admin area can use every template.
     */
    public function availableTo(TemplateAudience $audience): bool
    {
        return $audience === TemplateAudience::Admin || $this->audience() === $audience;
    }

    /**
     * The templates `$audience` can print with, in the order forms offer them.
     *
     * @return list<self>
     */
    public static function for(TemplateAudience $audience): array
    {
        return array_values(array_filter(self::cases(), fn (self $template) => $template->availableTo($audience)));
    }

    /**
     * The Blade view that lays out the whole document.
     *
     * @return view-string
     */
    public function view(): string
    {
        return match ($this) {
            self::SheetLetter, self::SheetA4 => 'pdf.card-sheet',
            self::PrintShop => 'pdf.card-print-shop',
        };
    }

    /**
     * Page size in millimetres, as [width, height].
     *
     * @return array{0: float, 1: float}
     */
    public function pageSize(): array
    {
        return match ($this) {
            self::SheetLetter => [215.9, 279.4],
            self::SheetA4 => [210.0, 297.0],
            self::PrintShop => [self::CARD_WIDTH_MM + 2 * self::BLEED_MM, self::CARD_HEIGHT_MM + 2 * self::BLEED_MM],
        };
    }

    /**
     * How many cards fit on one page.
     */
    public function cardsPerPage(): int
    {
        return match ($this) {
            self::SheetLetter, self::SheetA4 => 10,
            self::PrintShop => 1,
        };
    }
}

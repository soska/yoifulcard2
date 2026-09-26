<?php

namespace App\Services;

use App\Models\Card;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a card's QR code on the server, so the token never leaves it.
 */
class CardQrCode
{
    public const SVG_SIZE = 320;

    public const PNG_SIZE = 1024;

    public function svg(Card $card): string
    {
        $renderer = new ImageRenderer(new RendererStyle(self::SVG_SIZE, 2), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($card->qrPayload());
    }

    public function png(Card $card): string
    {
        return (new Writer(new GDLibRenderer(self::PNG_SIZE, 4)))->writeString($card->qrPayload());
    }
}

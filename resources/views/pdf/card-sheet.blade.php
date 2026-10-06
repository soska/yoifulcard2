{{--
    Sheet (App\Enums\CardTemplate::SheetLetter, ::SheetA4): 10 cards, 2 across
    and 5 down, butted together and centered on the page, with cut marks in
    the margins along every cut line. For printing in house.

    Desktop printers leave about 5 mm of the paper's edge blank, so marks
    stay at least 6 mm ($safe) from it. On Letter the margins above and
    below the cards are thinner than that (4.7 mm): the column cuts are
    marked instead by a hairline guide along them, over the cards, which the
    cut takes away, and the top and bottom cuts by the edge of the brand
    band, since a mark there would not print.
--}}
@php
    $columns = 2;
    $rows = 5;
    $left = ($pageWidth - $columns * $cardWidth) / 2;
    $top = ($pageHeight - $rows * $cardHeight) / 2;
    $bottom = $top + $rows * $cardHeight;
    $safe = 6;
    // Cut marks stop short of the cards and fit the margin they are in.
    $gap = 1;
    $verticalMark = min(5, $top - $gap - $safe);
    $horizontalMark = min(5, $left - $gap - $safe);
    $columnGuides = $verticalMark < 2;
    $stroke = 0.2;
    $guideStroke = 0.1;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.styles')
</head>
<body>
@foreach ($pages as $page)
    <div class="page{{ $loop->last ? '' : ' page-break' }}">
        @foreach ($page as $index => $card)
            @include('pdf.partials.card-front', [
                'x' => $left + ($index % $columns) * $cardWidth,
                'y' => $top + intdiv($index, $columns) * $cardHeight,
                'bleed' => 0,
            ])
        @endforeach

        @for ($column = 0; $column <= $columns; $column++)
            @if ($columnGuides)
                <div class="abs guide" style="left: {{ $left + $column * $cardWidth - $guideStroke / 2 }}mm; top: {{ max($top, $safe) }}mm; width: {{ $guideStroke }}mm; height: {{ min($bottom, $pageHeight - $safe) - max($top, $safe) }}mm;"></div>
            @else
                @php($markX = $left + $column * $cardWidth - $stroke / 2)
                <div class="abs mark" style="left: {{ $markX }}mm; top: {{ $top - $gap - $verticalMark }}mm; width: {{ $stroke }}mm; height: {{ $verticalMark }}mm;"></div>
                <div class="abs mark" style="left: {{ $markX }}mm; top: {{ $bottom + $gap }}mm; width: {{ $stroke }}mm; height: {{ $verticalMark }}mm;"></div>
            @endif
        @endfor

        @for ($row = 0; $row <= $rows; $row++)
            @php($markY = $top + $row * $cardHeight - $stroke / 2)
            @continue($markY < $safe || $markY + $stroke > $pageHeight - $safe)
            <div class="abs mark" style="left: {{ $left - $gap - $horizontalMark }}mm; top: {{ $markY }}mm; width: {{ $horizontalMark }}mm; height: {{ $stroke }}mm;"></div>
            <div class="abs mark" style="left: {{ $left + $columns * $cardWidth + $gap }}mm; top: {{ $markY }}mm; width: {{ $horizontalMark }}mm; height: {{ $stroke }}mm;"></div>
        @endfor
    </div>
@endforeach
</body>
</html>

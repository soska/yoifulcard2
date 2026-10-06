{{--
    Sheet (App\Enums\CardTemplate::SheetLetter, ::SheetA4): 10 cards, 2 across
    and 5 down, butted together and centered on the page, with cut marks in
    the margins along every cut line. For printing in house.
--}}
@php
    $columns = 2;
    $rows = 5;
    $left = ($pageWidth - $columns * $cardWidth) / 2;
    $top = ($pageHeight - $rows * $cardHeight) / 2;
    // Cut marks stop short of the cards and fit the margin they are in.
    $gap = 1;
    $verticalMark = min(5, $top - $gap - 0.5);
    $horizontalMark = min(5, $left - $gap - 0.5);
    $stroke = 0.2;
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
            @php($markX = $left + $column * $cardWidth - $stroke / 2)
            <div class="abs mark" style="left: {{ $markX }}mm; top: {{ $top - $gap - $verticalMark }}mm; width: {{ $stroke }}mm; height: {{ $verticalMark }}mm;"></div>
            <div class="abs mark" style="left: {{ $markX }}mm; top: {{ $top + $rows * $cardHeight + $gap }}mm; width: {{ $stroke }}mm; height: {{ $verticalMark }}mm;"></div>
        @endfor

        @for ($row = 0; $row <= $rows; $row++)
            @php($markY = $top + $row * $cardHeight - $stroke / 2)
            <div class="abs mark" style="left: {{ $left - $gap - $horizontalMark }}mm; top: {{ $markY }}mm; width: {{ $horizontalMark }}mm; height: {{ $stroke }}mm;"></div>
            <div class="abs mark" style="left: {{ $left + $columns * $cardWidth + $gap }}mm; top: {{ $markY }}mm; width: {{ $horizontalMark }}mm; height: {{ $stroke }}mm;"></div>
        @endfor
    </div>
@endforeach
</body>
</html>

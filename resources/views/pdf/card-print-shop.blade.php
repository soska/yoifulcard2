{{--
    Print shop (App\Enums\CardTemplate::PrintShop): one card per page, the
    page being the card (CR80) plus bleed on every side. Keep text and the QR
    well inside the trim line. When the program has a terms URL, each front
    is followed by a back that shows it, for duplex printing.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.styles')
</head>
<body>
@foreach ($pages as $page)
    @foreach ($page as $card)
        @php($last = $loop->parent->last && $loop->last)
        <div class="page{{ $last && ! $termsUrl ? '' : ' page-break' }}">
            @include('pdf.partials.card-front', ['x' => $bleed, 'y' => $bleed])
        </div>
        @if ($termsUrl)
            <div class="page band{{ $last ? '' : ' page-break' }}">
                <div class="abs name" style="left: {{ $bleed + 6 }}mm; top: {{ $bleed + 14 }}mm; width: {{ $cardWidth - 12 }}mm; height: 6mm; font-size: 12pt; text-align: center; white-space: nowrap;">{{ $business['name'] }}</div>
                <div class="abs" style="left: {{ $bleed + 6 }}mm; top: {{ $bleed + 30 }}mm; width: {{ $cardWidth - 12 }}mm; color: {{ $business['ink'] }}; font-size: 6.5pt; text-align: center;">{{ __('Terms and conditions') }}</div>
                <div class="abs" style="left: {{ $bleed + 6 }}mm; top: {{ $bleed + 34 }}mm; width: {{ $cardWidth - 12 }}mm; color: {{ $business['ink'] }}; font-size: 7pt; text-align: center;">{{ $termsUrl }}</div>
            </div>
        @endif
    @endforeach
@endforeach
</body>
</html>

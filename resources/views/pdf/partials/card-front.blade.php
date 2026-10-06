{{--
    One card front, CR80 (85.6 x 54 mm), placed at ($x, $y) mm on the page.
    $bleed runs the brand band past the trim line on print shop pages.
--}}
@php($band = 14)
<div class="abs band" style="left: {{ $x - $bleed }}mm; top: {{ $y - $bleed }}mm; width: {{ $cardWidth + 2 * $bleed }}mm; height: {{ $band + $bleed }}mm;"></div>
@if ($business['logo'])
    <div class="abs logo-tile" style="left: {{ $x + 4 }}mm; top: {{ $y + 2.5 }}mm; width: 9mm; height: 9mm;">
        <img src="{{ $business['logo'] }}" alt="" style="width: 9mm; height: 9mm;">
    </div>
@endif
<div class="abs name" style="left: {{ $x + ($business['logo'] ? 16 : 4) }}mm; top: {{ $y + 4.6 }}mm; width: {{ $cardWidth - ($business['logo'] ? 20 : 8) }}mm; height: 5mm; white-space: nowrap;">{{ $business['name'] }}</div>
<img class="abs" src="{{ $card['qr'] }}" alt="" style="left: {{ $x + 3 }}mm; top: {{ $y + $band + 3 }}mm; width: 34mm; height: 34mm;">
<div class="abs label" style="left: {{ $x + 41 }}mm; top: {{ $y + $band + 8 }}mm;">{{ __('Gift card') }}</div>
<div class="abs code" style="left: {{ $x + 41 }}mm; top: {{ $y + $band + 11.5 }}mm;">{{ $card['code'] }}</div>
<div class="abs hint" style="left: {{ $x + 41 }}mm; top: {{ $y + $band + 22 }}mm; width: {{ $cardWidth - 45 }}mm;">{{ __('Scan the code to see your balance.') }}</div>

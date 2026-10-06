{{-- Shared by the card PDF templates (App\Enums\CardTemplate). Sizes in mm and pt. --}}
<style>
    @page { size: {{ $pageWidth }}mm {{ $pageHeight }}mm; margin: 0; }
    * { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111111; }
    .page { position: relative; width: {{ $pageWidth }}mm; height: {{ $pageHeight }}mm; overflow: hidden; }
    .page-break { page-break-after: always; }
    .abs { position: absolute; }
    .band { background: {{ $business['color'] }}; }
    .logo-tile { background: #ffffff; border-radius: 1.5mm; text-align: center; }
    .logo-tile img { max-width: 100%; max-height: 100%; }
    .name { color: {{ $business['ink'] }}; font-weight: bold; font-size: 10pt; line-height: 1.15; overflow: hidden; }
    .label { color: #6b7280; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3pt; }
    .code { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: 11.5pt; }
    .hint { color: #6b7280; font-size: 6pt; line-height: 1.3; }
    .mark { background: #000000; }
    .guide { background: #9ca3af; }
</style>

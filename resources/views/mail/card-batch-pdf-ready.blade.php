<x-mail::message>
# {{ __('Your card batch PDF is ready') }}

{{ __('The card batch PDF for :business is ready to download.', ['business' => $business]) }}

<x-mail::button :url="$url">
{{ __('Go to the batch') }}
</x-mail::button>

{{ __('It can be downloaded once, within :hours hours. It holds the code of every card: keep it private and delete it after printing.', ['hours' => $hours]) }}
</x-mail::message>

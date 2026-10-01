<x-mail::message>
# {{ __(':business sent you a gift card', ['business' => $business]) }}

{{ __('Your balance is :balance.', ['balance' => $balance]) }}

<x-mail::button :url="$url">
{{ __('Open your card') }}
</x-mail::button>

{{ __('Show the code on that page when you pay. Keep this email: anyone with the link can use the card.') }}
</x-mail::message>

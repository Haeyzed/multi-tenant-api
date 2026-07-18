<x-mail::message>
# Your trial has ended

The trial for **{{ $tenantName }}** on the **{{ $planName }}** plan has ended. An invoice is ready and your subscription is in a short grace period.

Subscribe and pay now to restore full access.

<x-mail::button :url="$checkoutUrl">
Subscribe / Pay
</x-mail::button>

If the button does not work, open this URL:

{{ $checkoutUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

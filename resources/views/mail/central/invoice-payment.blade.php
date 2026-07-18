<x-mail::message>
# Invoice {{ $invoiceNumber }}

Hi {{ $tenantName }},

You have an invoice for **{{ strtoupper($currency) }} {{ $amount }}** ready for payment.

Choose your preferred payment provider and complete checkout securely.

<x-mail::button :url="$paymentUrl">
View invoice & pay
</x-mail::button>

If the button does not work, open this URL:

{{ $paymentUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

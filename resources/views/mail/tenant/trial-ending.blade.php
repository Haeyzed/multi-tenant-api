<x-mail::message>
# Your trial is ending soon

**{{ $tenantName }}** is on the **{{ $planName }}** plan. Your trial ends on {{ $trialEndsAt->toDayDateTimeString() }} UTC (about **{{ $daysLeft }}** day(s) left).

Subscribe now to keep access after the trial. You can pay securely via the link below.

<x-mail::button :url="$checkoutUrl">
Subscribe / Pay
</x-mail::button>

If the button does not work, open this URL:

{{ $checkoutUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

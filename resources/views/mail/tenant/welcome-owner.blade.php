<x-mail::message>
# Welcome to {{ $tenantName }}

Your store platform is ready at **{{ $primaryDomain }}**.

Set your owner password to get started. This link expires on {{ $expiresAt->toDayDateTimeString() }} UTC.

<x-mail::button :url="$setupUrl">
Set password
</x-mail::button>

If the button does not work, open this URL:

{{ $setupUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

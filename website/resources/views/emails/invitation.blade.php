<x-mail::message>
# {{ __("You've Been Invited!") }}

**{{ $inviterName }}** {{ __('has invited you to join the') }} **{{ $workspaceName }}** {{ __('workspace as a') }} **{{ $role }}**.

<x-mail::button :url="$acceptUrl">
{{ __('Accept Invitation') }}
</x-mail::button>

@if($expiresAt)
{{ __('This invitation expires on') }} **{{ $expiresAt }}**.
@endif

{{ __("If you didn't expect this invitation, you can safely ignore this email.") }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>

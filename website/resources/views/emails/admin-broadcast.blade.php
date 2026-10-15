<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#172033">
<table role="presentation" width="100%"><tr><td align="center" style="padding:24px">
<table role="presentation" width="100%" style="max-width:600px;background:white;border-radius:16px;border-top:5px solid {{ $broadcast->accent }}"><tr><td style="padding:32px">
<img src="{{ asset('images/dahimail-logo.png') }}" alt="{{ config('app.name') }}" width="56" height="56" style="border-radius:12px">
<p style="color:#64748b">{{ ucfirst($broadcast->category) }}</p>
<h1 style="font-size:24px">{{ $broadcast->renderFor($recipient, $broadcast->title) }}</h1>
<div style="white-space:pre-wrap;line-height:1.6">{{ $broadcast->renderFor($recipient, $broadcast->body) }}</div>
@if($broadcast->action_url && $broadcast->button_label)<p style="margin-top:28px"><a href="{{ $broadcast->action_url }}" style="background:{{ $broadcast->accent }};color:white;padding:12px 20px;border-radius:8px;text-decoration:none">{{ $broadcast->button_label }}</a></p>@endif
<p style="font-size:12px;color:#64748b;margin-top:32px">{{ config('app.name') }} · <a href="{{ url('/settings/notifications') }}">{{ __('Notification preferences') }}</a></p>
</td></tr></table></td></tr></table></body></html>

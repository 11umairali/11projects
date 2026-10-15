<x-layouts.admin :title="$broadcast->title" subtitle="Review the content and audience before sending.">
<div class="max-w-4xl space-y-6">
<a href="{{ route('admin.broadcasts.index') }}" class="btn-secondary">← Broadcasts</a>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div class="border border-border rounded-xl p-5 bg-surface-2"><p class="text-muted">Push preview · {{ ucfirst($broadcast->category) }}</p><h2 class="font-semibold">{{ $broadcast->renderFor($recipient, $broadcast->title) }}</h2><p class="whitespace-pre-wrap">{{ $broadcast->renderFor($recipient, $broadcast->body) }}</p></div>
<iframe title="Email preview" sandbox="" style="width:100%;height:420px;border:1px solid #cbd5e1;border-radius:12px" srcdoc="{{ view('emails.admin-broadcast', compact('broadcast', 'recipient'))->render() }}"></iframe>
@if($broadcast->status === 'draft')
<details class="border border-border p-4 rounded-xl"><summary>Edit template</summary><form method="post" action="{{ route('admin.broadcasts.update', $broadcast) }}" class="space-y-4 pt-4">@method('PUT')@include('admin.broadcast-form')</form></details>
<form method="post" action="{{ route('admin.broadcasts.send', $broadcast) }}" class="space-y-3 border border-border rounded-xl p-5" onsubmit="return confirm('Send this broadcast to the selected audience?')">@csrf
<label>Audience<select class="input w-full" name="audience"><option value="test">Test: my account only (creates a copy)</option><option value="selected">Selected registered users</option><option value="all">All active registered users</option></select></label>
<label>Select users (for selected audience)<select name="user_ids[]" multiple size="6" class="input w-full">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></label>
<p class="text-sm text-muted">Showing up to 500 users. Use the search below to find a specific recipient. User notification preferences are respected.</p><button class="btn-primary">Queue delivery</button></form>
<form method="get" class="flex gap-2"><input name="q" value="{{ request('q') }}" class="input flex-1" placeholder="Find recipients by name or email"><button class="btn-secondary">Find recipients</button></form>
@endif
<section><h2 class="font-semibold">Delivery status: {{ $broadcast->status }}</h2><p>{{ $broadcast->audience_count }} recipients</p>
@foreach(['push','email'] as $channel)<h3 class="mt-3 font-semibold">{{ ucfirst($channel) }}</h3>@foreach($deliveries->countBy($channel.'_status') as $status => $count)<span class="mr-4">{{ $status }}: {{ $count }}</span>@endforeach @endforeach
<p class="text-sm text-muted mt-3">Push “submitted” is a provider handoff, not a device receipt. Failed or interrupted deliveries are not retried automatically to avoid duplicates.</p></section>
<form method="post" action="{{ route('admin.broadcasts.duplicate', $broadcast) }}">@csrf<button class="btn-secondary">Use as template / duplicate draft</button></form>
</div></x-layouts.admin>

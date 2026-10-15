<x-layouts.admin title="Broadcasts" subtitle="Design announcements and deliver them to registered users.">
<div class="max-w-4xl space-y-6">
<p>{{ $audienceCount }} active registered users. Configure transport in <a href="{{ url('/admin/notifications') }}" class="text-brand">Push settings</a> and <a href="{{ url('/admin/settings') }}" class="text-brand">Email settings</a>.</p>
<form method="post" action="{{ route('admin.broadcasts.store') }}" class="bg-surface-2 border border-border rounded-2xl p-6 space-y-4"><h2 class="font-semibold text-lg">New template / draft</h2>@include('admin.broadcast-form')</form>
<section class="space-y-3"><h2 class="text-lg font-semibold">Saved drafts & delivery history</h2>@forelse($broadcasts as $item)<a href="{{ route('admin.broadcasts.show', $item) }}" class="block p-4 bg-surface-2 border border-border rounded-xl"><strong>{{ $item->title }}</strong><span class="text-muted ml-3">{{ $item->category }} · {{ $item->status }} · {{ $item->audience_count }} recipients</span></a>@empty<p>No broadcasts yet.</p>@endforelse{{ $broadcasts->links() }}</section>
<section><h2 class="text-lg font-semibold">Registered audience</h2><form method="get" class="flex gap-2 my-3"><input name="q" value="{{ request('q') }}" class="input flex-1" placeholder="Search name or email"><button class="btn-secondary">Search</button></form>
@foreach($users as $user)<div class="py-2 border-b border-border">{{ $user->name }} <span class="text-muted">{{ $user->email }}</span></div>@endforeach{{ $users->withQueryString()->links() }}</section>
</div></x-layouts.admin>

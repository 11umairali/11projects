@csrf
@if($errors->any())<div role="alert" class="text-red-600">{{ $errors->first() }}</div>@endif
<div class="grid gap-4 sm:grid-cols-2">
<label>Category<select name="category" class="input w-full">@foreach(['general','system','marketing','advertising','misc'] as $category)<option @selected(old('category', $broadcast->category ?? 'general') === $category)>{{ $category }}</option>@endforeach</select></label>
<label>Accent color<input type="color" name="accent" value="{{ old('accent', $broadcast->accent ?? '#2563EB') }}" class="input w-full"></label>
</div>
<label class="block">Title / email subject<input name="title" maxlength="160" required value="{{ old('title', $broadcast->title ?? '') }}" class="input w-full"></label>
<label class="block">Content<textarea name="body" rows="8" maxlength="12000" required class="input w-full">{{ old('body', $broadcast->body ?? '') }}</textarea></label>
<p class="text-sm text-muted">Personalize with @{{name}} and @{{email}}. Content is rendered as plain text for push and a branded email.</p>
<div class="grid gap-4 sm:grid-cols-2"><label>Button label<input name="button_label" maxlength="60" class="input w-full" value="{{ old('button_label', $broadcast->button_label ?? '') }}"></label><label>Button destination (HTTPS)<input name="action_url" type="url" class="input w-full" value="{{ old('action_url', $broadcast->action_url ?? '') }}"></label></div>
<div class="flex gap-6"><label><input type="checkbox" name="push" value="1" @checked(old('push', $broadcast->push ?? true))> App, web & mobile push</label><label><input type="checkbox" name="email" value="1" @checked(old('email', $broadcast->email ?? false))> Email</label></div>
<button class="btn-primary">Save draft & preview</button>

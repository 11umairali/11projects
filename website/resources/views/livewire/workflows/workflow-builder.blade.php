{{--
    Visual Flow Builder -- Single-page three-panel layout
    Left: Config/Details panel (w-80)
    Center: Visual flow canvas (flex-1)
    Right: Add Node panel (w-64)

    All wire:click / wire:model bindings target existing WorkflowBuilder.php methods & properties.
--}}

@php
    // ── Icon SVG path data (reused across canvas nodes AND the left panel) ──
    $triggerIconPaths = [
        'email_received'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'contact_created'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>',
        'contact_updated'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
        'deal_stage_changed'=> '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
        'tag_added'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        'tag_removed'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        'form_submitted'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'webhook_received'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
        'scheduled'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'campaign_opened'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
        'campaign_clicked'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>',
    ];

    $subtypeIcons = [
        'send_email'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'send_notification' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
        'add_tag'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        'remove_tag'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        'update_contact'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
        'assign_agent'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'create_deal'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'move_deal'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
        'wait_delay'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'webhook_call'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
        'ai_reply'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
        'if_else'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'has_tag'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>',
        'contact_field'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'email_opened'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
        'email_clicked'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>',
        'add_to_group'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'remove_from_group' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'in_group'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ];

    // Short descriptions for the right-panel add-node cards
    $actionDescriptions = [
        'send_email'        => __('Compose and send an email'),
        'send_notification' => __('Alert via email, Slack, or in-app'),
        'add_tag'           => __('Attach a tag to the contact'),
        'remove_tag'        => __('Detach a tag from the contact'),
        'update_contact'    => __('Change a contact field value'),
        'assign_agent'      => __('Route to a team member'),
        'create_deal'       => __('Open a new deal in pipeline'),
        'move_deal'         => __('Advance a deal to next stage'),
        'wait_delay'        => __('Pause before the next step'),
        'webhook_call'      => __('Send data to an external app via Webhook'),
        'ai_reply'          => __('Generate a reply with AI'),
        'add_to_group'      => __('Add contact to a group'),
        'remove_from_group' => __('Remove contact from a group'),
    ];

    $conditionDescriptions = [
        'if_else'       => __('Branch on a field value'),
        'has_tag'       => __('Check if contact has a tag'),
        'contact_field' => __('Evaluate a contact property'),
        'email_opened'  => __('Was the email opened?'),
        'email_clicked' => __('Was a link clicked?'),
        'in_group'      => __('Check if contact is in a group'),
    ];

    $currentTriggerIcon = $triggerIconPaths[$triggerType] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>';
@endphp

<div class="flex flex-col h-[calc(100vh-4rem)] -m-6"
     x-data="{
         rightPanelOpen: true,
         justAddedIndex: null,
         canvasFlash: false,
         toastMessage: '',
         toastVisible: false,
         flashAdded(index) {
             this.justAddedIndex = index;
             setTimeout(() => { this.justAddedIndex = null; }, 1500);
             this.$nextTick(() => {
                 const el = document.getElementById('node-card-' + index);
                 if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
             });
         },
         showToast(message) {
             this.toastMessage = message;
             this.toastVisible = true;
             setTimeout(() => { this.toastVisible = false; }, 1800);
         },
         flashCanvas() {
             this.canvasFlash = true;
             setTimeout(() => { this.canvasFlash = false; }, 400);
         }
     }"
     x-on:node-added.window="flashAdded($event.detail.index)"
     x-on:canvas-state-restored.window="
         flashCanvas();
         showToast($event.detail.action === 'undo' ? 'Undone' : 'Redone');
     "
     x-on:keydown.window="
         if (($event.ctrlKey || $event.metaKey) && !$event.shiftKey && $event.key === 'z') { $event.preventDefault(); $wire.undo(); }
         else if (($event.ctrlKey || $event.metaKey) && $event.shiftKey && $event.key === 'z') { $event.preventDefault(); $wire.redo(); }
         else if (($event.ctrlKey || $event.metaKey) && $event.key === 'y') { $event.preventDefault(); $wire.redo(); }
     "
>

    {{-- ================================================================== --}}
    {{-- TOP BAR                                                            --}}
    {{-- ================================================================== --}}
    <div class="flex-shrink-0 h-14 bg-surface-2 border-b border-border flex items-center justify-between px-4 z-20">
        {{-- Left: back + inline name --}}
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('workflows') }}" wire:navigate
               class="flex items-center gap-1.5 text-sm text-muted hover:text-ink transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">{{ __('Workflows') }}</span>
            </a>
            <span class="text-muted/50 hidden sm:inline">/</span>
            <div class="min-w-0 flex-1" x-data="{ editing: false }">
                <input x-show="editing" x-ref="nameInput" @blur="editing = false" @keydown.enter="editing = false"
                       wire:model.blur="name"
                       class="text-sm font-semibold text-ink bg-transparent border-b-2 border-indigo-500 outline-none py-0.5 w-full max-w-xs"
                       placeholder="{{ __('Untitled Workflow') }}">
                <button x-show="!editing" @click="editing = true; $nextTick(() => $refs.nameInput.focus())"
                        class="text-sm font-semibold text-ink hover:text-brand truncate max-w-xs flex items-center gap-1.5 transition-colors">
                    {{ $name ?: __('Untitled Workflow') }}
                    <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            </div>
        </div>

        {{-- Center: node count badge --}}
        <div class="hidden md:flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full
                {{ $triggerType ? 'bg-info/10 text-info' : 'bg-surface  text-muted' }}">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                {{ $triggerType ? ($triggerTypes[$triggerType] ?? $triggerType) : __('No trigger') }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-surface  text-muted ">
                {{ count($nodes) }} {{ count($nodes) === 1 ? 'step' : 'steps' }}
            </span>
        </div>

        {{-- Right: actions --}}
        <div class="flex items-center gap-2 shrink-0">
            {{-- Undo / Redo --}}
            <div class="flex items-center border border-border rounded-lg overflow-hidden">
                <button wire:click="undo"
                        @if(!$this->canUndo()) disabled @endif
                        class="p-2 text-muted hover:text-ink/80 hover:bg-surface  transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-muted"
                        title="{{ $this->canUndo() ? count($undoStack) . ' ' . (count($undoStack) === 1 ? 'step' : 'steps') . ' to undo (Ctrl+Z)' : 'Nothing to undo' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
                </button>
                <div class="w-px h-5 bg-gray-200 dark:bg-gray-700"></div>
                <button wire:click="redo"
                        @if(!$this->canRedo()) disabled @endif
                        class="p-2 text-muted hover:text-ink/80 hover:bg-surface  transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-muted"
                        title="{{ $this->canRedo() ? count($redoStack) . ' ' . (count($redoStack) === 1 ? 'step' : 'steps') . ' to redo (Ctrl+Y)' : 'Nothing to redo' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2M21 10l-4-4M21 10l-4 4"/></svg>
                </button>
            </div>

            {{-- Toggle right panel --}}
            <button @click="rightPanelOpen = !rightPanelOpen"
                    class="p-2 text-muted hover:text-muted  hover:bg-surface  rounded-lg transition-colors hidden lg:flex"
                    :title="__('Toggle node panel')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            </button>

            <button wire:click="save" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-lg hover:bg-surface hover:border-border transition-all disabled:opacity-50 flex items-center gap-2">
                <span wire:loading.remove wire:target="save" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    {{ __('Save Draft') }}
                </span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Saving...') }}
                </span>
            </button>

            <button wire:click="activate" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand-strong transition-all disabled:opacity-50 shadow-sm flex items-center gap-2">
                <span wire:loading.remove wire:target="activate" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ __('Activate') }}
                </span>
                <span wire:loading wire:target="activate" class="inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Activating...') }}
                </span>
            </button>
        </div>
    </div>

    {{-- Validation errors banner --}}
    @if($errors->any())
    <div class="flex-shrink-0 px-4 py-2 bg-danger/10 border-b border-danger/20 text-sm text-danger flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ $errors->first() }}</span>
        @if($errors->count() > 1)
        <span class="text-red-400"> (+{{ $errors->count() - 1 }} more)</span>
        @endif
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- THREE-PANEL BODY                                                   --}}
    {{-- ================================================================== --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ============================================================== --}}
        {{-- LEFT SIDEBAR: Config / Details Panel                           --}}
        {{-- ============================================================== --}}
        <aside class="w-80 flex-shrink-0 bg-surface-2 border-r border-border overflow-y-auto hidden lg:block">
            <div class="p-5 space-y-6">

                {{-- Section: Workflow Details --}}
                <div>
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ __('Details') }}
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Name') }} <span class="text-red-400">*</span></label>
                            <input type="text" wire:model.blur="name" placeholder="e.g., Auto-Reply to Support"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface hover:bg-surface transition-all">
                            @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Description') }}</label>
                            <textarea wire:model.blur="description" rows="2" placeholder="{{ __('What does this workflow do?') }}"
                                      class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface hover:bg-surface transition-all resize-y"></textarea>
                        </div>
                    </div>
                </div>

                <hr class="border-border">

                {{-- Section: Trigger --}}
                <div>
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('Trigger') }}
                    </h3>
                    <div>
                        <label class="block text-xs font-medium text-muted  mb-1">{{ __('When this happens') }} <span class="text-red-400">*</span></label>
                        <select wire:model.live="triggerType"
                                class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface hover:bg-surface transition-all">
                            <option value="">{{ __('Select trigger...') }}</option>
                            @foreach($triggerTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('triggerType') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Trigger-specific config: Scheduled --}}
                    @if($triggerType === 'scheduled')
                    <div class="mt-3 p-3 bg-surface rounded-lg border border-border space-y-3">
                        <p class="text-xs font-semibold text-muted flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ __('Schedule Config') }}
                        </p>
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('Cron Expression') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="triggerConfig.cron_expression" placeholder="0 9 * * 1"
                                   class="w-full px-3 py-2 text-xs font-mono border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                            <p class="text-[10px] text-muted mt-1">min hour dom month dow</p>
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('Timezone') }}</label>
                            <select wire:model.live="triggerConfig.timezone"
                                    class="w-full px-3 py-2 text-xs border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">Eastern (US)</option>
                                <option value="America/Chicago">Central (US)</option>
                                <option value="America/Denver">Mountain (US)</option>
                                <option value="America/Los_Angeles">Pacific (US)</option>
                                <option value="Europe/London">London (GMT)</option>
                                <option value="Europe/Paris">Paris (CET)</option>
                                <option value="Asia/Kolkata">India (IST)</option>
                                <option value="Asia/Tokyo">Tokyo (JST)</option>
                                <option value="Australia/Sydney">Sydney (AEST)</option>
                            </select>
                        </div>
                    </div>
                    @endif

                    {{-- Trigger-specific config: Email Received --}}
                    @if($triggerType === 'email_received')
                    <div class="mt-3 p-3 bg-surface rounded-lg border border-border space-y-3">
                        <p class="text-xs font-semibold text-muted flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            {{ __('Email Filters') }}
                        </p>
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('From contains') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="triggerConfig.from_filter" placeholder="@example.com"
                                   class="w-full px-3 py-2 text-xs border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('Subject contains') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="triggerConfig.subject_filter" placeholder="support, billing"
                                   class="w-full px-3 py-2 text-xs border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                        </div>
                    </div>
                    @endif

                    {{-- Trigger-specific config: Webhook --}}
                    @if($triggerType === 'webhook_received')
                    <div class="mt-3 p-3 bg-surface rounded-lg border border-border space-y-2">
                        <p class="text-xs font-semibold text-muted flex items-center gap-1.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            {{ __('Webhook') }}
                        </p>
                        @if($workflowId)
                        @php $wf = \App\Models\Workflow::find($workflowId); @endphp
                        <div>
                            <label class="block text-xs text-muted mb-1">{{ __('Trigger URL') }}</label>
                            <input type="text" value="{{ url('/api/webhooks/workflow/' . ($wf->webhook_token ?? 'TOKEN')) }}" readonly
                                   class="w-full px-3 py-2 text-[10px] font-mono border border-border rounded-lg bg-surface  text-muted">
                            <p class="text-[10px] text-muted mt-1">{{ __('Send requests to this URL to trigger the workflow from external services.') }}</p>
                        </div>
                        @else
                        <div class="flex items-center gap-1.5 px-3 py-2 bg-info/10 rounded-lg text-xs text-blue-600">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('URL generated after first save.') }}
                        </div>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- ====================================================== --}}
                {{-- Section: Node Editor (when a node is selected)         --}}
                {{-- ====================================================== --}}
                @if($editingNodeIndex !== null && isset($nodes[$editingNodeIndex]))
                @php $editNode = $nodes[$editingNodeIndex]; @endphp
                <hr class="border-border">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold text-muted uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit Step #{{ $editingNodeIndex + 1 }}
                        </h3>
                        <button wire:click="editNode({{ $editingNodeIndex }})"
                                class="text-xs text-muted hover:text-muted  transition-colors flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            {{ __('Close') }}
                        </button>
                    </div>

                    @php
                        $editNodeName = $editNode['type'] === 'action'
                            ? ($actionSubtypes[$editNode['subtype']] ?? $editNode['subtype'])
                            : ($conditionSubtypes[$editNode['subtype']] ?? $editNode['subtype']);
                    @endphp
                    <p class="text-sm font-semibold text-ink mb-3">{{ $editNodeName }}</p>

                    <div class="space-y-3">
                        {{-- send_email --}}
                        @if($editNode['subtype'] === 'send_email')
                        @php $canTemplates = $planFeatures['email_templates'] ?? false; @endphp
                        {{-- Email Template picker: plan-gated. Free plans get
                             a locked, disabled dropdown with an upgrade nudge. --}}
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-muted">
                                    {{ __('Email Template') }}
                                    @if(!$canTemplates)
                                        <span class="inline-flex items-center gap-1 ml-1 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full align-middle">
                                            {{ __('Pro') }}
                                        </span>
                                    @endif
                                </label>
                                @if($canTemplates)
                                    <a href="{{ url('/campaigns/templates') }}" target="_blank"
                                       class="text-[10px] text-brand hover:underline">{{ __('Manage templates →') }}</a>
                                @else
                                    <a href="{{ url('/settings/billing') }}" wire:navigate
                                       class="text-[10px] text-warning hover:underline">{{ __('Upgrade →') }}</a>
                                @endif
                            </div>
                            <select wire:change="applyEmailTemplate({{ $editingNodeIndex }}, $event.target.value)"
                                    @disabled(!$canTemplates)
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="">{{ __('— Custom (no template) —') }}</option>
                                @php
                                    $grouped = $emailTemplates->groupBy(fn($t) => $t->is_default ? 'default' : 'workspace');
                                @endphp
                                @if($grouped->has('workspace'))
                                    <optgroup label="{{ __('Your Templates') }}">
                                        @foreach($grouped['workspace'] as $tpl)
                                            <option value="{{ $tpl->id }}"
                                                @selected((int)($editNode['config']['email_template_id'] ?? 0) === (int)$tpl->id)>
                                                {{ $tpl->name }}@if($tpl->category) — {{ ucfirst($tpl->category) }}@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($grouped->has('default'))
                                    <optgroup label="{{ __('Built-in Templates') }}">
                                        @foreach($grouped['default'] as $tpl)
                                            <option value="{{ $tpl->id }}"
                                                @selected((int)($editNode['config']['email_template_id'] ?? 0) === (int)$tpl->id)>
                                                {{ $tpl->name }}@if($tpl->category) — {{ ucfirst($tpl->category) }}@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            @if(!empty($editNode['config']['email_template_id']))
                                <p class="text-[10px] text-muted mt-1">
                                    {{ __('Template applied. Subject and body below are editable — your changes override the template at send time.') }}
                                </p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('From Name') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.from_name" placeholder="{{ __('Support Team') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Subject') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.subject" placeholder="{{ __('Thanks for reaching out!') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">
                                {{ !empty($editNode['config']['email_template_id']) ? __('Body (HTML — overrides template)') : __('Body') }}
                            </label>
                            <textarea wire:model.live.debounce.500ms="nodes.{{ $editingNodeIndex }}.config.body" rows="6" placeholder="Email body... Use @{{contact.name}} for personalization"
                                      class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all resize-y font-mono"></textarea>
                            <p class="text-[10px] text-muted mt-1">@{{contact.name}}, @{{contact.email}}, @{{contact.company}}</p>
                        </div>

                        {{-- send_notification --}}
                        @elseif($editNode['subtype'] === 'send_notification')
                        @php $canInApp = $planFeatures['workflow_in_app_notify'] ?? false; @endphp
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Channel') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.channel"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="in_app" @disabled(!$canInApp)>
                                    {{ __('In-App (bell icon)') }}@if(!$canInApp) — {{ __('Pro') }}@endif
                                </option>
                                <option value="slack">{{ __('Slack') }}</option>
                                <option value="email">{{ __('Email') }}</option>
                            </select>
                            @if(!$canInApp && ($editNode['config']['channel'] ?? '') === 'in_app')
                                <p class="text-[10px] text-warning mt-1">
                                    {{ __('In-App notifications require a Starter plan or higher.') }}
                                    <a href="{{ url('/settings/billing') }}" wire:navigate class="underline">{{ __('Upgrade') }}</a>
                                </p>
                            @endif
                            <p class="text-[10px] text-muted mt-1">
                                @if(($editNode['config']['channel'] ?? 'in_app') === 'in_app')
                                    {{ __('Appears in the bell-icon dropdown for all workspace members.') }}
                                @elseif(($editNode['config']['channel'] ?? '') === 'slack')
                                    {{ __('Posts to your connected Slack workspace via webhook.') }}
                                @else
                                    {{ __('Sends a plain email to the workspace owner (or specified email).') }}
                                @endif
                            </p>
                        </div>

                        {{-- Title (in_app only) --}}
                        @if(($editNode['config']['channel'] ?? 'in_app') === 'in_app')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Title') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.title"
                                   placeholder="{{ __('e.g., New high-value lead') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        @endif

                        {{-- Override email recipient (email channel only) --}}
                        @if(($editNode['config']['channel'] ?? '') === 'email')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Email recipient') }} <span class="text-muted">({{ __('optional, defaults to owner') }})</span></label>
                            <input type="email" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.email"
                                   placeholder="alerts@yourcompany.com"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Message') }}</label>
                            <textarea wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.message" rows="3" placeholder="{{ __('Notification message — use {first_name}, {email}, {company} for personalization') }}"
                                      class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all resize-y"></textarea>
                        </div>

                        {{-- wait_delay --}}
                        @elseif($editNode['subtype'] === 'wait_delay')
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-muted  mb-1">{{ __('Duration') }}</label>
                                <input type="number" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.duration" min="1"
                                       class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-muted  mb-1">{{ __('Unit') }}</label>
                                <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.unit"
                                        class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                    <option value="minutes">{{ __('Minutes') }}</option>
                                    <option value="hours">{{ __('Hours') }}</option>
                                    <option value="days">{{ __('Days') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- if_else / contact_field --}}
                        @elseif(in_array($editNode['subtype'], ['if_else', 'contact_field']))
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Field') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.field" placeholder="e.g., lead_score, company"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Operator') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.operator"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="equals">{{ __('Equals') }}</option>
                                <option value="not_equals">{{ __('Not Equals') }}</option>
                                <option value="contains">{{ __('Contains') }}</option>
                                <option value="not_contains">{{ __('Not Contains') }}</option>
                                <option value="greater_than">{{ __('Greater Than') }}</option>
                                <option value="less_than">{{ __('Less Than') }}</option>
                                <option value="is_empty">{{ __('Is Empty') }}</option>
                                <option value="is_not_empty">{{ __('Is Not Empty') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Value') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.value" placeholder="{{ __('Value to compare') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- add_tag / remove_tag / has_tag --}}
                        @elseif(in_array($editNode['subtype'], ['add_tag', 'remove_tag', 'has_tag']))
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Tag Name') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.tag_name" placeholder="e.g., VIP, Hot Lead"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- add_to_group / remove_from_group / in_group --}}
                        @elseif(in_array($editNode['subtype'], ['add_to_group', 'remove_from_group', 'in_group']))
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Contact Group') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.group_id"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="">{{ __('Select group...') }}</option>
                                @foreach($contactGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                            @if($contactGroups->isEmpty())
                            <p class="text-[10px] text-amber-500 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                {{ __('No contact groups found. Create one first.') }}
                            </p>
                            @endif
                        </div>

                        {{-- update_contact --}}
                        @elseif($editNode['subtype'] === 'update_contact')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Field') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.field"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="">{{ __('Select field...') }}</option>
                                <option value="lead_score">{{ __('Engagement Score') }}</option>
                                <option value="status">{{ __('Status') }}</option>
                                <option value="lifecycle_stage">{{ __('Lifecycle Stage') }}</option>
                                <option value="company">{{ __('Company') }}</option>
                                <option value="phone">{{ __('Phone') }}</option>
                                <option value="notes">{{ __('Notes') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('New Value') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.value"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- assign_agent --}}
                        @elseif($editNode['subtype'] === 'assign_agent')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Assignment Method') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.round_robin"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="0">{{ __('Specific agent') }}</option>
                                <option value="1">{{ __('Round robin') }}</option>
                            </select>
                        </div>
                        @if(!($editNode['config']['round_robin'] ?? false))
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Agent Email') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.agent_id" placeholder="agent@company.com"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        @endif

                        {{-- create_deal --}}
                        @elseif($editNode['subtype'] === 'create_deal')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Deal Name') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.deal_name" placeholder="{{ __('New Opportunity') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Pipeline ID') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.pipeline_id" placeholder="{{ __('Pipeline ID') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Deal Value') }}</label>
                            <input type="number" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.value" placeholder="0.00" step="0.01"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- move_deal --}}
                        @elseif($editNode['subtype'] === 'move_deal')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Target Stage ID') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.stage_id" placeholder="{{ __('Stage ID') }}"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- webhook_call --}}
                        @elseif($editNode['subtype'] === 'webhook_call')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('URL') }}</label>
                            <input type="url" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.url" placeholder="https://api.example.com/webhook"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('HTTP Method') }}</label>
                            <select wire:model.live="nodes.{{ $editingNodeIndex }}.config.method"
                                    class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 bg-surface-2 transition-all">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                                <option value="PUT">PUT</option>
                                <option value="PATCH">PATCH</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>

                        {{-- ai_reply --}}
                        @elseif($editNode['subtype'] === 'ai_reply')
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('AI Instructions') }}</label>
                            <textarea wire:model.live.debounce.500ms="nodes.{{ $editingNodeIndex }}.config.instructions" rows="3" placeholder="{{ __('Reply politely using knowledge base...') }}"
                                      class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all resize-y"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Max Tokens') }}</label>
                            <input type="number" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.max_tokens" min="50" max="4000" step="50"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                        </div>

                        {{-- email_opened / email_clicked --}}
                        @elseif(in_array($editNode['subtype'], ['email_opened', 'email_clicked']))
                        <div>
                            <label class="block text-xs font-medium text-muted  mb-1">{{ __('Check within (hours)') }}</label>
                            <input type="number" wire:model.live.debounce.300ms="nodes.{{ $editingNodeIndex }}.config.within_hours" min="1" max="720"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40/20 focus:border-indigo-400 transition-all">
                            <p class="text-[10px] text-muted mt-1">Was the email {{ $editNode['subtype'] === 'email_opened' ? 'opened' : 'clicked' }} within this window?</p>
                        </div>

                        @else
                        <div class="flex items-center gap-2 px-3 py-2 bg-surface rounded-lg text-xs text-muted">
                            <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('No additional configuration needed.') }}
                        </div>
                        @endif
                    </div>

                    {{-- Done editing button --}}
                    <button wire:click="editNode({{ $editingNodeIndex }})"
                            class="mt-4 w-full px-3 py-2 text-sm font-medium text-brand bg-brand/10 rounded-lg hover:bg-brand/15 transition-colors flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Done Editing') }}
                    </button>
                </div>
                @endif

            </div>
        </aside>

        {{-- ============================================================== --}}
        {{-- CENTER: Visual Flow Canvas                                     --}}
        {{-- ============================================================== --}}
        <main class="flex-1 overflow-y-auto relative transition-all duration-300"
              :class="{ 'ring-2 ring-inset ring-indigo-300/60': canvasFlash }"
              style="background-color: #f8fafc; background-image: radial-gradient(circle, #e2e8f0 1px, transparent 1px); background-size: 20px 20px;">

            <div class="py-10 px-4">
                <div class="max-w-md mx-auto">

                    {{-- ================================================== --}}
                    {{-- TRIGGER NODE                                       --}}
                    {{-- ================================================== --}}
                    <div class="flex justify-center">
                        <div class="w-full">
                            @if($triggerType)
                            <div class="bg-surface-2 rounded-xl border-2 border-brand/20 shadow-sm overflow-hidden transition-all hover:shadow-md cursor-default">
                                <div class="bg-brand px-4 py-1.5 flex items-center gap-2">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-surface-2 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-surface-2"></span>
                                    </span>
                                    <span class="text-[10px] font-bold text-white uppercase tracking-widest">Trigger</span>
                                </div>
                                <div class="px-4 py-3 flex items-center gap-3">
                                    <div class="w-9 h-9 bg-brand/10 border border-brand/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $currentTriggerIcon !!}</svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] text-indigo-400 uppercase font-bold tracking-widest">When</p>
                                        <p class="text-sm font-semibold text-ink truncate">{{ $triggerTypes[$triggerType] ?? $triggerType }}</p>
                                    </div>
                                </div>
                            </div>
                            @else
                            {{-- No trigger selected --}}
                            <div class="bg-surface-2 rounded-xl border-2 border-dashed border-brand/20 px-4 py-5 text-center">
                                <div class="w-10 h-10 mx-auto bg-brand/10 rounded-full flex items-center justify-center mb-2">
                                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-ink/80">{{ __('Start with a trigger') }}</p>
                                <p class="text-xs text-muted mt-1">Choose what starts this workflow using the <span class="font-semibold text-muted ">Trigger</span> dropdown in the left panel</p>
                                <div class="mt-3 flex items-center justify-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-medium text-brand bg-brand/10 rounded-full border border-indigo-100">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                        {{ __('Left panel') }}
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Connector from trigger --}}
                    <div class="flex justify-center">
                        <div class="w-0.5 h-8 border-l-2 border-dashed border-border"></div>
                    </div>

                    {{-- ================================================== --}}
                    {{-- EMPTY STATE (no nodes yet)                         --}}
                    {{-- ================================================== --}}
                    @if(count($nodes) === 0)
                    <div class="flex justify-center">
                        <div class="w-full bg-surface-2 rounded-xl border-2 border-dashed border-brand/20 px-6 py-8 text-center">
                            <div class="w-14 h-14 mx-auto bg-brand/10 rounded-full flex items-center justify-center mb-3">
                                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <p class="text-sm font-semibold text-ink/80">{{ __('What should happen next?') }}</p>
                            <p class="text-xs text-muted mt-1.5 max-w-[240px] mx-auto">Click any action from the <span class="font-semibold text-muted ">right panel</span> to add your first step, like sending an email or tagging a contact.</p>
                            <div class="mt-4 flex items-center justify-center gap-3 flex-wrap">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-brand bg-brand/10 rounded-full border border-indigo-100">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    {{ __('Pick from the panel on the right') }}
                                </span>
                                <span class="text-xs text-muted">or</span>
                                <button wire:click="startGuidedMode"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-brand rounded-full hover:bg-brand-strong transition-colors shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                    {{ __('Start with Template') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Connector to end --}}
                    <div class="flex justify-center">
                        <div class="w-0.5 h-8 border-l-2 border-dashed border-border"></div>
                    </div>
                    @endif

                    {{-- ================================================== --}}
                    {{-- NODE LIST                                          --}}
                    {{-- ================================================== --}}
                    @foreach($nodes as $index => $node)
                    @php
                        $isDelay = str_contains($node['subtype'] ?? '', 'wait') || str_contains($node['subtype'] ?? '', 'delay');
                        $isCondition = $node['type'] === 'condition';
                        $isEditing = $editingNodeIndex === $index;

                        if ($isCondition) {
                            $nc = [
                                'border' => 'border-l-yellow-400',
                                'ring'   => 'ring-yellow-400',
                                'iconBg' => 'bg-warning/10',
                                'iconTx' => 'text-yellow-600',
                                'badge'  => 'bg-warning/15 text-warning',
                                'label'  => __('Condition'),
                            ];
                        } elseif ($isDelay) {
                            $nc = [
                                'border' => 'border-l-purple-400',
                                'ring'   => 'ring-purple-400',
                                'iconBg' => 'bg-brand/10',
                                'iconTx' => 'text-purple-600',
                                'badge'  => 'bg-brand/15 text-brand',
                                'label'  => __('Delay'),
                            ];
                        } else {
                            $nc = [
                                'border' => 'border-l-green-400',
                                'ring'   => 'ring-green-400',
                                'iconBg' => 'bg-success/10',
                                'iconTx' => 'text-success',
                                'badge'  => 'bg-success/15 text-success',
                                'label'  => __('Action'),
                            ];
                        }

                        $iconSvg = $subtypeIcons[$node['subtype']] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>';
                        $nodeName = $node['type'] === 'action'
                            ? ($actionSubtypes[$node['subtype']] ?? $node['subtype'])
                            : ($conditionSubtypes[$node['subtype']] ?? $node['subtype']);

                        // Build human-readable config summary
                        $configSummary = '';
                        $cfg = $node['config'] ?? [];
                        switch ($node['subtype']) {
                            case 'send_email':
                                if (!empty($cfg['subject'])) $configSummary = 'Subject: ' . Str::limit($cfg['subject'], 30);
                                break;
                            case 'send_notification':
                                $ch = !empty($cfg['channel']) ? ucfirst($cfg['channel']) : '';
                                $msg = !empty($cfg['message']) ? Str::limit($cfg['message'], 25) : '';
                                $configSummary = implode(' - ', array_filter([$ch, $msg]));
                                break;
                            case 'add_tag': case 'remove_tag': case 'has_tag':
                                if (!empty($cfg['tag_name'])) $configSummary = 'Tag: ' . $cfg['tag_name'];
                                break;
                            case 'add_to_group': case 'remove_from_group': case 'in_group':
                                if (!empty($cfg['group_id'])) {
                                    $grp = $contactGroups->firstWhere('id', $cfg['group_id']);
                                    $configSummary = 'Group: ' . ($grp ? $grp->name : '#' . $cfg['group_id']);
                                }
                                break;
                            case 'wait_delay':
                                if (!empty($cfg['duration'])) $configSummary = $cfg['duration'] . ' ' . ($cfg['unit'] ?? 'hours');
                                break;
                            case 'if_else': case 'contact_field':
                                $parts = array_filter([
                                    !empty($cfg['field']) ? $cfg['field'] : null,
                                    !empty($cfg['operator']) ? str_replace('_', ' ', $cfg['operator']) : null,
                                    !empty($cfg['value']) ? '"' . Str::limit($cfg['value'], 15) . '"' : null,
                                ]);
                                $configSummary = implode(' ', $parts);
                                break;
                            case 'update_contact':
                                if (!empty($cfg['field'])) $configSummary = ucfirst(str_replace('_', ' ', $cfg['field'])) . (!empty($cfg['value']) ? ' = ' . Str::limit($cfg['value'], 15) : '');
                                break;
                            case 'webhook_call':
                                if (!empty($cfg['url'])) $configSummary = ($cfg['method'] ?? 'POST') . ' ' . Str::limit($cfg['url'], 25);
                                break;
                            case 'ai_reply':
                                if (!empty($cfg['instructions'])) $configSummary = Str::limit($cfg['instructions'], 35);
                                break;
                            case 'email_opened': case 'email_clicked':
                                if (!empty($cfg['within_hours'])) $configSummary = 'Within ' . $cfg['within_hours'] . 'h';
                                break;
                            case 'assign_agent':
                                $configSummary = !empty($cfg['round_robin']) && $cfg['round_robin'] ? 'Round robin' : (!empty($cfg['agent_id']) ? $cfg['agent_id'] : '');
                                break;
                            default:
                                $parts = [];
                                foreach ($cfg as $cv) {
                                    if (!empty($cv) && is_string($cv)) $parts[] = Str::limit($cv, 28);
                                }
                                $configSummary = implode(' / ', array_slice($parts, 0, 2));
                        }
                    @endphp

                    {{-- Node Card --}}
                    <div class="flex justify-center group/node" wire:key="node-{{ $index }}-{{ $node['subtype'] }}" id="node-card-{{ $index }}">
                        <div class="w-full">
                            <div wire:click="editNode({{ $index }})"
                                 class="bg-surface-2 rounded-xl border border-border shadow-sm {{ $nc['border'] }} border-l-4 overflow-hidden
                                        transition-all duration-200 hover:shadow-md cursor-pointer
                                        {{ $isEditing ? 'ring-2 ' . $nc['ring'] . ' shadow-md' : '' }}"
                                 :class="{ 'animate-pulse ring-2 ring-indigo-400': justAddedIndex === {{ $index }} }">

                                <div class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        {{-- Icon --}}
                                        <div class="w-9 h-9 {{ $nc['iconBg'] }} rounded-lg flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 {{ $nc['iconTx'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">@safeSvg($iconSvg)</svg>
                                        </div>

                                        {{-- Name + summary --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded {{ $nc['badge'] }}">{{ $nc['label'] }}</span>
                                                <span class="text-xs text-muted">#{{ $index + 1 }}</span>
                                            </div>
                                            <p class="text-sm font-semibold text-ink mt-0.5 truncate">{{ $nodeName }}</p>
                                            @if($isEditing)
                                            <p class="text-xs text-indigo-500 truncate mt-0.5 flex items-center gap-1">
                                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                {{ __('Editing in left panel') }}
                                            </p>
                                            @elseif($configSummary)
                                            <p class="text-xs text-muted truncate mt-0.5">{{ $configSummary }}</p>
                                            @else
                                            <p class="text-xs text-amber-500 truncate mt-0.5 flex items-center gap-1">
                                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                {{ __('Click to configure') }}
                                            </p>
                                            @endif
                                        </div>

                                        {{-- Hover action buttons --}}
                                        <div class="flex items-center gap-0.5 opacity-0 group-hover/node:opacity-100 transition-opacity shrink-0"
                                             x-data @click.stop>
                                            @if($index > 0)
                                            <button wire:click.stop="moveNodeUp({{ $index }})"
                                                    class="p-1.5 text-muted hover:text-ink/80 hover:bg-surface  rounded-lg transition-colors" title="Move up">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                            </button>
                                            @endif
                                            @if($index < count($nodes) - 1)
                                            <button wire:click.stop="moveNodeDown({{ $index }})"
                                                    class="p-1.5 text-muted hover:text-ink/80 hover:bg-surface  rounded-lg transition-colors" title="Move down">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            @endif
                                            <button wire:click.stop="removeNode({{ $index }})" wire:confirm="Remove this step? This action cannot be undone."
                                                    class="p-1.5 text-muted hover:text-danger hover:bg-danger/10 rounded-lg transition-colors" title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Connector + "+" add button between nodes --}}
                    <div x-data="{ showMenu: false }" class="relative flex justify-center group/connector">
                        <div class="flex flex-col items-center">
                            <div class="w-0.5 h-4 border-l-2 border-dashed border-border"></div>
                            {{-- Plus button (grows on hover for better click target) --}}
                            <button @click="showMenu = !showMenu"
                                    class="relative z-10 w-7 h-7 bg-surface-2 border-2 border-border rounded-full flex items-center justify-center text-muted
                                           hover:border-indigo-400 hover:text-brand hover:bg-brand/10 hover:shadow-md hover:w-8 hover:h-8
                                           transition-all duration-200 group/add"
                                    :class="{ 'border-indigo-400 text-brand bg-brand/10 shadow-md': showMenu }"
                                    :title="__('Insert a step here')">
                                <svg class="w-3.5 h-3.5 group-hover/add:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </button>
                            <div class="w-0.5 h-4 border-l-2 border-dashed border-border"></div>
                        </div>

                        {{-- Inline add-node dropdown --}}
                        <div x-show="showMenu" @click.away="showMenu = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-1/2 -translate-x-1/2 top-full mt-1 w-56 bg-surface-2 rounded-xl shadow-xl border border-border py-2 z-30"
                             style="display: none;">
                            <p class="px-3 py-1 text-[10px] font-bold text-muted uppercase tracking-wider">Insert Step After #{{ $index + 1 }}</p>
                            @foreach(array_slice($actionSubtypes, 0, 5) as $key => $label)
                            <button @click="showMenu = false" wire:click="insertNodeAfter({{ $index }}, 'action', '{{ $key }}')"
                                    class="w-full text-left px-3 py-1.5 text-sm text-ink/80 hover:bg-surface flex items-center gap-2 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full {{ $key === 'wait_delay' ? 'bg-purple-400' : 'bg-green-400' }} flex-shrink-0"></span>
                                {{ $label }}
                            </button>
                            @endforeach
                            <hr class="my-1 border-border">
                            @foreach(array_slice($conditionSubtypes, 0, 2) as $key => $label)
                            <button @click="showMenu = false" wire:click="insertNodeAfter({{ $index }}, 'condition', '{{ $key }}')"
                                    class="w-full text-left px-3 py-1.5 text-sm text-ink/80 hover:bg-surface flex items-center gap-2 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 flex-shrink-0"></span>
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    {{-- ================================================== --}}
                    {{-- END NODE                                           --}}
                    {{-- ================================================== --}}
                    <div class="flex justify-center">
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="w-10 h-10 bg-surface  rounded-full flex items-center justify-center border-2 border-border">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                            </div>
                            <span class="text-[10px] font-medium text-muted uppercase tracking-wider">{{ __('End') }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </main>

        {{-- ============================================================== --}}
        {{-- RIGHT SIDEBAR: Add Node Panel                                  --}}
        {{-- ============================================================== --}}
        <aside x-show="rightPanelOpen"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="translate-x-full opacity-0"
               x-transition:enter-end="translate-x-0 opacity-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="translate-x-0 opacity-100"
               x-transition:leave-end="translate-x-full opacity-0"
               class="w-64 flex-shrink-0 bg-surface-2 border-l border-border overflow-y-auto hidden lg:block">

            <div class="p-4 space-y-5">
                <div>
                    <h3 class="text-xs font-bold text-muted uppercase tracking-wider mb-1">{{ __('Add Step') }}</h3>
                    <p class="text-[10px] text-muted">{{ __('Click any item below to add it to your workflow. The new step appears at the end of the flow.') }}</p>
                </div>

                {{-- How It Works Guide --}}
                <details class="bg-blue-500/5 border border-blue-500/10 rounded-xl overflow-hidden">
                    <summary class="px-3 py-2 cursor-pointer text-xs font-semibold text-blue-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('How Workflows Work') }}
                    </summary>
                    <div class="px-3 pb-3 space-y-2">
                        <ol class="text-[10px] text-muted space-y-1.5 list-decimal list-inside leading-relaxed">
                            <li><strong class="text-ink">{{ __('Choose a Trigger') }}</strong> — {{ __('Select what starts the workflow (e.g. "New Email Received")') }}</li>
                            <li><strong class="text-ink">{{ __('Add Actions') }}</strong> — {{ __('Pick what happens next: send email, add tag, assign agent, etc.') }}</li>
                            <li><strong class="text-ink">{{ __('Add Conditions') }}</strong> — {{ __('Optionally filter with If/Else rules (e.g. "if subject contains billing")') }}</li>
                            <li><strong class="text-ink">{{ __('Configure Each Step') }}</strong> — {{ __('Click any step to set its details (email template, tag name, etc.)') }}</li>
                            <li><strong class="text-ink">{{ __('Activate') }}</strong> — {{ __('Click "Activate" to make the workflow live. It runs automatically.') }}</li>
                        </ol>
                        <div class="pt-2 border-t border-blue-500/10">
                            <p class="text-[10px] font-semibold text-ink mb-1">{{ __('Available Triggers:') }}</p>
                            <ul class="text-[10px] text-muted space-y-0.5">
                                <li>{{ __('New Email Received — fires when any inbound email arrives') }}</li>
                                <li>{{ __('New Contact Created — fires when a contact is added') }}</li>
                                <li>{{ __('Deal Stage Changed — fires when a deal moves to a new stage') }}</li>
                                <li>{{ __('Tag Added — fires when a tag is attached to a contact') }}</li>
                            </ul>
                        </div>
                        <div class="pt-2 border-t border-blue-500/10">
                            <p class="text-[10px] font-semibold text-ink mb-1">{{ __('Example:') }}</p>
                            <p class="text-[10px] text-muted italic">{{ __('"When a new email arrives → if subject contains support → assign to Support Team → add tag #support → send auto-reply"') }}</p>
                        </div>
                    </div>
                </details>

                {{-- ============================== --}}
                {{-- Actions section                --}}
                {{-- ============================== --}}
                <div x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-1.5 group">
                        <span class="flex items-center gap-2">
                            <span class="w-5 h-5 bg-success/15 rounded flex items-center justify-center">
                                <svg class="w-3 h-3 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                            </span>
                            <span class="text-xs font-bold text-success uppercase tracking-wider">{{ __('Actions') }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 text-muted transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="mt-1.5 space-y-1">
                        @foreach($actionSubtypes as $key => $label)
                        @php $isDelayItem = ($key === 'wait_delay'); @endphp
                        <button wire:click="addNode('action', '{{ $key }}')"
                                x-data="{ clicked: false }"
                                @click="clicked = true; setTimeout(() => clicked = false, 800)"
                                class="w-full text-left px-3 py-2 rounded-lg border border-transparent cursor-pointer
                                       hover:bg-{{ $isDelayItem ? 'purple' : 'green' }}-50 hover:border-{{ $isDelayItem ? 'purple' : 'green' }}-200
                                       active:scale-[0.97] transition-all duration-150 group/item"
                                :class="{ '!bg-{{ $isDelayItem ? 'purple' : 'green' }}-100 !border-{{ $isDelayItem ? 'purple' : 'green' }}-300': clicked }">
                            <div class="flex items-start gap-2.5">
                                <div class="w-7 h-7 {{ $isDelayItem ? 'bg-brand/10' : 'bg-success/10' }} rounded-md flex items-center justify-center flex-shrink-0 mt-0.5
                                            group-hover/item:{{ $isDelayItem ? 'bg-brand/15' : 'bg-success/15' }} transition-colors">
                                    <svg class="w-3.5 h-3.5 {{ $isDelayItem ? 'text-purple-500' : 'text-green-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $subtypeIcons[$key] ?? '' !!}</svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold text-ink/80">{{ $label }}</p>
                                        <span x-show="clicked" x-transition.opacity.duration.300ms
                                              class="text-[10px] font-bold {{ $isDelayItem ? 'text-purple-600' : 'text-success' }}">{{ __('Added!') }}</span>
                                    </div>
                                    <p class="text-[10px] text-muted leading-tight mt-0.5">{{ $actionDescriptions[$key] ?? '' }}</p>
                                </div>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- ============================== --}}
                {{-- Conditions section             --}}
                {{-- ============================== --}}
                <div x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-1.5 group">
                        <span class="flex items-center gap-2">
                            <span class="w-5 h-5 bg-warning/15 rounded flex items-center justify-center">
                                <svg class="w-3 h-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <span class="text-xs font-bold text-warning uppercase tracking-wider">{{ __('Conditions') }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 text-muted transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="mt-1.5 space-y-1">
                        @foreach($conditionSubtypes as $key => $label)
                        <button wire:click="addNode('condition', '{{ $key }}')"
                                x-data="{ clicked: false }"
                                @click="clicked = true; setTimeout(() => clicked = false, 800)"
                                class="w-full text-left px-3 py-2 rounded-lg border border-transparent cursor-pointer
                                       hover:bg-warning/10 hover:border-warning/20
                                       active:scale-[0.97] transition-all duration-150 group/item"
                                :class="{ '!bg-warning/15 !border-yellow-300': clicked }">
                            <div class="flex items-start gap-2.5">
                                <div class="w-7 h-7 bg-warning/10 rounded-md flex items-center justify-center flex-shrink-0 mt-0.5
                                            group-hover/item:bg-warning/15 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $subtypeIcons[$key] ?? '' !!}</svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold text-ink/80">{{ $label }}</p>
                                        <span x-show="clicked" x-transition.opacity.duration.300ms
                                              class="text-[10px] font-bold text-yellow-600">{{ __('Added!') }}</span>
                                    </div>
                                    <p class="text-[10px] text-muted leading-tight mt-0.5">{{ $conditionDescriptions[$key] ?? '' }}</p>
                                </div>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Tips --}}
                <div class="p-3 bg-surface rounded-lg border border-border">
                    <p class="text-[10px] font-bold text-muted uppercase tracking-wider mb-2">{{ __('Tips') }}</p>
                    <ul class="space-y-1.5 text-[10px] text-muted">
                        <li class="flex items-start gap-1.5">
                            <svg class="w-3 h-3 text-muted/50 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            {{ __('Click any node on the canvas to edit it') }}
                        </li>
                        <li class="flex items-start gap-1.5">
                            <svg class="w-3 h-3 text-muted/50 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                            {{ __('Hover a node to reorder or delete it') }}
                        </li>
                        <li class="flex items-start gap-1.5">
                            <svg class="w-3 h-3 text-muted/50 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Use + buttons to insert between steps') }}
                        </li>
                        <li class="flex items-start gap-1.5">
                            <svg class="w-3 h-3 text-muted/50 flex-shrink-0 mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
                            Ctrl+Z to undo, Ctrl+Y to redo
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

    </div>{{-- end .flex.flex-1 --}}

    {{-- Undo / Redo toast notification --}}
    <div x-show="toastVisible"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50"
         style="display: none;">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-surface text-white text-sm font-medium rounded-xl shadow-xl">
            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span x-text="toastMessage"></span>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- GUIDED MODE OVERLAY                                                --}}
    {{-- ================================================================== --}}
    @if($showGuidedMode)
    <div class="fixed inset-0 z-50 flex items-center justify-center" x-data x-trap.noscroll="true">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="exitGuidedMode"></div>

        {{-- Modal panel --}}
        <div class="relative w-full max-w-2xl max-h-[85vh] bg-surface-2 rounded-2xl shadow-2xl border border-border overflow-hidden flex flex-col mx-4"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-brand/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-ink">
                            {{ $guidedStep === 1 ? __('Choose a Template') : __('Customise Template') }}
                        </h2>
                        <p class="text-xs text-muted mt-0.5">
                            {{ $guidedStep === 1 ? __('Pick a pre-built workflow to get started quickly') : __('Adjust the details before creating your workflow') }}
                        </p>
                    </div>
                </div>
                <button wire:click="exitGuidedMode"
                        class="p-2 text-muted hover:text-ink hover:bg-surface rounded-lg transition-colors"
                        aria-label="Close guided mode">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Step indicator --}}
            <div class="px-6 py-3 border-b border-border bg-surface shrink-0">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
                            {{ $guidedStep >= 1 ? 'bg-brand text-white' : 'bg-surface-2 text-muted border border-border' }}">1</span>
                        <span class="text-xs font-medium {{ $guidedStep >= 1 ? 'text-ink' : 'text-muted' }}">{{ __('Select') }}</span>
                    </div>
                    <div class="flex-1 h-px {{ $guidedStep >= 2 ? 'bg-brand' : 'bg-border' }}"></div>
                    <div class="flex items-center gap-2">
                        <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
                            {{ $guidedStep >= 2 ? 'bg-brand text-white' : 'bg-surface-2 text-muted border border-border' }}">2</span>
                        <span class="text-xs font-medium {{ $guidedStep >= 2 ? 'text-ink' : 'text-muted' }}">{{ __('Customise') }}</span>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">

                {{-- ============================== --}}
                {{-- STEP 1: Template Grid           --}}
                {{-- ============================== --}}
                @if($guidedStep === 1)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($templates as $tpl)
                    @php
                        $isSelected = $selectedTemplate === $tpl['id'];
                        $categoryColors = [
                            'email' => 'bg-info/15 text-info',
                            'crm' => 'bg-success/15 text-success',
                            'contacts' => 'bg-brand/15 text-brand',
                        ];
                        $catClass = $categoryColors[$tpl['category']] ?? 'bg-surface text-muted';
                    @endphp
                    <button wire:click="selectTemplate('{{ $tpl['id'] }}')"
                            class="text-left p-4 rounded-xl border-2 transition-all duration-150 group
                                {{ $isSelected
                                    ? 'border-brand bg-brand/5 ring-1 ring-brand shadow-sm'
                                    : 'border-border hover:border-brand/30 hover:shadow-sm bg-surface-2' }}">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg {{ $isSelected ? 'bg-brand/15' : 'bg-brand/10' }} flex items-center justify-center shrink-0 transition-colors">
                                <x-icon :name="$tpl['icon']" class="w-5 h-5 text-brand" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-ink truncate group-hover:text-brand transition-colors">{{ $tpl['name'] }}</h3>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $catClass }}">
                                        {{ ucfirst($tpl['category']) }}
                                    </span>
                                    <span class="text-[10px] text-muted">{{ count($tpl['nodes']) }} steps</span>
                                </div>
                                <p class="text-xs text-muted mt-1.5 line-clamp-2 leading-relaxed">{{ $tpl['description'] }}</p>
                            </div>
                            @if($isSelected)
                            <div class="shrink-0 mt-1">
                                <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            @endif
                        </div>
                    </button>
                    @endforeach
                </div>

                {{-- ============================== --}}
                {{-- STEP 2: Customise               --}}
                {{-- ============================== --}}
                @elseif($guidedStep === 2 && $selectedTemplate)
                @php $tpl = collect($templates)->firstWhere('id', $selectedTemplate); @endphp
                @if($tpl)
                <div class="space-y-5">
                    {{-- Template summary --}}
                    <div class="flex items-start gap-3 p-4 bg-brand/5 rounded-xl border border-brand/20">
                        <div class="w-10 h-10 rounded-lg bg-brand/15 flex items-center justify-center shrink-0">
                            <x-icon :name="$tpl['icon']" class="w-5 h-5 text-brand" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ $tpl['name'] }}</h3>
                            <p class="text-xs text-muted mt-0.5">{{ $tpl['description'] }}</p>
                        </div>
                    </div>

                    {{-- Flow preview --}}
                    <div>
                        <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-3">{{ __('Workflow Steps') }}</label>
                        <div class="space-y-0">
                            @foreach($tpl['nodes'] as $nodeIndex => $tplNode)
                            @php
                                $nodeTypeColors = match($tplNode['type']) {
                                    'trigger' => ['bg' => 'bg-brand/10', 'text' => 'text-brand', 'border' => 'border-brand/20', 'badge' => 'bg-brand/15 text-brand'],
                                    'condition' => ['bg' => 'bg-warning/10', 'text' => 'text-warning', 'border' => 'border-warning/20', 'badge' => 'bg-warning/15 text-warning'],
                                    default => ['bg' => 'bg-success/10', 'text' => 'text-success', 'border' => 'border-success/20', 'badge' => 'bg-success/15 text-success'],
                                };
                            @endphp
                            <div class="flex items-center gap-3">
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-lg {{ $nodeTypeColors['bg'] }} border {{ $nodeTypeColors['border'] }} flex items-center justify-center">
                                        <span class="text-xs font-bold {{ $nodeTypeColors['text'] }}">{{ $nodeIndex + 1 }}</span>
                                    </div>
                                    @if($nodeIndex < count($tpl['nodes']) - 1)
                                    <div class="w-0.5 h-4 border-l-2 border-dashed border-border"></div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 {{ $nodeIndex < count($tpl['nodes']) - 1 ? 'pb-4' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $nodeTypeColors['badge'] }}">
                                            {{ ucfirst($tplNode['type']) }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium text-ink mt-0.5">{{ $tplNode['config']['label'] ?? ucfirst(str_replace('_', ' ', $tplNode['subtype'])) }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Editable name and description --}}
                    <div class="space-y-3 pt-2 border-t border-border">
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Workflow Name') }}</label>
                            <input type="text" wire:model="name"
                                   class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface hover:bg-surface transition-all"
                                   placeholder="e.g., Welcome New Contacts">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">{{ __('Description') }}</label>
                            <textarea wire:model="description" rows="2"
                                      class="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface hover:bg-surface transition-all resize-y"
                                      placeholder="{{ __('What does this workflow do?') }}"></textarea>
                        </div>
                    </div>
                </div>
                @endif
                @endif
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-border bg-surface shrink-0 flex items-center justify-between">
                <button wire:click="exitGuidedMode"
                        class="px-4 py-2 text-sm font-medium text-muted hover:text-ink transition-colors">
                    {{ __('Use blank canvas instead') }}
                </button>

                <div class="flex items-center gap-2">
                    @if($guidedStep === 2)
                    <button wire:click="$set('guidedStep', 1)"
                            class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-lg hover:bg-surface transition-colors">
                        {{ __('Back') }}
                    </button>
                    <button wire:click="applyTemplate" wire:loading.attr="disabled"
                            class="px-5 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand-strong transition-all disabled:opacity-50 shadow-sm flex items-center gap-2">
                        <span wire:loading.remove wire:target="applyTemplate" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Create Workflow') }}
                        </span>
                        <span wire:loading wire:target="applyTemplate" class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            {{ __('Creating...') }}
                        </span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

</div>{{-- end root --}}

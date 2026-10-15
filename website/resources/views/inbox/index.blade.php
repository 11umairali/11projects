<x-layouts.inbox :title="__('Inbox')">
    <div x-data="{ mobileView: 'list', composing: false }"
         @conversation-selected.window="mobileView = 'detail'; composing = false"
         @start-compose.window="composing = true; mobileView = 'detail'"
         class="flex h-full w-full overflow-hidden">

        {{-- Pane 1: Sidebar --}}
        <div :class="mobileView === 'sidebar' ? 'flex' : 'hidden lg:flex'"
             class="shrink-0">
            <livewire:inbox.inbox-sidebar />
        </div>

        {{-- Pane 2: Conversation list --}}
        <div :class="mobileView === 'list' ? 'flex' : 'hidden lg:flex'"
             class="shrink-0">
            <livewire:inbox.conversation-list />
        </div>

        {{-- Pane 3: Detail OR Compose --}}
        <div :class="mobileView === 'detail' ? 'flex flex-col' : 'hidden lg:flex lg:flex-col'"
             class="flex-1 min-w-0 min-h-0 h-full overflow-hidden">

            {{-- Compose view --}}
            <template x-if="composing">
                <div class="flex-1 flex flex-col min-h-0 h-full overflow-y-auto hide-scroll bg-[#07090e]">
                    {{-- Compose header --}}
                    <div class="h-12 flex items-center px-6 justify-between shrink-0 border-b border-[#2d3039]">
                        <div class="flex items-center gap-3">
                            <button @click="composing = false" class="p-1 text-gray-400 hover:text-gray-200 rounded-md transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            </button>
                            <h2 class="text-[15px] font-semibold text-gray-200">{{ __('New Message') }}</h2>
                        </div>
                    </div>
                    {{-- Compose body --}}
                    <div class="flex-1 overflow-y-auto hide-scroll p-2">
                        <livewire:inbox.compose-email />
                    </div>
                </div>
            </template>

            {{-- Conversation detail view --}}
            <div x-show="!composing" class="flex-1 flex flex-col min-h-0 h-full overflow-hidden">
                <livewire:inbox.conversation-detail />
            </div>
        </div>

        {{-- Mobile bottom tabs --}}
        <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#15171e] border-t border-[#2d3039] flex">
            <button @click="mobileView = 'sidebar'"
                    :class="mobileView === 'sidebar' ? 'text-[#3b82f6]' : 'text-gray-500'"
                    class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors">
                <x-icon name="settings" class="w-4 h-4" />
                {{ __('Channels') }}
            </button>
            <button @click="mobileView = 'list'"
                    :class="mobileView === 'list' ? 'text-[#3b82f6]' : 'text-gray-500'"
                    class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors">
                <x-icon name="inbox" class="w-4 h-4" />
                {{ __('Inbox') }}
            </button>
            <button @click="mobileView = 'detail'"
                    :class="mobileView === 'detail' ? 'text-[#3b82f6]' : 'text-gray-500'"
                    class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors">
                <x-icon name="message-square" class="w-4 h-4" />
                {{ __('Message') }}
            </button>
        </div>
    </div>
</x-layouts.inbox>

<x-layouts.inbox :title="__('Inbox')">
<div x-data="inboxApp()" x-init="init()" x-cloak class="flex h-full w-full overflow-hidden">

    {{-- Pane 1: Sidebar --}}
    <div class="w-[200px] xl:w-[220px] flex-shrink-0 border-r border-[#2d3039] flex flex-col bg-[#0c0d12] hidden lg:flex">
        <div class="px-4 py-3 border-b border-[#2d3039]/60">
            {{-- Account picker — like the workspace switcher, lets the user
                 scope the inbox to a single connected mailbox. Hides itself
                 entirely when only one account is connected (no point
                 showing a single-item dropdown). --}}
            <div class="relative mb-3">
                <button @click="accountDrop=!accountDrop" @click.away="accountDrop=false"
                        class="w-full flex items-center gap-2 px-1 py-1 -mx-1 rounded-lg hover:bg-white/5 transition text-left">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-pink-500 to-violet-600 flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                         x-text="activeAccount ? (activeAccount.email||'?').charAt(0).toUpperCase() : 'M'"></div>
                    <span class="text-[13px] font-semibold text-gray-200 truncate flex-1"
                          x-text="activeAccount ? (activeAccount.display_name || activeAccount.email) : 'All Accounts'"></span>
                    <svg x-show="emailAccounts.length>1" class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" :class="accountDrop?'rotate-180 transition-transform':'transition-transform'">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="accountDrop && emailAccounts.length>1" x-cloak x-transition.opacity.duration.150ms
                     class="absolute top-full left-0 right-0 mt-1.5 bg-[#1a1d27]/98 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 z-30 max-h-72 overflow-y-auto">
                    {{-- All accounts (clear filter) --}}
                    <button @click="setFilterAccount(null)"
                            :class="!filterAccountId ? 'bg-white/5' : ''"
                            class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-200 hover:bg-white/5 transition flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-pink-500 to-violet-600 flex items-center justify-center text-[9px] font-bold text-white shrink-0">M</div>
                        <span class="flex-1 truncate">All Accounts</span>
                        <svg x-show="!filterAccountId" class="w-3.5 h-3.5 text-[#3b82f6]" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                    <div class="h-px bg-white/5 my-1"></div>
                    {{-- Connected accounts --}}
                    <template x-for="acc in emailAccounts" :key="acc.id">
                        <button @click="setFilterAccount(acc.id)"
                                :class="filterAccountId===acc.id ? 'bg-white/5' : ''"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-200 hover:bg-white/5 transition flex items-center gap-2.5">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white shrink-0"
                                 :class="acc.provider==='gmail' ? 'bg-red-500/80' : (acc.provider==='outlook' ? 'bg-blue-500/80' : 'bg-gradient-to-br from-purple-500 to-pink-500')"
                                 x-text="(acc.email||'?').charAt(0).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-gray-100 truncate" x-text="acc.display_name || acc.email"></div>
                                <div class="text-[10px] text-gray-500 truncate flex items-center gap-1.5">
                                    <span x-text="acc.email"></span>
                                    <template x-if="acc.status === 'connected'">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                    </template>
                                    <template x-if="acc.status !== 'connected'">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" :title="acc.status"></span>
                                    </template>
                                </div>
                            </div>
                            <svg x-show="filterAccountId===acc.id" class="w-3.5 h-3.5 text-[#3b82f6] shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </template>
                    {{-- Manage link --}}
                    <div class="h-px bg-white/5 my-1"></div>
                    <a href="{{ url('/settings/email-accounts') }}"
                       class="w-full flex items-center gap-2 px-3 py-2 text-[12px] text-[#3b82f6] hover:bg-white/5 transition">
                        <x-icon name="plus" class="w-3.5 h-3.5" />
                        Manage email accounts
                    </a>
                </div>
            </div>
            <button @click="openCompose()" class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#3b82f6] text-white text-[13px] font-semibold rounded-lg hover:bg-[#2563eb] transition">
                <x-icon name="plus" class="w-4 h-4" /> Compose
            </button>
        </div>
        <div class="px-2 py-2 space-y-0.5 text-[13px]">
            {{-- Folder buttons only light up when no channel filter is active.
                 "Inbox + WhatsApp" can't both be active at once — the
                 channel filter takes priority, so the folder pill dims
                 until the user clears the channel back to `all`. --}}
            <button @click="setFolder('inbox')" :class="(folder==='inbox' && channel==='all')?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="inbox" class="w-4 h-4" /> Inbox <span x-show="sidebarCounts.folders?.inbox" x-text="sidebarCounts.folders?.inbox" class="ml-auto text-[11px] bg-white/10 px-1.5 py-0.5 rounded-full"></span>
            </button>
            <button @click="setFolder('sent')" :class="(folder==='sent' && channel==='all')?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="send" class="w-4 h-4" /> Sent <span x-show="sidebarCounts.folders?.sent" x-text="sidebarCounts.folders?.sent" class="ml-auto text-[11px] bg-white/10 px-1.5 py-0.5 rounded-full"></span>
            </button>
            <button @click="setFolder('starred')" :class="(folder==='starred' && channel==='all')?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="star" class="w-4 h-4" /> Starred <span x-show="sidebarCounts.folders?.starred" x-text="sidebarCounts.folders?.starred" class="ml-auto text-[11px] bg-white/10 px-1.5 py-0.5 rounded-full"></span>
            </button>
            <button @click="setFolder('snoozed')" :class="(folder==='snoozed' && channel==='all')?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="clock" class="w-4 h-4" /> Snoozed <span x-show="sidebarCounts.folders?.snoozed" x-text="sidebarCounts.folders?.snoozed" class="ml-auto text-[11px] bg-white/10 px-1.5 py-0.5 rounded-full"></span>
            </button>
        </div>
        <div class="h-px bg-[#2d3039]/60 mx-3"></div>
        {{-- Agent-reachable channels (inbound + outbound) --}}
        <div class="px-2 py-2 space-y-0.5 text-[13px]">
            @foreach([['email','Email','mail'],['whatsapp','WhatsApp','message-circle'],['sms','SMS','phone'],['telegram','Telegram','send'],['slack','Slack','hash']] as [$ck,$cl,$ci])
            <button @click="setChannel('{{$ck}}')" :class="channel==='{{$ck}}'?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="{{$ci}}" class="w-4 h-4" /> {{$cl}} <span x-show="sidebarCounts.channels?.{{$ck}}" x-text="sidebarCounts.channels?.{{$ck}}" class="ml-auto text-[11px] text-gray-600"></span>
            </button>
            @endforeach
        </div>
        {{-- Live Chat is visitor-initiated (widget on the website), not a
             typical messaging channel like the ones above — keep it in its
             own group with a divider so agents can tell the two kinds apart. --}}
        <div class="h-px bg-[#2d3039]/60 mx-3"></div>
        <div class="px-2 py-2 space-y-0.5 text-[13px]">
            <button @click="setChannel('chat')" :class="channel==='chat'?'bg-[#3b82f6]/10 text-[#3b82f6]':'text-gray-400 hover:bg-white/5 hover:text-gray-200'" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg font-medium transition">
                <x-icon name="message-square" class="w-4 h-4" /> Live Chat <span x-show="sidebarCounts.channels?.chat" x-text="sidebarCounts.channels?.chat" class="ml-auto text-[11px] text-gray-600"></span>
            </button>
        </div>
        <div class="mt-auto px-2 py-2 space-y-0.5 text-[13px] border-t border-[#2d3039]/60">
            <button @click="setFolder('archive')" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-gray-200 font-medium transition"><x-icon name="archive" class="w-4 h-4" /> Archive</button>
            <button @click="setFolder('trash')" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-gray-200 font-medium transition"><x-icon name="trash" class="w-4 h-4" /> Bin</button>
            <button @click="openQuickReplies()" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-gray-200 font-medium transition text-left"><x-icon name="zap" class="w-4 h-4" /> Quick Replies</button>
            <a href="{{ route('settings.email') }}" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-gray-200 font-medium transition"><x-icon name="settings" class="w-4 h-4" /> Settings</a>
            <a href="{{ route('dashboard') }}" class="w-full flex items-center gap-3 px-3 py-1.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-gray-200 font-medium transition"><x-icon name="log-out" class="w-4 h-4" /> Exit Inbox</a>
        </div>
    </div>

    {{-- Pane 2: Conversation List --}}
    <div class="w-[370px] flex flex-col border-r border-[#2d3039] bg-[#0c0d12]/80 shrink-0">
        {{-- Top bar --}}
        <div class="h-12 flex items-center px-4 justify-between border-b border-[#2d3039]/60 shrink-0 pt-3 relative"
             x-data="{ sortDrop:false, tagDrop:false, teamDrop:false }">
            <div class="flex items-center gap-0.5 text-gray-400">
                {{-- Sort dropdown --}}
                <div class="relative z-50">
                    <button @click="sortDrop=!sortDrop;tagDrop=false;teamDrop=false" @click.away="sortDrop=false" class="py-1 px-1.5 rounded-lg flex items-center gap-1.5 hover:bg-white/5 hover:text-gray-200 transition text-[13px] font-semibold tracking-wide ml-0.5 whitespace-nowrap shrink-0">
                        <x-icon name="inbox" class="w-[15px] h-[15px] stroke-[2.5] text-blue-400" />
                        <span x-text="folder.charAt(0).toUpperCase()+folder.slice(1)"></span>
                        <x-icon name="chevron-down" class="w-3 h-3 text-gray-500" />
                    </button>
                    <div x-show="sortDrop" x-transition.opacity.duration.200ms style="display:none" class="absolute top-full left-0 mt-1.5 w-32 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans">
                        @foreach([['newest','Newest','M19 14l-7 7m0 0l-7-7m7 7V3'],['oldest','Oldest','M5 10l7-7m0 0l7 7m-7-7v18'],['priority','Priority',''],['unread','Unread','']] as [$sv,$sl,$sp])
                        <button @click="sortDrop=false;setSortBy('{{$sv}}')" :class="sortBy==='{{$sv}}'?'bg-white/5':''" class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">{{$sl}}</button>
                        @endforeach
                    </div>
                </div>
                {{-- Tag filter --}}
                <div class="relative z-40">
                    <button @click="tagDrop=!tagDrop;sortDrop=false;teamDrop=false" @click.away="tagDrop=false" :class="filterTag?'text-[#3b82f6]':'text-gray-500'" class="py-1 px-1.5 rounded-lg flex items-center gap-1 hover:bg-white/5 hover:text-gray-200 transition text-[12px] font-medium whitespace-nowrap shrink-0">
                        <x-icon name="tag" class="w-3.5 h-3.5" /> <span x-text="filterTag||'Tags'"></span> <x-icon name="chevron-down" class="w-2.5 h-2.5 text-gray-600" />
                    </button>
                    <div x-show="tagDrop" x-transition.opacity.duration.200ms style="display:none" class="absolute top-full left-0 mt-1.5 w-44 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans max-h-64 overflow-y-auto">
                        <template x-if="filterTag">
                            <button @click="tagDrop=false;setFilterTag('')" class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-[#3b82f6] hover:bg-white/5 transition flex items-center gap-2"><x-icon name="x" class="w-3 h-3" /> Clear filter</button>
                        </template>
                        <template x-for="t in tags" :key="t.id">
                            <button @click="tagDrop=false;setFilterTag(t.name)" :class="filterTag===t.name?'bg-white/5':''" class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color:'+(t.color||'#6B7280')"></span>
                                <span x-text="t.name"></span>
                                <span x-show="t.conversations_count>0" x-text="t.conversations_count" class="ml-auto text-[10px] text-gray-500"></span>
                            </button>
                        </template>
                    </div>
                </div>
                {{-- Team filter --}}
                <div class="relative z-30">
                    <button @click="teamDrop=!teamDrop;sortDrop=false;tagDrop=false" @click.away="teamDrop=false" :class="filterAssignee?'text-[#3b82f6]':'text-gray-500'" class="py-1 px-1.5 rounded-lg flex items-center gap-1 hover:bg-white/5 hover:text-gray-200 transition text-[12px] font-medium whitespace-nowrap shrink-0">
                        <x-icon name="users" class="w-3.5 h-3.5" /> Team <x-icon name="chevron-down" class="w-2.5 h-2.5 text-gray-600" />
                    </button>
                    <div x-show="teamDrop" x-transition.opacity.duration.200ms style="display:none" class="absolute top-full left-0 mt-1.5 w-48 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 font-sans max-h-64 overflow-y-auto">
                        <template x-if="filterAssignee">
                            <button @click="teamDrop=false;setFilterAssignee(null)" class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-[#3b82f6] hover:bg-white/5 transition flex items-center gap-2"><x-icon name="x" class="w-3 h-3" /> Clear filter</button>
                        </template>
                        <template x-for="m in teamMembers" :key="m.id">
                            <button @click="teamDrop=false;setFilterAssignee(m.id)" :class="filterAssignee===m.id?'bg-white/5':''" class="w-full text-left px-3 py-1.5 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-gray-700 flex items-center justify-center text-[8px] font-bold text-gray-300 shrink-0" x-text="m.initials"></span>
                                <span x-text="m.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-blue-400/80">
                <button @click="refreshConversations()" class="p-1 rounded hover:text-blue-300 transition" title="Refresh">
                    <x-icon name="refresh-cw" class="w-4 h-4" x-bind:class="loading&&'animate-spin'" />
                </button>
                <button @click="searchOpen=!searchOpen" class="p-1 rounded hover:text-blue-300 transition"><x-icon name="search" class="w-[18px] h-[18px] stroke-[2.5]" /></button>
                {{-- Pencil/Compose icon removed — already covered by
                     the big "Compose" button in the left sidebar.
                     The hamburger "Select all" icon is now a proper
                     checkbox that visibly ticks/unticks. Clicking it
                     toggles selection on every conversation in the
                     visible list (same behaviour as before). --}}
                <label class="ml-1 inline-flex items-center justify-center cursor-pointer p-1 rounded hover:bg-white/10 transition" title="Select all">
                    <input type="checkbox"
                           x-model="selectAll"
                           @click="toggleSelectAll()"
                           class="h-4 w-4 rounded border-[#2d3039] bg-[#1a1d27] text-[#3b82f6] focus:ring-[#3b82f6]/40 focus:ring-offset-0">
                </label>
            </div>
            {{-- Search overlay --}}
            <div x-show="searchOpen" x-transition x-cloak class="absolute inset-x-0 top-0 h-12 bg-[#0c0d12] z-[100] flex items-center px-4 gap-2 border-b border-[#2d3039]/60" style="display:none">
                <x-icon name="search" class="w-4 h-4 text-gray-500 shrink-0" />
                <input type="text" x-model.debounce.300ms="search" @input.debounce.300ms="page=1;loadConversations()" placeholder="Search conversations..." class="flex-1 bg-transparent text-[13px] text-gray-300 placeholder-gray-600 focus:outline-none" @keydown.escape="searchOpen=false">
                <button @click="searchOpen=false;search='';page=1;loadConversations()" class="p-1 text-gray-500 hover:text-gray-300 transition"><x-icon name="x" class="w-4 h-4" /></button>
            </div>
        </div>

        {{-- Bulk actions bar --}}
        <div x-show="selectedIds.length>0" class="px-4 py-2 bg-[#3b82f6]/5 border-b border-[#2d3039] flex items-center gap-2 flex-wrap">
            <span class="text-[12px] font-medium text-gray-200" x-text="selectedIds.length+' selected'"></span>
            <span class="text-gray-600 text-[12px]">|</span>
            <button @click="toggleSelectAll()" class="text-[12px] text-[#3b82f6] font-medium hover:text-blue-300 transition-colors" x-text="selectAll?'Deselect all':'Select all'"></button>
            <div class="flex items-center gap-1 ml-auto">
                <button @click="doBulkAction('mark_read')" title="Mark as read" class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors"><x-icon name="check-check" class="w-3.5 h-3.5" /></button>
                <button @click="doBulkAction('star')" title="Star" class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors"><x-icon name="star" class="w-3.5 h-3.5" /></button>
                <button @click="doBulkAction('archive')" title="Archive" class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors"><x-icon name="archive" class="w-3.5 h-3.5" /></button>
                {{-- Bulk delete.
                     • Outside Trash → soft-delete (move to Bin).
                     • Inside Trash → force-delete (permanent purge), since
                       calling 'delete' on already-trashed rows would just
                       re-set deleted_at and the user wouldn't see them
                       disappear. --}}
                <button @click="
                    const inTrash = (folder === 'trash' || folder === 'bin');
                    const prompt = inTrash
                        ? 'Permanently delete the selected conversations? This cannot be undone.'
                        : 'Move selected conversations to Bin?';
                    if (confirm(prompt)) doBulkAction(inTrash ? 'force_delete' : 'delete');
                " title="Delete" class="p-1.5 rounded text-gray-500 hover:bg-white/5 hover:text-red-400 transition-colors"><x-icon name="trash" class="w-3.5 h-3.5" /></button>
            </div>
        </div>

        {{-- Conversation cards --}}
        <div class="flex-1 overflow-y-auto p-1 space-y-0.5" @scroll.debounce.200ms="if($el.scrollTop+$el.clientHeight>=$el.scrollHeight-100)loadMore()">
            <template x-if="conversations.length===0&&!loading">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white/[0.03] border border-white/[0.05] flex items-center justify-center mb-4"><x-icon name="inbox" class="w-7 h-7 text-gray-600" /></div>
                    <h3 class="text-sm font-semibold text-gray-200 mb-1">No conversations yet</h3>
                    <p class="text-[13px] text-gray-500">Conversations will appear here when you receive messages.</p>
                </div>
            </template>

            <template x-for="conv in conversations" :key="conv.id">
                {{-- No border, no dot. Priority is shown inline as a
                     coloured text badge ("Urgent", "High", "Low") next
                     to the message count in the meta row below — only
                     for non-normal priorities. --}}
                <div @click="selectConversation(conv.id)"
                     class="relative px-3 py-2 rounded-lg transition cursor-pointer bg-white/[0.02]"
                     :class="
                        activeId===conv.id
                          ? '!bg-[#3b82f6] text-white shadow-lg'
                          : 'hover:bg-white/[0.04]'
                     ">
                    {{-- Top-right cluster: star, priority badge (non-normal
                         only), and channel icon — all live in the same
                         corner so the meta row below stays clean. --}}
                    <div class="absolute top-2 right-2 z-20 flex items-center gap-1.5">
                        <span class="text-[10px]" :class="activeId===conv.id?'text-white/90':'text-gray-500'" x-text="conv.time"></span>
                        <template x-if="conv.is_starred">
                            <svg class="w-3.5 h-3.5 text-yellow-400" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5l2.31 4.68 5.16.75-3.73 3.64.88 5.13L11.48 15.27l-4.62 2.43.88-5.13L4.01 8.93l5.16-.75 2.31-4.68z"/></svg>
                        </template>
                        <template x-if="conv.priority && conv.priority !== 'normal'">
                            <span class="text-[9px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded leading-none"
                                  :class="
                                      conv.priority==='urgent' ? 'bg-red-500/15 text-red-400'     :
                                      conv.priority==='high'   ? 'bg-yellow-500/15 text-yellow-400' :
                                      conv.priority==='low'    ? 'bg-gray-500/15 text-gray-400'   : ''
                                  "
                                  x-text="conv.priority.charAt(0).toUpperCase()+conv.priority.slice(1)"></span>
                        </template>
                        <span class="w-5 h-5 rounded flex items-center justify-center"
                              :class="activeId===conv.id?'bg-white/20 text-white':'bg-white/5 text-gray-400'"
                              :title="channelLabel(conv.channel)"
                              x-html="channelIcon(conv.channel)"></span>
                    </div>
                    <div class="flex gap-3 pr-6">
                        <div class="relative flex-shrink-0">
                            <button @click.stop="toggleSelect(conv.id)" :class="selectedIds.includes(conv.id)?'bg-[#3b82f6] border-blue-500 text-white opacity-100':'border-[#2d3039] bg-[#1a1d27] opacity-0 hover:opacity-100'" class="absolute -left-0.5 -top-0.5 w-5 h-5 rounded border flex items-center justify-center transition-all z-10">
                                <template x-if="selectedIds.includes(conv.id)"><x-icon name="check" class="w-3 h-3" /></template>
                            </button>
                            <div class="w-9 h-9 rounded-full border border-white/10 shrink-0 flex items-center justify-center text-lg font-bold"
                                 :class="avatarColor(conv.id)"
                                 x-text="conv.contact_initials"></div>
                        </div>
                        <div class="overflow-hidden flex-1 min-w-0">
                            {{-- Header / contact name. Bold + bright white
                                 for unread; normal weight + muted for
                                 read so the row visually fades to match
                                 the inbox background once the user has
                                 opened it. --}}
                            <h4 class="text-[13px] truncate pr-16"
                                :class="
                                    activeId===conv.id
                                      ? 'font-bold text-white'
                                      : (conv.is_unread ? 'font-bold text-white' : 'font-normal text-gray-500')
                                "
                                x-text="conv.contact_name"></h4>
                            <p x-show="conv.subject&&conv.subject!=='(no subject)'"
                               class="text-[11px] mt-0.5 leading-snug truncate"
                               :class="
                                    activeId===conv.id
                                      ? 'text-white/90 font-medium'
                                      : (conv.is_unread ? 'text-gray-200 font-medium' : 'text-gray-500/80 font-normal')
                               "
                               x-text="conv.subject"></p>
                            <p class="text-xs mt-0.5 leading-snug truncate"
                               :class="
                                    activeId===conv.id
                                      ? 'text-white/80'
                                      : (conv.is_unread ? 'text-gray-400' : 'text-gray-600')
                               "
                               x-text="conv.preview"></p>
                        </div>
                    </div>
                    <div x-show="conv.messages_count>1 || (conv.tags||[]).length || conv.assigned_to_initials" class="mt-1 flex items-center justify-between">
                        <div class="flex items-center gap-2" :class="activeId===conv.id?'text-white/70':'text-gray-500/80'">

                            <template x-if="conv.messages_count>1"><span class="text-[11px] font-medium flex items-center gap-1"><x-icon name="message-circle" class="w-[13px] h-[13px] stroke-[2.5]" /><span x-text="conv.messages_count"></span></span></template>
                        </div>
                        <div class="flex items-center gap-1">
                            <template x-for="tag in (conv.tags||[]).slice(0,3)" :key="tag.name"><span class="text-[10px] font-medium" :class="activeId===conv.id?'text-white/90':'text-gray-500'" x-text="'#'+tag.name"></span></template>
                            <template x-if="conv.assigned_to_initials"><span class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center text-[8px] font-bold" :class="activeId===conv.id?'text-white/80':'text-gray-400'" x-text="conv.assigned_to_initials"></span></template>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="loading"><div class="py-6 flex justify-center"><svg class="w-5 h-5 animate-spin text-[#3b82f6]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></div></template>

            <template x-if="hasMore&&!loading">
                <div class="py-4 flex justify-center"><button @click="loadMore()" class="text-sm text-[#3b82f6] hover:text-blue-300 font-medium transition-colors">Load more conversations</button></div>
            </template>
        </div>
        <div x-show="conversations.length>0" class="px-4 py-2 border-t border-[#2d3039]"><span class="text-[11px] text-gray-600" x-text="conversations.length+' conversations'"></span></div>
    </div>

    {{-- Pane 3: Detail --}}
    <div class="flex-1 min-w-0 min-h-0 h-full overflow-hidden flex flex-col">
        {{-- Compose --}}
        <div x-show="composing" x-cloak class="flex-1 flex flex-col min-h-0 h-full overflow-y-auto hide-scroll bg-[#07090e]">
            <div class="h-12 flex items-center px-6 justify-between shrink-0 border-b border-[#2d3039]">
                <div class="flex items-center gap-3">
                    <button @click="composing=false" class="p-1 text-gray-400 hover:text-gray-200 rounded-md transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></button>
                    <h2 class="text-[15px] font-semibold text-gray-200">New Message</h2>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto hide-scroll p-2"><livewire:inbox.compose-email /></div>
        </div>

        {{-- Quick Replies (inline panel — same container pattern as Compose) --}}
        <div x-show="showQuickReplies" x-cloak class="flex-1 flex flex-col min-h-0 h-full overflow-y-auto hide-scroll bg-[#07090e]">
            <div class="h-12 flex items-center px-6 justify-between shrink-0 border-b border-[#2d3039]">
                <div class="flex items-center gap-3">
                    <button @click="showQuickReplies=false" class="p-1 text-gray-400 hover:text-gray-200 rounded-md transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></button>
                    <h2 class="text-[15px] font-semibold text-gray-200">Quick Replies</h2>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto hide-scroll p-4">
                <livewire:inbox.canned-response-manager />
            </div>
        </div>

        {{-- Detail --}}
        <div x-show="!composing && !showQuickReplies" class="flex-1 flex flex-col min-h-0 h-full overflow-hidden">
            {{-- Empty state --}}
            <template x-if="activeId && !activeConversation">
                <div class="flex-1 flex items-center justify-center bg-[#07090e]">
                    <svg class="w-10 h-10 animate-spin text-[#3b82f6]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>
            </template>

            <template x-if="!activeId">
                <div class="flex-1 flex items-center justify-center bg-[#07090e]">
                    <div class="text-center"><div class="w-20 h-20 rounded-2xl bg-white/[0.02] border border-white/[0.05] flex items-center justify-center mx-auto mb-4"><x-icon name="mail" class="w-8 h-8 text-gray-700" /></div>
                    <h3 class="text-lg font-semibold text-gray-300 mb-1">Select a conversation</h3>
                    <p class="text-[13px] text-gray-600">Choose a conversation from the list to view its messages.</p></div>
                </div>
            </template>

            {{-- Mail opening animation --}}

            {{-- Conversation --}}
            <template x-if="activeId&&activeConversation">
                <div class="flex-1 flex min-h-0 h-full overflow-hidden">
                    <div class="flex-1 flex flex-col min-w-0 min-h-0 h-full">
                        {{-- Header --}}
                        <div class="h-12 flex items-center px-6 justify-between pt-2 z-10 w-full shrink-0" x-data="{actionsDrop:false,snoozeOpen:false}">
                            <div class="flex items-center gap-2 flex-1">
                                {{-- Actions dropdown --}}
                                <div class="relative" @click.away="actionsDrop=false">
                                    <button @click="actionsDrop=!actionsDrop" class="w-6 h-6 rounded-full border border-[#2d3039] bg-[#1a1d27] hover:bg-white/10 flex items-center justify-center text-gray-400 transition"><x-icon name="more-horizontal" class="w-3 h-3" /></button>
                                    <div x-show="actionsDrop" x-transition class="absolute left-0 top-full mt-1 z-50 w-56 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5" style="display:none">
                                        <button @click="doAction(activeId,'star');actionsDrop=false" class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5"><x-icon name="star" class="w-3.5 h-3.5 shrink-0" /> <span x-text="activeConversation.is_starred?'Unstar':'Star'"></span></button>
                                        <div class="relative" @mouseenter="snoozeOpen=true" @mouseleave="snoozeOpen=false">
                                            <button class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center justify-between"><span class="flex items-center gap-2.5"><x-icon name="clock" class="w-3.5 h-3.5 text-gray-500 shrink-0" /> Snooze</span><svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
                                            <div x-show="snoozeOpen" x-transition class="absolute left-full top-0 ml-1 w-48 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50" style="display:none">
                                                @foreach([['1h','1 hour'],['3h','3 hours'],['tomorrow','Tomorrow 9 AM'],['next_week','Next Monday']] as [$dur,$lbl])
                                                <button @click="doAction(activeId,'snooze',{duration:'{{$dur}}'});actionsDrop=false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">{{$lbl}}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="h-px bg-white/5 my-1.5"></div>
                                        <button x-show="activeConversation.status!=='closed'" @click="doAction(activeId,'close');actionsDrop=false" class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5"><x-icon name="check" class="w-3.5 h-3.5 text-green-400 shrink-0" /> Close conversation</button>
                                        <button x-show="activeConversation.status==='closed'" @click="doAction(activeId,'reopen');actionsDrop=false" class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5"><x-icon name="refresh-cw" class="w-3.5 h-3.5 text-blue-400 shrink-0" /> Reopen</button>
                                        <button @click="doAction(activeId,'spam');actionsDrop=false" class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5"><x-icon name="alert-triangle" class="w-3.5 h-3.5 text-orange-400 shrink-0" /> Spam</button>
                                        <button @click="if(confirm('Move to trash?'))doAction(activeId,'trash');actionsDrop=false" class="w-full text-left px-3 py-2 text-[12px] font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition flex items-center gap-2.5"><x-icon name="trash" class="w-3.5 h-3.5 shrink-0" /> Trash</button>
                                    </div>
                                </div>
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold shrink-0 bg-blue-500/20 text-blue-400" x-text="activeConversation.contact_initials"></div>
                                <span class="text-[13px] font-semibold text-gray-200 truncate" x-text="activeConversation.contact_name"></span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full border bg-green-500/10 text-green-400 border-green-500/20"><span class="w-1.5 h-1.5 rounded-full bg-current"></span><span x-text="activeConversation.status?.charAt(0).toUpperCase()+activeConversation.status?.slice(1)"></span></span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full bg-white/5 text-gray-500 border border-white/10" x-text="activeConversation.channel?.charAt(0).toUpperCase()+activeConversation.channel?.slice(1)"></span>
                            </div>
                            <button @click="rightPanel=!rightPanel" class="p-1 rounded transition" :class="rightPanel?'text-blue-400':'text-gray-400 hover:text-blue-400'" title="Details"><x-icon name="panel-right" class="w-[18px] h-[18px] stroke-[2]" /></button>
                        </div>

                        {{-- Messages --}}
                        <div class="flex-1 min-h-0 overflow-y-auto" style="scrollbar-gutter:stable" x-ref="messagesContainer">
                            <div class="px-6 pt-4 pb-6">
                                {{-- Subject + AI Assistant button --}}
                                <div class="flex items-start justify-between gap-3 mb-6">
                                    <h1 class="text-[26px] font-bold text-gray-100 flex-1 min-w-0" x-text="activeConversation.subject||'(no subject)'"></h1>
                                    {{-- AI Assistant — opens a right-side drawer with a 2-line
                                         thread summary and a chat input so the agent can ask
                                         follow-up questions about this conversation. --}}
                                    <button @click="openAiPanel()" type="button"
                                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-white bg-gradient-to-r from-[#3b82f6] to-purple-500 hover:from-[#2563eb] hover:to-purple-600 border border-white/10 transition shadow-lg shadow-purple-500/20"
                                            title="Open AI Assistant for this conversation">
                                        {{-- Heroicons "sparkles" — the wand-with-twinkles icon, the right
                                             visual for an AI feature (the gear-icon was wrong, fixed). --}}
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 008.279 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a2.625 2.625 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a2.625 2.625 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0116.5 15z"/>
                                        </svg>
                                        <span>AI Assistant</span>
                                    </button>
                                </div>
                                {{-- Tags inline --}}
                                <template x-if="activeConversation.tags&&activeConversation.tags.length">
                                    <div class="flex flex-wrap gap-1.5 mb-6">
                                        <template x-for="tag in activeConversation.tags" :key="tag.id">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border" :style="'background-color:'+tag.color+'15;color:'+tag.color+';border-color:'+tag.color+'30'">
                                                <span class="w-1.5 h-1.5 rounded-full" :style="'background-color:'+tag.color"></span>
                                                <span x-text="tag.name"></span>
                                                <button @click="doAction(activeId,'remove_tag',{tag_id:tag.id})" class="ml-0.5 hover:opacity-70">&times;</button>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="loadingMessages"><div class="flex justify-center py-16"><svg class="w-6 h-6 animate-spin text-[#3b82f6]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></div></template>
                                <div class="space-y-5">
                                    <template x-for="(msg,idx) in activeMessages" :key="msg.id">
                                        <div class="msg-appear" :style="'animation-delay:'+(idx*0.1)+'s'">
                                            {{-- Date divider --}}
                                            <template x-if="idx===0||msg.date_group!==activeMessages[idx-1]?.date_group">
                                                <div class="flex items-center gap-3 py-2"><div class="flex-1 h-px bg-[#2d3039]"></div><span class="text-[11px] text-gray-500 font-medium px-2" x-text="msg.date_group"></span><div class="flex-1 h-px bg-[#2d3039]"></div></div>
                                            </template>

                                            {{-- Internal Note (team-only, yellow) --}}
                                            <template x-if="msg.type === 'note'">
                                                <div class="mx-auto w-full max-w-[92%]">
                                                    <div class="rounded-xl border border-yellow-500/20 bg-yellow-500/5 px-4 py-3">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold rounded bg-yellow-500/10 text-yellow-400 uppercase tracking-wider">
                                                                <x-icon name="sticky-note" class="w-[10px] h-[10px]" />
                                                                Internal Note
                                                            </span>
                                                            <span class="text-xs font-medium text-gray-300" x-text="msg.sender_name"></span>
                                                            <span class="text-[11px] text-gray-500" x-text="msg.time"></span>
                                                        </div>
                                                        <div class="text-sm leading-relaxed whitespace-pre-wrap text-gray-300" x-text="msg.body_text"></div>
                                                        <p class="mt-2 text-[11px] text-gray-600 italic">(Only visible to your team — not sent to the customer)</p>
                                                    </div>
                                                </div>
                                            </template>

                                            {{-- Message bubble --}}
                                            <div x-show="msg.type !== 'note'" :class="activeConversation.channel==='email' ? 'flex gap-3 w-full border-b border-[#2d3039] pb-5' : (msg.direction==='outbound'?'flex flex-row-reverse gap-3 max-w-[85%] ml-auto':'flex gap-3 max-w-[92%]')">
                                                <div class="mail-avatar-initial w-10 h-10 rounded-full flex items-center justify-center text-lg font-semibold shrink-0 mt-0.5" x-text="msg.sender_initials"></div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2 mb-1" :class="msg.direction==='outbound'?'justify-end':''">
                                                        <span class="text-sm font-semibold text-gray-200" x-text="msg.sender_name"></span>
                                                        <span class="text-[11px] text-gray-600" x-text="'via '+msg.channel"></span>
                                                        <span class="text-[11px] text-gray-500" x-text="msg.time"></span>
                                                    </div>
                                                    <div x-show="msg.from_email" class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 mb-2">
                                                        <span>From: <span class="text-gray-400" x-text="(msg.from_name||'')+' <'+msg.from_email+'>'"></span></span>
                                                        <template x-if="msg.to_emails&&msg.to_emails.length"><span>To: <span class="text-gray-400" x-text="msg.to_emails.join(', ')"></span></span></template>
                                                        <span x-text="msg.date"></span>
                                                    </div>
                                                    <div :class="activeConversation.channel==='email' ? 'rounded-lg overflow-hidden bg-[#0f1117]' : (msg.direction==='outbound'?'rounded-xl rounded-tr-sm border border-[#3b82f6]/20 overflow-hidden bg-[#0f1117]':'rounded-xl rounded-tl-sm border border-[#2d3039] overflow-hidden bg-[#0f1117]')"
                                                        >
                                                         <template x-if="msg.has_html">
                                                            <div x-data="{loaded:false}" x-init="window.renderEmail($el, msg.id, ()=>{loaded=true})" class="email-shadow-host rounded-lg overflow-auto">
                                                                <div x-show="!loaded" class="px-4 py-3 text-sm text-gray-300 whitespace-pre-wrap" x-text="msg.body_text"></div>
                                                            </div>
                                                        </template>
                                                        <template x-if="!msg.has_html">
                                                            <div class="px-4 py-3 text-sm leading-relaxed text-gray-300 whitespace-pre-wrap" x-text="msg.body_text"></div>
                                                        </template>
                                                    </div>
                                                    <template x-if="msg.attachments&&msg.attachments.length">
                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            <template x-for="att in msg.attachments" :key="att.id">
                                                                <a :href="att.url" target="_blank" download class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#2d3039] bg-white/[0.02] text-xs hover:bg-white/[0.05] transition group">
                                                                    <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                                    <span class="font-medium truncate max-w-[180px] text-gray-300" x-text="att.filename"></span>
                                                                    <span class="text-gray-600" x-text="att.size"></span>
                                                                    <svg class="w-3 h-3 text-[#3b82f6] opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                                </a>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    {{-- Outbound delivery status badge. Keep it small + single-line. --}}
                                                    <template x-if="msg.direction==='outbound'">
                                                        <div class="flex items-center gap-1 justify-end mt-1 text-[10px] leading-none">
                                                            {{-- Scheduled & not yet sent --}}
                                                            <template x-if="msg.schedule_status === 'pending'">
                                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 whitespace-nowrap">
                                                                    <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5"/></svg>
                                                                    <span x-text="msg.scheduled_at_pretty ? 'Scheduled · ' + msg.scheduled_at_pretty : 'Scheduled'"></span>
                                                                </span>
                                                            </template>
                                                            {{-- Cancelled (undo-send clicked) --}}
                                                            <template x-if="msg.schedule_status === 'cancelled'">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-500/10 text-gray-400 border border-gray-500/20">Cancelled</span>
                                                            </template>
                                                            {{-- Failed --}}
                                                            <template x-if="msg.schedule_status !== 'pending' && msg.schedule_status !== 'cancelled' && msg.delivery_status === 'failed'">
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-red-500/10 text-red-400 border border-red-500/20" :title="msg.delivery_error || 'Send failed'">Failed</span>
                                                            </template>
                                                            {{-- Sent successfully --}}
                                                            <template x-if="msg.schedule_status === 'sent' || (msg.delivery_status === 'sent' && !msg.schedule_status)">
                                                                <span class="inline-flex items-center gap-1 text-gray-500">
                                                                    <svg class="w-2.5 h-2.5 shrink-0 text-green-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                    <span>Sent</span>
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Load more messages --}}
                                <template x-if="hasMoreMessages && !loadingMoreMessages">
                                    <div class="py-4 flex justify-center">
                                        <button @click="loadMoreMessages()" class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-[12px] font-medium text-gray-400 hover:bg-white/10 hover:text-gray-200 transition">
                                            <x-icon name="chevron-down" class="w-3.5 h-3.5" /> Load more messages
                                        </button>
                                    </div>
                                </template>
                                <template x-if="loadingMoreMessages">
                                    <div class="py-4 flex justify-center">
                                        <svg class="w-5 h-5 animate-spin text-[#3b82f6]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <section aria-label="{{ __('Reply') }}" class="shrink-0 border-t border-[#2d3039] max-h-[45vh] overflow-y-auto">
                            <button type="button" @click="composerOpen = !composerOpen" :aria-expanded="composerOpen" aria-controls="email-reply-composer" class="m-3 inline-flex items-center justify-center rounded-full bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 focus-visible:ring-2 focus-visible:ring-blue-300" x-text="composerOpen ? 'Hide reply' : 'Reply'"></button>
                            <div id="email-reply-composer" x-show="composerOpen" x-cloak>
                                <livewire:inbox.reply-composer />
                            </div>
                        </section>
                    </div>

                    {{-- Right Panel --}}
                    <div x-show="rightPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="w-[280px] bg-[#11131a]/95 border-l border-[#2d3039] flex flex-col shrink-0 overflow-y-auto hide-scroll backdrop-blur-xl" style="display:none">
                        <div class="h-6"></div>
                        <div class="p-4 space-y-[6px]">
                            {{-- Tags --}}
                            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3"><x-icon name="tag" class="w-[14px] h-[14px]" /> Tags</div>
                                <div class="space-y-2 pl-[22px]">
                                    <template x-for="tag in (activeConversation.tags||[])" :key="tag.id">
                                        <div class="flex items-center justify-between text-[11px] font-medium text-gray-300 group">
                                            <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full" :style="'background-color:'+(tag.color||'#6B7280')"></span><span x-text="tag.name"></span></div>
                                            <button @click="doAction(activeId,'remove_tag',{tag_id:tag.id})" class="text-gray-600 hover:text-red-400 opacity-0 group-hover:opacity-100 transition"><x-icon name="x" class="w-3 h-3" /></button>
                                        </div>
                                    </template>
                                    <div x-data="{open:false}" class="relative">
                                        <button @click="open=!open" class="text-[11px] text-[#3b82f6] hover:text-blue-300 font-medium flex items-center gap-1"><x-icon name="plus" class="w-3 h-3" /> Add tag</button>
                                        <div x-show="open" @click.away="open=false" class="absolute left-0 top-full mt-1 w-44 bg-[#1a1d27] border border-white/10 rounded-xl shadow-lg py-1.5 z-50 max-h-48 overflow-y-auto" style="display:none">
                                            <template x-for="t in tags" :key="t.id"><button @click="doAction(activeId,'add_tag',{tag_id:t.id});open=false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2"><span class="w-2 h-2 rounded-full" :style="'background-color:'+(t.color||'#6B7280')"></span><span x-text="t.name"></span></button></template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Assignment --}}
                            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3"><x-icon name="user-plus" class="w-[14px] h-[14px]" /> Assignment</div>
                                <div class="pl-[22px]">
                                    <p class="text-[11px] text-gray-300 mb-2" x-text="activeConversation.assigned_to||'Unassigned'"></p>
                                    <div x-data="{open:false}" class="relative">
                                        <button @click="open=!open" class="text-[11px] text-[#3b82f6] hover:text-blue-300 font-medium flex items-center gap-1"><x-icon name="user-plus" class="w-3 h-3" /> <span x-text="activeConversation.assigned_to?'Reassign':'Assign'"></span></button>
                                        <div x-show="open" @click.away="open=false" class="absolute left-0 top-full mt-1 w-48 bg-[#1a1d27] border border-white/10 rounded-xl shadow-lg py-1.5 z-50 max-h-48 overflow-y-auto" style="display:none">
                                            <button @click="doAction(activeId,'assign',{user_id:null});open=false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-400 hover:bg-white/5 hover:text-white transition">Unassigned</button>
                                            <template x-for="m in teamMembers" :key="m.id"><button @click="doAction(activeId,'assign',{user_id:m.id});open=false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2"><span class="w-4 h-4 rounded-full bg-[#3b82f6]/10 text-[#3b82f6] flex items-center justify-center text-[8px] font-bold shrink-0" x-text="m.initials"></span><span x-text="m.name"></span></button></template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- Priority --}}
                            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3"><x-icon name="flag" class="w-[14px] h-[14px]" /> Priority</div>
                                <div class="pl-[22px] space-y-1">
                                    @foreach([['low','Low','bg-gray-400'],['normal','Normal','bg-blue-400'],['high','High','bg-yellow-400'],['urgent','Urgent','bg-red-400']] as [$pv,$pl,$pd])
                                    <button @click="doAction(activeId,'priority',{value:'{{$pv}}'})" :class="activeConversation.priority==='{{$pv}}'?'bg-white/5 text-gray-200':'text-gray-500 hover:bg-white/5 hover:text-gray-300'" class="w-full text-left flex items-center gap-2 px-2 py-1 rounded-md text-[11px] font-medium transition-colors">
                                        <span class="w-2 h-2 rounded-full {{$pd}}"></span> {{$pl}}
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Contact --}}
                            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3"><x-icon name="user" class="w-[14px] h-[14px]" /> Contact</div>
                                <div class="pl-[22px] space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs font-bold" x-text="activeConversation.contact_initials"></div>
                                        <div>
                                            <div class="text-[12px] font-medium text-gray-200" x-text="activeConversation.contact_name"></div>
                                            <div class="text-[10px] text-gray-500" x-text="activeConversation.contact_email"></div>
                                        </div>
                                    </div>
                                    <div x-show="activeConversation.contact_phone" class="flex justify-between text-[11px]">
                                        <span class="text-gray-500">Phone</span>
                                        <span class="text-gray-300" x-text="activeConversation.contact_phone"></span>
                                    </div>
                                    <div x-show="activeConversation.contact_company" class="flex justify-between text-[11px]">
                                        <span class="text-gray-500">Company</span>
                                        <span class="text-gray-300" x-text="activeConversation.contact_company"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Stripe customer profile — shown only when the workspace
                                 has an active Stripe integration AND the contact email
                                 matches a Stripe customer. Read-only support context:
                                 customer id, total spent, subscriptions, recent payments,
                                 delinquent warning. --}}
                            <template x-if="activeConversation.stripe_customer">
                                <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                    <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                                        <svg class="w-[14px] h-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        Stripe
                                        <template x-if="activeConversation.stripe_customer.has_active_subscription">
                                            <span class="bg-green-500/10 text-green-400 text-[10px] px-1.5 py-0.5 rounded-full font-medium border border-green-500/20">Active</span>
                                        </template>
                                    </div>
                                    <div class="pl-[22px] space-y-2">
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-gray-500">ID</span>
                                            <span class="text-gray-300 font-mono text-[10px]" x-text="activeConversation.stripe_customer.customer?.id"></span>
                                        </div>
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-gray-500">Spent</span>
                                            <span class="text-gray-200 font-semibold" x-text="'$' + (Number(activeConversation.stripe_customer.total_spent || 0)).toFixed(2)"></span>
                                        </div>
                                        <template x-if="activeConversation.stripe_customer.customer?.delinquent">
                                            <div class="p-2 bg-red-500/10 border border-red-500/20 rounded-lg">
                                                <p class="text-[11px] text-red-400 font-medium">Payment delinquent</p>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.stripe_customer.subscriptions && activeConversation.stripe_customer.subscriptions.length">
                                            <div class="pt-1">
                                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">Subscriptions</p>
                                                <template x-for="sub in activeConversation.stripe_customer.subscriptions" :key="sub.id || sub.status">
                                                    <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                                                        <div>
                                                            <p class="text-[11px] font-medium text-gray-300" x-text="(sub.plan_names || []).join(', ')"></p>
                                                            <p class="text-[10px] text-gray-500" x-text="'$' + Number(sub.amount_total || 0).toFixed(2) + '/' + (sub.interval || '')"></p>
                                                        </div>
                                                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full"
                                                              :class="sub.status === 'active' ? 'bg-green-500/10 text-green-400' : (sub.status === 'past_due' ? 'bg-red-500/10 text-red-400' : (sub.status === 'trialing' ? 'bg-blue-500/10 text-blue-400' : 'bg-white/5 text-gray-500'))"
                                                              x-text="(sub.status || '').charAt(0).toUpperCase() + (sub.status || '').slice(1)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.stripe_customer.payments && activeConversation.stripe_customer.payments.length">
                                            <div class="pt-1">
                                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">Recent Payments</p>
                                                <template x-for="payment in activeConversation.stripe_customer.payments.slice(0,5)" :key="payment.id || payment.created">
                                                    <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                                                        <div>
                                                            <p class="text-[11px] text-gray-300" x-text="'$' + Number(payment.amount || 0).toFixed(2)"></p>
                                                            <p class="text-[10px] text-gray-500" x-text="payment.created_human"></p>
                                                        </div>
                                                        <template x-if="payment.refunded"><span class="px-1.5 py-0.5 text-[10px] font-medium bg-yellow-500/10 text-yellow-400 rounded-full">Refunded</span></template>
                                                        <template x-if="!payment.refunded && payment.paid"><span class="px-1.5 py-0.5 text-[10px] font-medium bg-green-500/10 text-green-400 rounded-full">Paid</span></template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- HubSpot contact profile — shown when HubSpot is active and
                                 the contact's email matches a HubSpot record. Shows
                                 lifecycle stage, associated deals, and a deep-link to
                                 the HubSpot contact page. --}}
                            <template x-if="activeConversation.hubspot_contact">
                                <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                    <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                                        <svg class="w-[14px] h-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        HubSpot
                                        <template x-if="activeConversation.hubspot_contact.lifecycle_stage">
                                            <span class="bg-orange-500/10 text-orange-400 text-[10px] px-1.5 py-0.5 rounded-full font-medium border border-orange-500/20" x-text="activeConversation.hubspot_contact.lifecycle_stage"></span>
                                        </template>
                                    </div>
                                    <div class="pl-[22px] space-y-2">
                                        <template x-if="activeConversation.hubspot_contact.full_name">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Name</span>
                                                <span class="text-gray-300" x-text="activeConversation.hubspot_contact.full_name"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.hubspot_contact.company">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Company</span>
                                                <span class="text-gray-300" x-text="activeConversation.hubspot_contact.company"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.hubspot_contact.job_title">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Title</span>
                                                <span class="text-gray-300" x-text="activeConversation.hubspot_contact.job_title"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.hubspot_contact.lead_status">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Lead status</span>
                                                <span class="text-gray-300" x-text="activeConversation.hubspot_contact.lead_status"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.hubspot_contact.deals && activeConversation.hubspot_contact.deals.length">
                                            <div class="pt-1">
                                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">Deals</p>
                                                <template x-for="deal in activeConversation.hubspot_contact.deals" :key="deal.id">
                                                    <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                                                        <div class="min-w-0">
                                                            <p class="text-[11px] font-medium text-gray-300 truncate" x-text="deal.name"></p>
                                                            <p class="text-[10px] text-gray-500" x-text="deal.stage || ''"></p>
                                                        </div>
                                                        <template x-if="deal.amount">
                                                            <span class="text-[11px] text-gray-300 font-medium shrink-0 ml-2" x-text="'$' + Number(deal.amount).toFixed(2)"></span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.hubspot_contact.url">
                                            <a :href="activeConversation.hubspot_contact.url" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-[11px] text-orange-400 hover:text-orange-300 transition">
                                                Open in HubSpot
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Salesforce contact profile — shown when Salesforce is
                                 active and the email matches a Salesforce Contact. Shows
                                 account, open opportunities, and a deep-link to the
                                 Salesforce record. --}}
                            <template x-if="activeConversation.salesforce_contact">
                                <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                    <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                                        <svg class="w-[14px] h-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                        Salesforce
                                    </div>
                                    <div class="pl-[22px] space-y-2">
                                        <template x-if="activeConversation.salesforce_contact.full_name">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Name</span>
                                                <span class="text-gray-300" x-text="activeConversation.salesforce_contact.full_name"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.salesforce_contact.account_name">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Account</span>
                                                <span class="text-gray-300" x-text="activeConversation.salesforce_contact.account_name"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.salesforce_contact.title">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Title</span>
                                                <span class="text-gray-300" x-text="activeConversation.salesforce_contact.title"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.salesforce_contact.lead_source">
                                            <div class="flex justify-between text-[11px]">
                                                <span class="text-gray-500">Lead source</span>
                                                <span class="text-gray-300" x-text="activeConversation.salesforce_contact.lead_source"></span>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.salesforce_contact.opportunities && activeConversation.salesforce_contact.opportunities.length">
                                            <div class="pt-1">
                                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">Opportunities</p>
                                                <template x-for="opp in activeConversation.salesforce_contact.opportunities" :key="opp.id">
                                                    <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                                                        <div class="min-w-0">
                                                            <p class="text-[11px] font-medium text-gray-300 truncate" x-text="opp.name"></p>
                                                            <p class="text-[10px] text-gray-500" x-text="opp.stage || ''"></p>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 shrink-0 ml-2">
                                                            <template x-if="opp.amount">
                                                                <span class="text-[11px] text-gray-300 font-medium" x-text="'$' + Number(opp.amount).toFixed(2)"></span>
                                                            </template>
                                                            <template x-if="opp.is_won">
                                                                <span class="px-1.5 py-0.5 text-[10px] font-medium bg-green-500/10 text-green-400 rounded-full">Won</span>
                                                            </template>
                                                            <template x-if="opp.is_closed && !opp.is_won">
                                                                <span class="px-1.5 py-0.5 text-[10px] font-medium bg-red-500/10 text-red-400 rounded-full">Lost</span>
                                                            </template>
                                                            <template x-if="!opp.is_closed">
                                                                <span class="px-1.5 py-0.5 text-[10px] font-medium bg-blue-500/10 text-blue-400 rounded-full">Open</span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="activeConversation.salesforce_contact.url">
                                            <a :href="activeConversation.salesforce_contact.url" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-[11px] text-blue-400 hover:text-blue-300 transition">
                                                Open in Salesforce
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Details --}}
                            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3"><x-icon name="info" class="w-[14px] h-[14px]" /> Details</div>
                                <div class="pl-[22px] space-y-3">
                                    <div class="space-y-2">
                                        <span class="text-[11px] text-gray-500 font-medium">Channel</span>
                                        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-300 rounded-lg p-2.5 flex items-center gap-2 text-[11px] font-medium">
                                            <x-icon name="mail" class="w-3 h-3" />
                                            <span x-text="(activeConversation.channel||'email')+' — '+(activeConversation.account_email||'')"></span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">Messages</span>
                                        <span class="text-gray-300 font-semibold" x-text="activeConversation.messages_count||activeMessages.length"></span>
                                    </div>
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">Last message</span>
                                        <span class="text-gray-300" x-text="activeConversation.last_message_at||'—'"></span>
                                    </div>
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">Last by</span>
                                        <span class="text-gray-300 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="activeConversation.last_message_direction==='inbound'?'bg-amber-400':'bg-blue-400'"></span>
                                            <span x-text="activeConversation.last_message_by||'—'"></span>
                                        </span>
                                    </div>
                                    <div x-show="activeConversation.response_time" class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">Response time</span>
                                        <span class="text-green-400 font-medium" x-text="activeConversation.response_time"></span>
                                    </div>
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">First message</span>
                                        <span class="text-gray-300" x-text="activeConversation.first_message_at||'—'"></span>
                                    </div>
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-gray-500 font-medium">Created</span>
                                        <span class="text-gray-300" x-text="activeConversation.created_at||'—'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Mobile tabs --}}
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#15171e] border-t border-[#2d3039] flex">
        <button @click="mobileView='sidebar'" :class="mobileView==='sidebar'?'text-[#3b82f6]':'text-gray-500'" class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors"><x-icon name="settings" class="w-4 h-4" /> Channels</button>
        <button @click="mobileView='list'" :class="mobileView==='list'?'text-[#3b82f6]':'text-gray-500'" class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors"><x-icon name="inbox" class="w-4 h-4" /> Inbox</button>
        <button @click="mobileView='detail'" :class="mobileView==='detail'?'text-[#3b82f6]':'text-gray-500'" class="flex-1 flex flex-col items-center justify-center py-1.5 text-[10px] font-medium transition-colors"><x-icon name="message-square" class="w-4 h-4" /> Message</button>
    </div>

    {{-- ═══ AI ASSISTANT — slide-in right drawer ═══
         Opens when the agent clicks the "AI Assistant" button in the
         conversation header. Body has 3 sections:
           1. Initial 2-line thread summary (with shimmer skeleton while loading)
           2. Quick-prompt chips (one-click common questions)
           3. Chat thread + input box (free-form follow-up Q&A)
    --}}
    <div x-show="aiPanel" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="aiPanel=false"
         class="fixed inset-0 z-50 pointer-events-none">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm pointer-events-auto" @click="closeAiPanel()"></div>

        {{-- Drawer --}}
        <aside x-show="aiPanel"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="absolute right-0 top-0 bottom-0 w-full sm:w-[440px] bg-[#15171e] border-l border-[#2d3039] shadow-[-20px_0_60px_rgba(0,0,0,0.5)] flex flex-col pointer-events-auto"
               role="dialog" aria-label="AI Assistant">

            {{-- Header --}}
            <div class="shrink-0 flex items-center justify-between px-5 py-4 border-b border-[#2d3039] bg-gradient-to-r from-[#3b82f6]/10 to-purple-500/10">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#3b82f6] to-purple-500 flex items-center justify-center shadow-lg shadow-purple-500/30">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 008.279 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a2.625 2.625 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a2.625 2.625 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0116.5 15z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[14px] font-semibold text-gray-100">AI Assistant</div>
                        <div class="text-[11px] text-gray-500 truncate" x-text="activeConversation?.subject||'Thread context'"></div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="refreshAiSummary()" :disabled="summarizingThread"
                            class="p-1.5 rounded-md text-gray-400 hover:text-gray-200 hover:bg-white/5 transition disabled:opacity-50"
                            title="Refresh summary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="summarizingThread?'animate-spin':''"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                    <button @click="closeAiPanel()" class="p-1.5 rounded-md text-gray-400 hover:text-gray-200 hover:bg-white/5 transition" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-h-0 overflow-y-auto hide-scroll px-5 py-4 space-y-5" x-ref="aiPanelBody">

                {{-- Initial summary card --}}
                <div class="rounded-xl border border-[#3b82f6]/25 bg-gradient-to-r from-[#3b82f6]/10 to-purple-500/5 p-4">
                    <div class="flex items-center gap-1.5 mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-[#3b82f6]">Thread Recap</span>
                        <span x-show="!summarizingThread && aiSummary" class="text-[10px] text-gray-500">· auto-generated</span>
                    </div>
                    {{-- Shimmer skeleton --}}
                    <template x-if="summarizingThread">
                        <div class="space-y-2">
                            <div class="h-3 rounded bg-gradient-to-r from-white/5 via-white/15 to-white/5 ai-shimmer" style="width:92%"></div>
                            <div class="h-3 rounded bg-gradient-to-r from-white/5 via-white/15 to-white/5 ai-shimmer" style="width:78%"></div>
                            <div class="h-3 rounded bg-gradient-to-r from-white/5 via-white/15 to-white/5 ai-shimmer" style="width:85%"></div>
                        </div>
                    </template>
                    <template x-if="!summarizingThread && aiSummary">
                        <div class="text-[13px] text-gray-200 leading-relaxed whitespace-pre-line" x-text="aiSummary"></div>
                    </template>
                </div>

                {{-- Quick-prompt chips --}}
                <div x-show="aiSummary && !summarizingThread">
                    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500 mb-2">Quick actions</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button @click="sendAiChat('Draft a professional reply I can send right now.')"
                                :disabled="aiChatLoading"
                                class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 transition disabled:opacity-50">
                            ✍️ Draft a reply
                        </button>
                        <button @click="sendAiChat('What is the customer\'s sentiment? Are they angry, satisfied, neutral, or frustrated?')"
                                :disabled="aiChatLoading"
                                class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 transition disabled:opacity-50">
                            😊 Sentiment
                        </button>
                        <button @click="sendAiChat('What are the next 3 steps the agent should take to resolve this?')"
                                :disabled="aiChatLoading"
                                class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 transition disabled:opacity-50">
                            ✅ Next steps
                        </button>
                        <button @click="sendAiChat('Extract any order numbers, ticket IDs, dates, account ids, or other key facts from this thread as a short list.')"
                                :disabled="aiChatLoading"
                                class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 transition disabled:opacity-50">
                            🔑 Key facts
                        </button>
                        <button @click="sendAiChat('Translate the latest customer message to English.')"
                                :disabled="aiChatLoading"
                                class="px-2.5 py-1 text-[11px] font-medium rounded-full bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10 transition disabled:opacity-50">
                            🌐 Translate
                        </button>
                    </div>
                </div>

                {{-- Chat thread --}}
                <template x-if="aiMessages.length > 0">
                    <div class="space-y-3 pt-1">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500">Conversation</div>
                            <button @click="clearAiChat()" class="text-[10px] text-gray-500 hover:text-gray-300">Clear</button>
                        </div>
                        <template x-for="(m, i) in aiMessages" :key="i">
                            <div :class="m.role==='user' ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="m.role==='user'
                                            ? 'max-w-[85%] px-3 py-2 rounded-2xl rounded-br-sm bg-[#3b82f6] text-white text-[13px] leading-relaxed'
                                            : (m.error ? 'max-w-[85%] px-3 py-2 rounded-2xl rounded-bl-sm bg-red-500/10 border border-red-500/30 text-red-300 text-[13px] leading-relaxed'
                                                       : 'max-w-[85%] px-3 py-2 rounded-2xl rounded-bl-sm bg-white/5 border border-white/10 text-gray-200 text-[13px] leading-relaxed whitespace-pre-line')">
                                    {{-- Pending shimmer for assistant placeholder --}}
                                    <template x-if="m.pending">
                                        <div class="space-y-1.5 py-1 min-w-[140px]">
                                            <div class="h-2.5 rounded bg-gradient-to-r from-white/10 via-white/25 to-white/10 ai-shimmer" style="width:80%"></div>
                                            <div class="h-2.5 rounded bg-gradient-to-r from-white/10 via-white/25 to-white/10 ai-shimmer" style="width:60%"></div>
                                        </div>
                                    </template>
                                    <template x-if="!m.pending">
                                        <span x-text="m.content"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

            </div>

            {{-- Footer input --}}
            <div class="shrink-0 px-4 py-3 border-t border-[#2d3039] bg-[#1a1d27]">
                <form @submit.prevent="sendAiChat()" class="flex items-end gap-2">
                    <textarea x-model="aiInput" rows="1"
                              @keydown.enter.exact.prevent="sendAiChat()"
                              @input="$el.style.height='auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                              :disabled="aiChatLoading || !aiSummary"
                              placeholder="Ask the AI anything about this thread…"
                              class="flex-1 resize-none bg-[#15171e] border border-[#2d3039] focus:border-[#3b82f6]/50 focus:ring-1 focus:ring-[#3b82f6]/30 outline-none rounded-xl px-3 py-2 text-[13px] text-gray-200 placeholder:text-gray-600 disabled:opacity-50 max-h-[120px] hide-scroll"></textarea>
                    <button type="submit" :disabled="aiChatLoading || !aiInput.trim() || !aiSummary"
                            class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-r from-[#3b82f6] to-purple-500 hover:from-[#2563eb] hover:to-purple-600 flex items-center justify-center text-white shadow-lg shadow-purple-500/30 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            aria-label="Send">
                        <svg x-show="!aiChatLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <svg x-show="aiChatLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </button>
                </form>
                <div class="mt-1.5 text-[10px] text-gray-600 text-center">Press <kbd class="px-1 py-0.5 bg-white/5 rounded border border-white/10">Enter</kbd> to send · <kbd class="px-1 py-0.5 bg-white/5 rounded border border-white/10">Shift</kbd>+<kbd class="px-1 py-0.5 bg-white/5 rounded border border-white/10">Enter</kbd> for newline</div>
            </div>
        </aside>
    </div>
</div>

<style>
.msg-appear { animation: msgFadeIn 0.3s ease-out both; }
@keyframes msgFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* AI Assistant — shimmer skeleton while loading the summary or a chat reply.
   Background-size:200% lets the gradient animate across the placeholder. */
.ai-shimmer { background-size: 200% 100%; animation: aiShimmer 1.4s ease-in-out infinite; }
@keyframes aiShimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }

/* Email content rendered directly (no iframe) */
.email-body {
    padding: 16px;
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #e2e8f0;
    word-break: break-word;
    overflow: hidden;
}
.email-body img { max-width: 100%; height: auto; border-radius: 8px; }
.email-body a { color: #60a5fa; text-decoration: underline; }
.email-body table { max-width: 100% !important; width: auto !important; }
.email-body td, .email-body th { padding: 4px 8px; }
.email-body blockquote { border-left: 3px solid #3b82f6; margin: 8px 0; padding: 4px 12px; color: #94a3b8; }
.email-body h1, .email-body h2, .email-body h3 { color: #f1f5f9; margin: 12px 0 8px; }
.email-body p { margin: 0 0 8px; }
.email-body hr { border-color: #2d3039; }
/* Override email inline styles for dark theme */
.email-body [style*="background-color: #fff"],
.email-body [style*="background-color: white"],
.email-body [style*="background:#fff"],
.email-body [style*="background: #fff"],
.email-body [style*="background-color:#ffffff"] { background-color: transparent !important; }
.email-body [style*="color: #000"],
.email-body [style*="color:#000"],
.email-body [style*="color: black"],
.email-body [style*="color:black"] { color: #e2e8f0 !important; }
.email-body body { background: transparent !important; margin: 0 !important; padding: 0 !important; }
</style>

<script>
window.renderEmail = function(el, msgId, onDone) {
    var baseUrl = '{{ url("") }}';
    var fallbackCSS = '<style>body,html{margin:0;padding:0}*{box-sizing:border-box}img{max-width:100%;height:auto}a{color:#3b82f6}table{max-width:100%!important;border-collapse:collapse}td,th{padding:4px 8px}blockquote{border-left:3px solid #e5e7eb;margin:8px 0;padding:4px 12px;color:#6b7280}h1,h2,h3{margin:12px 0 8px}p{margin:0 0 8px}hr{border:none;border-top:1px solid #e5e7eb}</style>';
    var wrapStyle = 'font-family:system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.7;color:#1e293b;word-break:break-word;padding:20px;background:#fff';

    function render(html) {
        var shadow = el.attachShadow ? el.attachShadow({mode:'open'}) : el;
        var hasStyle = html.includes('<style') || html.includes('style=');
        var css = (hasStyle ? '' : fallbackCSS) + '<style>:host{display:block;max-width:100%;overflow:auto}img,video{max-width:100%!important;height:auto!important}table{max-width:100%!important}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style>';
        shadow.innerHTML = css + '<div style="' + wrapStyle + '">' + html + '</div>';
        window.emailCache[msgId] = html;
        if (onDone) onDone();
    }

    var cached = window.emailCache[msgId];
    if (cached) {
        render(cached);
    } else {
        fetch(baseUrl + '/messages/' + msgId + '/html?raw=1')
            .then(function(r) { return r.text(); })
            .then(render)
            .catch(function() { if (onDone) onDone(); });
    }
};
function inboxApp() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const baseUrl = '{{ url("") }}';
    const avatarColors = ['mail-avatar-initial'];
    const headers = {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf};
    window.emailCache = window.emailCache || {};

    return {
        conversations:[], activeId:null, activeConversation:null, activeMessages:[],
        page:1, hasMore:false, loading:false, loadingMessages:false,
        hasMoreMessages:false, loadingMoreMessages:false, msgOffset:0, totalMessages:0,
        composerOpen:false, composing:false, showQuickReplies:false, searchOpen:false, search:'',
        folder:'inbox', channel:'all', sortBy:'newest',
        filterTag:'', filterAssignee:null, filterAccountId:null,
        selectedIds:[], selectAll:false,
        sidebarCounts:{}, tags:[], teamMembers:[],
        // Email accounts connected to this workspace — drives the
        // "All Accounts ▾" dropdown at the top of the inbox sidebar.
        emailAccounts:[], accountDrop:false,
        lastId:0, pollTimer:null, realtimeListener:null, conversationListener:null, _syncStopped:false, _syncTimer:null, _listVersion:0, _listBusy:false, rightPanel:false, mobileView:'list',
        // AI Assistant panel state — slide-in right drawer with the
        // initial thread summary at the top + a chat thread below where
        // the agent can ask follow-up questions ("draft a reply",
        // "is the customer angry?"). Cleared when switching conversations.
        aiPanel:false, aiSummary:null, summarizingThread:false,
        aiMessages:[], aiInput:'', aiChatLoading:false,

        avatarColor(id) { return avatarColors[id % avatarColors.length]; },

        // Returns an inline SVG string for a channel — rendered via x-html
        // on each conversation row so the viewer can tell at a glance
        // whether a thread is an email, a WhatsApp chat, a Telegram DM,
        // etc. without clicking in. Using raw SVG (instead of the x-icon
        // Blade component) because Alpine x-for cannot swap the name
        // attribute dynamically — the component renders at compile time.
        channelIcon(ch) {
            const stroke = 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
            const common = `viewBox="0 0 24 24" fill="none" ${stroke} class="w-3.5 h-3.5"`;
            switch (ch) {
                case 'email':
                    return `<svg ${common}><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>`;
                case 'whatsapp':
                    return `<svg ${common}><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
                case 'sms':
                    return `<svg ${common}><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>`;
                case 'telegram':
                    return `<svg ${common}><path d="m22 2-7 20-4-9-9-4 20-7z"/><path d="M22 2 11 13"/></svg>`;
                case 'slack':
                    return `<svg ${common}><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>`;
                case 'chat':
                case 'live_chat':
                    return `<svg ${common}><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
                default:
                    return `<svg ${common}><circle cx="12" cy="12" r="10"/></svg>`;
            }
        },

        // Short label shown alongside the icon on each row so the channel
        // is identifiable even at a glance without colour cues (a11y).
        channelLabel(ch) {
            return ({
                email: 'Email', whatsapp: 'WhatsApp', sms: 'SMS',
                telegram: 'Telegram', slack: 'Slack',
                chat: 'Chat', live_chat: 'Chat',
            })[ch] || (ch ? ch.charAt(0).toUpperCase() + ch.slice(1) : '');
        },

        init() {
            // Apply URL state FIRST so the initial conversation-list fetch
            // uses the right channel/folder. Otherwise the default fetch
            // starts first, grabs this.loading=true, and the re-fetch bails.
            const params = new URLSearchParams(window.location.search);
            const urlChannel = params.get('channel');
            const urlFolder  = params.get('folder');
            if (urlChannel) this.channel = urlChannel;
            if (urlFolder)  this.folder  = urlFolder;
            // Note: we deliberately do NOT restore a previously-active
            // conversation from ?c=<id> here. A reload should land the
            // user on the empty-state placeholder, not reopen whatever
            // thread they were reading last — that behaviour felt like
            // "the inbox is stuck" more often than "helpful resume".

            this.loadConversations();
            const notificationThread = Number(params.get('cid'));
            if (notificationThread > 0) this.selectConversation(notificationThread);
            this.loadSidebar();
            this.loadTags();
            this.loadTeam();
            this.loadEmailAccounts();
            this.pollTimer = setInterval(() => this.poll(), 5000);
            this.realtimeListener = () => this.poll();
            window.addEventListener('dahi:realtime', this.realtimeListener);
            this.startSyncLoop();
            this.syncBrowserTimezone();
            // Sync the (always-mounted) compose component's channel/lock
            // with the current URL filter on first paint, so the tab
            // strip is already correct before the user ever clicks
            // Compose. Tiny delay so Livewire has registered listeners.
            setTimeout(() => this._syncComposeChannel(), 50);
            const self = this;
            this.conversationListener = () => {
                this.composing = false;
                this.refreshConversations();
                this.loadSidebar();
                this.refreshActiveMessages();
            };
            window.addEventListener('conversations-updated', this.conversationListener);

            // Keep channel/folder in sync with the URL (via replaceState so
            // back/forward history isn't polluted) — handy for bookmarking
            // a filter, but intentionally no `c=<id>` anymore. A page reload
            // should always land on the placeholder, not re-open whatever
            // thread was previously active.
            const syncUrl = () => {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('c');
                    if (self.channel && self.channel !== 'all') url.searchParams.set('channel', self.channel); else url.searchParams.delete('channel');
                    if (self.folder && self.folder !== 'inbox') url.searchParams.set('folder', self.folder); else url.searchParams.delete('folder');
                    window.history.replaceState({}, '', url.toString());
                } catch(e) {}
            };
            this.$watch('channel', syncUrl);
            this.$watch('folder', syncUrl);
            // Clean any stale ?c= that might be in the URL from an older
            // session (pre this change) so the user doesn't see it linger.
            syncUrl();
        },

        // Detect the browser's IANA timezone and persist it to the user's
        // profile via /inbox/api/set-timezone. From that point on all server
        // code just reads $user->timezone — no cookies, no Livewire property
        // gymnastics. Console-logs at each step so it's easy to debug.
        syncBrowserTimezone() {
            let tz = null;
            try {
                tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            } catch (e) {
                console.warn('[InboxTZ] could not detect timezone', e);
                return;
            }
            if (!tz) {
                console.warn('[InboxTZ] browser returned empty tz');
                return;
            }

            // Skip if we've already persisted this same tz this session.
            const cached = sessionStorage.getItem('persisted_user_tz');
            if (cached === tz) {
                console.log('[InboxTZ] already persisted this session:', tz);
                return;
            }

            console.log('[InboxTZ] detected browser tz=' + tz + ', saving to profile');

            fetch(baseUrl + '/inbox/api/set-timezone', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    ...headers,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ timezone: tz }),
            })
            .then(async r => {
                // Parse JSON even on non-2xx so we can show the server's
                // actual error message instead of just "null".
                let body = null;
                try { body = await r.json(); } catch (e) {}
                return { ok: r.ok, status: r.status, body };
            })
            .then(({ ok, status, body }) => {
                if (ok && body && body.ok) {
                    sessionStorage.setItem('persisted_user_tz', tz);
                    console.log('[InboxTZ] saved', body);
                } else {
                    console.warn('[InboxTZ] save failed status=' + status, body);
                }
            })
            .catch(err => console.error('[InboxTZ] save error', err));
        },

        // Pulls new Gmail/IMAP/Outlook messages via /inbox/api/sync. While
        // the backfill cursor reports has_more=true we poll fast (~800ms);
        // once caught up we drop to a short idle interval (10s) so new
        // incoming mail shows up quickly. The loop runs for as long as
        // the tab is open — no "stop after N empty calls" cutoff, since
        // that made users think sync had died when they came back to a
        // long-idle tab.
        startSyncLoop() {
            this._syncStopped = false;

            const self = this;
            let callCount = 0;
            const IDLE_MS = 10000; // 10s between empty polls

            async function fetchBatch() {
                if (self._syncStopped) return;
                callCount++;
                let nextDelay = IDLE_MS;
                try {
                    const r = await fetch(baseUrl + '/inbox/api/sync', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers,
                    });
                    if (!r.ok) {
                        console.warn('[InboxSync] status=' + r.status + ' — retrying in 5s');
                        if (!self._syncStopped) self._syncTimer = setTimeout(fetchBatch, 5000);
                        return;
                    }
                    const d = await r.json();
                    if (self._syncStopped) return;
                    console.log('[InboxSync] call #' + callCount + ' synced=' + d.synced + ' has_more=' + d.has_more);

                    if (d.synced > 0) {
                        // New messages arrived — refresh the visible list + sidebar counts.
                        self.poll();
                    }

                    nextDelay = d.has_more ? 800 : IDLE_MS;
                } catch (e) {
                    console.error('[InboxSync] fetch error', e);
                    nextDelay = 5000;
                }

                console.log('[InboxSync] next call in ' + nextDelay + 'ms');
                if (!self._syncStopped) self._syncTimer = setTimeout(fetchBatch, nextDelay);
            }

            console.log('[InboxSync] loop starting → ' + baseUrl + '/inbox/api/sync');
            this._syncTimer = setTimeout(fetchBatch, 100);
        },

        destroy() {
            this._syncStopped = true;
            clearTimeout(this._syncTimer);
            window.removeEventListener('conversations-updated', this.conversationListener);
            clearInterval(this.pollTimer);
            window.removeEventListener('dahi:realtime', this.realtimeListener);
        },
        async loadConversations(append=false, silent=false) {
            if (silent && (this.loading || this._listBusy)) return;
            const version = ++this._listVersion;
            this._listBusy = true;
            if(!silent) this.loading = true;
            try {
                const p = new URLSearchParams({page:this.page,folder:this.folder,channel:this.channel,sort:this.sortBy,search:this.search,tag:this.filterTag,assignee:this.filterAssignee||'',account_id:this.filterAccountId||''});
                if (silent) p.set('through_page', this.page);
                const r = await fetch(baseUrl+'/inbox/api/conversations?'+p, {headers});
                if (!r.ok) return;
                const j = await r.json();
                if (version !== this._listVersion) return;
                this.conversations = append ? [...this.conversations,...j.data] : j.data;
                this.hasMore = j.has_more;
                if(this.conversations.length) this.lastId = Math.max(...this.conversations.map(c=>c.id));
            } catch(e) { console.error('Load failed:',e); }
            finally { if (version === this._listVersion) { this.loading = false; this._listBusy = false; } }
        },

        loadMore() { if(!this.hasMore||this.loading) return; this.page++; this.loadConversations(true); },

        async selectConversation(id) {
            this.composerOpen = false;
            if(this.activeId===id) return;
            this.activeId = id;
            // Expose as a window-level fallback so the Livewire ReplyComposer
            // send button (which can't see Alpine state directly) can always
            // recover the conversation id even if the Livewire listener
            // missed a dispatch.
            try { window.__activeConversationId = id; } catch(e) {}
            this.composing = false;
            // CRITICAL: wipe any messages from the previous conversation BEFORE
            // we start the new fetch. Otherwise a racing refreshActiveMessages
            // poll (fired on a 5s tick for the old conversation id) could
            // merge old-conversation bubbles into the new thread — that's how
            // WhatsApp messages from Kapil's convo were bleeding into Tony's
            // email thread.
            this.activeMessages = [];
            this.activeConversation = null;
            this.msgOffset = 0;
            this.hasMoreMessages = false;
            this.totalMessages = 0;
            // Clear AI Assistant state when switching threads — each
            // thread gets its own summary + chat session, never reuse.
            this.aiPanel = false;
            this.aiSummary = null;
            this.summarizingThread = false;
            this.aiMessages = [];
            this.aiInput = '';
            this.aiChatLoading = false;
            const c = this.conversations.find(x=>x.id===id);
            if(c) c.is_unread = false;
            try {
                // Fetch messages data
                // Fetch the full thread in one call (API caps at 500). For
                // very long conversations with more than 500 messages we loop
                // below until has_more is false, so the user never has to
                // click "Load more".
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+id+'/messages?limit=500&offset=0', {headers});
                // A 404 means the conversation was deleted / doesn't belong
                // to this workspace anymore. Treat the URL as stale: clear
                // all state so the detail pane shows the empty "Select a
                // conversation" placeholder instead of the zombie view.
                if (!r.ok) {
                    this.activeId = null;
                    this.activeConversation = null;
                    this.activeMessages = [];
                    window.__activeConversationId = null;
                    return;
                }
                const j = await r.json();
                if (j && j.error) {
                    this.activeId = null;
                    this.activeConversation = null;
                    this.activeMessages = [];
                    window.__activeConversationId = null;
                    return;
                }

                this.activeConversation = j.conversation;
                // Sort ascending by id so the oldest message appears at the
                // top and the newest at the bottom, regardless of the order
                // the API returns them in.
                this.activeMessages = [...j.messages].sort((a,b) => a.id - b.id);
                this.totalMessages = j.total_messages;
                this.hasMoreMessages = j.has_more;
                this.msgOffset = j.messages.length;

                // Auto-drain any remaining pages so the whole thread loads
                // without the user clicking "Load more". Capped at 20 extra
                // pages (10k messages) to avoid infinite loops.
                let guard = 20;
                while (this.hasMoreMessages && guard-- > 0) {
                    const rN = await fetch(baseUrl+'/inbox/api/conversations/'+id+'/messages?limit=500&offset='+this.msgOffset, {headers});
                    const jN = await rN.json();
                    if (!jN.messages || !jN.messages.length) break;
                    const byId = new Map();
                    [...this.activeMessages, ...jN.messages].forEach(m => byId.set(m.id, m));
                    this.activeMessages = [...byId.values()].sort((a,b) => a.id - b.id);
                    this.hasMoreMessages = jN.has_more;
                    this.msgOffset += jN.messages.length;
                }
                Livewire.dispatch('conversation-selected', {conversationId:id});

                // Belt-and-braces: also directly set conversationId on any
                // reply-composer Livewire instance in the DOM, in case the
                // event payload binding misses the named parameter.
                try {
                    document.querySelectorAll('[wire\\:id]').forEach(el => {
                        const wid = el.getAttribute('wire:id');
                        const comp = window.Livewire && Livewire.find ? Livewire.find(wid) : null;
                        if (!comp) return;
                        // In Livewire 3 `comp.name` can be a function/getter on
                        // some component shapes — calling .indexOf() on a
                        // non-string blew up the inbox ("name.indexOf is not
                        // a function"). Coerce to string + only match on
                        // actual strings.
                        let name = comp.name || (comp.__instance && comp.__instance.name) || '';
                        if (typeof name !== 'string') {
                            name = String(name && name.toString ? name.toString() : '');
                        }
                        if (name && name.indexOf('reply-composer') !== -1) {
                            comp.set('conversationId', id, false);
                        }
                    });
                } catch(err) { console.warn('reply-composer direct set failed:', err); }
            } catch(e) { console.error('Messages failed:',e); }
            finally { this.loadingMessages = false; }
        },

        /**
         * Open the AI Assistant panel and (if not already loaded) fetch
         * the initial 2-line summary of the thread. Re-opening a panel
         * we already loaded is instant — we keep the summary + chat
         * messages around until the user switches conversations.
         */
        async openAiPanel() {
            if (!this.activeId) return;
            this.aiPanel = true;
            // Already loaded — just reopen.
            if (this.aiSummary || this.summarizingThread) return;
            this.summarizingThread = true;
            try {
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+this.activeId+'/summarize', {
                    method: 'POST',
                    headers: {...headers, 'Content-Type': 'application/json'},
                });
                const j = await r.json();
                if (j.ok && j.summary) {
                    this.aiSummary = j.summary;
                } else {
                    this.aiSummary = (j.error || 'AI Recap failed.');
                }
            } catch (e) {
                console.error('Summarize failed:', e);
                this.aiSummary = 'AI Recap failed. Check your connection.';
            } finally {
                this.summarizingThread = false;
                // Auto-scroll the panel body to bottom on first load
                this.$nextTick(() => this._scrollAiPanel());
            }
        },

        closeAiPanel() { this.aiPanel = false; },

        /**
         * Re-run the initial summary (e.g., after new messages arrived).
         * Doesn't touch the chat history below.
         */
        async refreshAiSummary() {
            if (!this.activeId || this.summarizingThread) return;
            this.summarizingThread = true;
            this.aiSummary = null;
            try {
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+this.activeId+'/summarize', {
                    method: 'POST',
                    headers: {...headers, 'Content-Type': 'application/json'},
                });
                const j = await r.json();
                this.aiSummary = j.ok && j.summary ? j.summary : (j.error || 'AI Recap failed.');
            } catch (e) {
                this.aiSummary = 'AI Recap failed. Check your connection.';
            } finally {
                this.summarizingThread = false;
            }
        },

        /**
         * Send a follow-up question to the AI Assistant. The full thread
         * + prior turns of this chat session are sent every time so the
         * model has full context. Renders user bubble + assistant bubble
         * (with shimmer placeholder while waiting).
         */
        async sendAiChat(promptText) {
            const text = (promptText !== undefined ? promptText : this.aiInput).trim();
            if (!text || !this.activeId || this.aiChatLoading) return;
            this.aiInput = '';
            this.aiMessages.push({role:'user', content:text});
            this.aiChatLoading = true;
            // Pending assistant placeholder (will be replaced on response)
            const pendingIdx = this.aiMessages.push({role:'assistant', content:'', pending:true}) - 1;
            this.$nextTick(() => this._scrollAiPanel());
            try {
                // Send only the prior history (drop the placeholder we just pushed)
                const history = this.aiMessages
                    .slice(0, pendingIdx)
                    .map(m => ({role:m.role, content:m.content}));
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+this.activeId+'/ai-chat', {
                    method: 'POST',
                    headers: {...headers, 'Content-Type': 'application/json'},
                    body: JSON.stringify({prompt:text, history}),
                });
                const j = await r.json();
                if (j.ok && j.reply) {
                    this.aiMessages[pendingIdx] = {role:'assistant', content:j.reply};
                } else {
                    this.aiMessages[pendingIdx] = {role:'assistant', content:(j.error||'I could not answer that.'), error:true};
                }
            } catch (e) {
                console.error('aiChat failed:', e);
                this.aiMessages[pendingIdx] = {role:'assistant', content:'AI Assistant failed. Check your connection.', error:true};
            } finally {
                this.aiChatLoading = false;
                this.$nextTick(() => this._scrollAiPanel());
            }
        },

        clearAiChat() { this.aiMessages = []; },

        _scrollAiPanel() {
            const el = this.$refs.aiPanelBody;
            if (el) el.scrollTop = el.scrollHeight;
        },

        async loadMoreMessages() {
            if(!this.hasMoreMessages || this.loadingMoreMessages) return;
            this.loadingMoreMessages = true;
            try {
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+this.activeId+'/messages?limit=1&offset='+this.msgOffset, {headers});
                const j = await r.json();
                // Merge + de-dupe by id, then sort ascending so older messages
                // slot in at the TOP and the display stays chronological.
                const byId = new Map();
                [...this.activeMessages, ...j.messages].forEach(m => byId.set(m.id, m));
                this.activeMessages = [...byId.values()].sort((a,b) => a.id - b.id);
                this.hasMoreMessages = j.has_more;
                this.msgOffset += j.messages.length;
            } catch(e) { console.error('Load more messages failed:',e); }
            finally { this.loadingMoreMessages = false; }
        },

        refreshConversations() { this.page=1; this.loadConversations(); },

        // Keep the (already-mounted) Livewire ComposeEmail component's
        // channel + lock in sync with whichever channel filter the user
        // is on. Called proactively on init + every setChannel/setFolder
        // so that by the time the user actually clicks "Compose" the
        // component's tab strip has already re-rendered to match — no
        // flicker of the full multi-tab UI before it collapses down.
        _syncComposeChannel() {
            const channelMap = {
                email: 'email', whatsapp: 'whatsapp', sms: 'sms',
                telegram: 'telegram', slack: 'slack'
            };
            const preferred = channelMap[this.channel] || 'email';
            const lock = !!channelMap[this.channel];
            try {
                Livewire.dispatch('compose-set-channel', {channel: preferred, lock: lock});
            } catch(e) { console.warn('compose-set-channel dispatch failed:', e); }
        },

        // When the user clicks "Compose", the composer is revealed. The
        // channel/lock state was already pushed to the Livewire component
        // by _syncComposeChannel() on the last filter change, so there's
        // no flicker — we just flip the x-show flag. We still dispatch
        // once more here as a safety net in case the sync was missed
        // (e.g. Livewire not ready at init).
        openCompose() {
            this.showQuickReplies = false;
            this._syncComposeChannel();
            this.composing = true;
        },

        // Open the Quick Replies panel inline (same container as Compose).
        // Mutually exclusive with composing so the panes can't overlap.
        openQuickReplies() {
            this.composing = false;
            this.showQuickReplies = true;
        },

        async poll() {
            if (document.hidden || this.loading || this._listBusy) return;
            // Existing-thread replies, read state and delivery changes matter too.
            await Promise.all([this.loadConversations(false, true), this.loadSidebar(), this.refreshActiveMessages()]);
        },

        async refreshActiveMessages() {
            // Silent: we deliberately do NOT set this.loadingMessages here so
            // the big center spinner never shows on the 5-second background
            // poll. If the view is already showing its own loader (initial
            // open), let that finish first.
            if (!this.activeId || this.loadingMessages) return;
            // Snapshot the id we're refreshing for — if the user switches
            // conversations mid-flight, we'll notice on re-check below and
            // drop the result instead of merging stale bubbles into a fresh
            // thread.
            const refreshingFor = this.activeId;
            try {
                const r = await fetch(baseUrl+'/inbox/api/conversations/'+refreshingFor+'/messages?limit=500&offset=0', {headers});
                if (!r.ok) return;
                const j = await r.json();
                if (!j.messages) return;
                // Race guard: the user may have clicked a different
                // conversation between the fetch starting and it returning.
                // If so, discard — don't pollute the now-active thread with
                // the old one's messages.
                if (this.activeId !== refreshingFor) return;

                // Extra safety net: scope the fetched bubbles to our active
                // conversation only. The server should already do this, but
                // guarding here protects against any future cross-wire.
                const scoped = j.messages.filter(m => !m.conversation_id || m.conversation_id === refreshingFor);

                const byId = new Map();
                // Existing activeMessages from the same conversation stay;
                // anything without a matching conversation_id is dropped too.
                this.activeMessages
                    .filter(m => !m.conversation_id || m.conversation_id === refreshingFor)
                    .forEach(m => byId.set(m.id, m));
                scoped.forEach(m => byId.set(m.id, m));
                this.activeMessages = [...byId.values()].sort((a,b) => a.id - b.id);
                this.totalMessages = j.total_messages;
                this.hasMoreMessages = j.has_more;
            } catch(e) { console.warn('refreshActiveMessages failed:', e); }
        },

        async loadSidebar() { try { const r = await fetch(baseUrl+'/inbox/api/sidebar',{headers}); this.sidebarCounts = await r.json(); } catch(e){} },
        async loadTags() { try { const r = await fetch(baseUrl+'/inbox/api/tags',{headers}); this.tags = await r.json(); } catch(e){} },
        async loadTeam() { try { const r = await fetch(baseUrl+'/inbox/api/team',{headers}); this.teamMembers = await r.json(); } catch(e){} },
        async loadEmailAccounts() { try { const r = await fetch(baseUrl+'/inbox/api/accounts',{headers}); this.emailAccounts = await r.json(); } catch(e){} },

        // Switching folder/channel should feel like navigating to a new
        // screen — any open thread closes back to the placeholder. Without
        // this the right pane keeps rendering the previously-selected
        // conversation even though it's no longer in the visible list,
        // which is confusing (and sometimes the row is hidden by the new
        // filter, so clicking anywhere to dismiss it requires re-selecting
        // something first).
        _clearActiveThread() {
            this.activeId = null;
            this.activeConversation = null;
            this.activeMessages = [];
            try { window.__activeConversationId = null; } catch(e) {}
        },
        setFolder(f) { this._clearActiveThread(); this.folder=f; this.channel='all'; this.page=1; this.conversations=[]; this.loadConversations(); this._syncComposeChannel(); },
        setChannel(ch) { this._clearActiveThread(); this.channel=ch; this.folder='inbox'; this.page=1; this.conversations=[]; this.loadConversations(); this._syncComposeChannel(); },
        setSortBy(s) { this.sortBy=s; this.page=1; this.conversations=[]; this.loadConversations(); },
        setFilterTag(t) { this.filterTag=t; this.page=1; this.conversations=[]; this.loadConversations(); },
        setFilterAssignee(id) { this.filterAssignee=this.filterAssignee===id?null:id; this.page=1; this.conversations=[]; this.loadConversations(); },
        setFilterAccount(id) { this.filterAccountId=id; this.accountDrop=false; this.page=1; this.conversations=[]; this.loadConversations(); },
        get activeAccount() { return this.filterAccountId ? this.emailAccounts.find(a => a.id === this.filterAccountId) : null; },

        toggleSelect(id) { const i=this.selectedIds.indexOf(id); i===-1?this.selectedIds.push(id):this.selectedIds.splice(i,1); },
        toggleSelectAll() { if(this.selectAll){this.selectedIds=[];this.selectAll=false;}else{this.selectedIds=this.conversations.map(c=>c.id);this.selectAll=true;} },

        async doAction(convId, action, data={}) {
            try {
                await fetch(baseUrl+'/inbox/api/conversations/'+convId+'/action', {method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({action,...data})});
                if(['close','reopen','spam','trash','snooze'].includes(action)) {
                    // Blow away ALL cached conversation state, not just the
                    // id/header, so the message bubbles, loaders, reply box,
                    // and right-panel widgets all reset. Without clearing
                    // activeMessages the detail pane kept rendering the old
                    // thread even after trash/close/spam.
                    this.activeId = null;
                    this.activeConversation = null;
                    this.activeMessages = [];
                    this.totalMessages = 0;
                    this.hasMoreMessages = false;
                    this.msgOffset = 0;
                    window.__activeConversationId = null;
                    this.refreshConversations();
                    this.loadSidebar();
                }
                else if(action==='star'&&this.activeConversation) { this.activeConversation.is_starred=!this.activeConversation.is_starred; }
                else if(action==='priority' && this.activeConversation) {
                    // Optimistic update — flip both the right-pane
                    // sidebar (activeConversation) and the matching row
                    // in the conversations list immediately so the user
                    // doesn't have to reload to see the new priority.
                    // Server already wrote it; we're just keeping the
                    // local state in sync.
                    const newP = data.value;
                    this.activeConversation.priority = newP;
                    const row = this.conversations.find(c => c.id === convId);
                    if (row) row.priority = newP;
                }
                else if(action==='assign' && this.activeConversation) {
                    // Same idea for assignee — refresh the local row
                    // so the right-pane "Assigned to" line updates
                    // without waiting for a re-fetch.
                    const memberId = data.user_id;
                    const member = this.teamMembers.find(m => m.id === memberId);
                    this.activeConversation.assigned_to = member ? member.name : null;
                    const row2 = this.conversations.find(c => c.id === convId);
                    if (row2) {
                        row2.assigned_to_initials = member ? member.initials : null;
                        row2.assigned_to_name = member ? member.name : null;
                    }
                }
                else if(['add_tag', 'remove_tag'].includes(action)) {
                    // Tag mutations need server data to know the
                    // current full tag list — keep the existing
                    // re-fetch behaviour for these.
                    this.selectConversation(convId);
                }
                else { this.selectConversation(convId); }
            } catch(e) { console.error('Action failed:',e); }
        },

        async doBulkAction(action) {
            try {
                await fetch(baseUrl+'/inbox/api/bulk', {method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({ids:this.selectedIds,action})});
                this.selectedIds=[]; this.selectAll=false; this.refreshConversations(); this.loadSidebar();
            } catch(e) { console.error('Bulk failed:',e); }
        },
    };
}
</script>
</x-layouts.inbox>

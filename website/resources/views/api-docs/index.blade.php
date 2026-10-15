<x-layouts.guest title="API Documentation">
<div x-data="apiDocs()" class="min-h-screen bg-surface">

    {{-- Top bar --}}
    <div class="border-b border-border/50 bg-surface-2/80 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <span class="brand-logo text-xl tracking-tight">{{ config('app.name') }}</span>
                <span class="text-lg font-semibold text-ink">API Documentation</span>
            </a>
            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-xs font-medium px-2 py-1 bg-brand/10 text-brand rounded-md">v1</span>
                @auth
                    <a href="{{ url('/settings/account') }}" wire:navigate class="text-sm text-muted hover:text-ink transition-colors">Settings</a>
                @else
                    <a href="{{ url('/login') }}" class="text-sm text-muted hover:text-ink transition-colors">Sign In</a>
                @endauth
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto flex">
        {{-- Sidebar --}}
        <aside class="hidden lg:block w-60 flex-shrink-0 border-r border-border/50 sticky top-[57px] h-[calc(100vh-57px)] overflow-y-auto py-6 px-4">
            {{-- Search --}}
            <div class="mb-4">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                    <input x-model="searchQuery" type="text" placeholder="Search endpoints..."
                           class="w-full pl-8 pr-3 py-1.5 text-xs border border-border rounded-lg bg-surface text-ink placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-brand/50">
                </div>
            </div>

            <nav class="space-y-1 text-sm">
                <a href="#quick-start" @click.prevent="scrollTo('quick-start')" class="block px-3 py-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors">Quick Start</a>
                <a href="#authentication" @click.prevent="scrollTo('authentication')" class="block px-3 py-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors">Authentication</a>
                <a href="#rate-limiting" @click.prevent="scrollTo('rate-limiting')" class="block px-3 py-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors">Rate Limiting</a>
                <a href="#errors" @click.prevent="scrollTo('errors')" class="block px-3 py-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors">Errors</a>

                <div class="pt-3 pb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-muted/60">Endpoints</div>

                <template x-for="section in filteredSections" :key="section.id">
                    <a :href="'#' + section.id" @click.prevent="scrollTo(section.id)"
                       class="block px-3 py-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors"
                       x-text="section.title"></a>
                </template>
            </nav>
        </aside>

        {{-- Mobile sidebar toggle --}}
        <div class="lg:hidden fixed bottom-4 right-4 z-40">
            <button @click="mobileNav = !mobileNav" class="w-12 h-12 bg-brand text-white rounded-full shadow-lg flex items-center justify-center">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
        </div>

        {{-- Mobile nav drawer --}}
        <div x-show="mobileNav" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="lg:hidden fixed inset-0 z-50 bg-black/50" @click="mobileNav = false" x-cloak>
            <div class="absolute right-0 top-0 h-full w-64 bg-surface-2 border-l border-border p-4 overflow-y-auto" @click.stop>
                <div class="flex justify-between items-center mb-4">
                    <span class="font-semibold text-ink">Navigation</span>
                    <button @click="mobileNav = false" class="p-1 text-muted hover:text-ink"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <nav class="space-y-1 text-sm">
                    <a href="#quick-start" @click="scrollTo('quick-start'); mobileNav = false" class="block px-3 py-2 rounded-md text-muted hover:text-ink hover:bg-surface-3">Quick Start</a>
                    <a href="#authentication" @click="scrollTo('authentication'); mobileNav = false" class="block px-3 py-2 rounded-md text-muted hover:text-ink hover:bg-surface-3">Authentication</a>
                    <a href="#rate-limiting" @click="scrollTo('rate-limiting'); mobileNav = false" class="block px-3 py-2 rounded-md text-muted hover:text-ink hover:bg-surface-3">Rate Limiting</a>
                    <a href="#errors" @click="scrollTo('errors'); mobileNav = false" class="block px-3 py-2 rounded-md text-muted hover:text-ink hover:bg-surface-3">Errors</a>
                    <div class="pt-3 pb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-muted/60">Endpoints</div>
                    <template x-for="section in filteredSections" :key="section.id">
                        <a :href="'#' + section.id" @click="scrollTo(section.id); mobileNav = false"
                           class="block px-3 py-2 rounded-md text-muted hover:text-ink hover:bg-surface-3"
                           x-text="section.title"></a>
                    </template>
                </nav>
            </div>
        </div>

        {{-- Main content --}}
        <main class="flex-1 min-w-0 py-8 px-4 sm:px-8 lg:px-12 max-w-4xl">

            {{-- ═══════════════════════════════════════════════════════════════
                 QUICK START
                 ═══════════════════════════════════════════════════════════════ --}}
            <section id="quick-start" class="mb-16">
                <h1 class="text-3xl font-bold text-ink mb-2">{{ config('app.name') }} API</h1>
                <p class="text-muted text-base mb-8">Build powerful integrations with the {{ config('app.name') }} platform. Manage contacts, conversations, campaigns, and more programmatically.</p>

                <div class="panel p-6 space-y-5">
                    <h2 class="text-lg font-semibold text-ink">Quick Start</h2>

                    <div class="space-y-4 text-sm">
                        <div>
                            <h3 class="font-medium text-ink mb-1">1. Generate an API Token</h3>
                            <p class="text-muted">Navigate to <strong>Settings &rarr; Account</strong> to create a personal API token. Select the scopes (permissions) your integration needs.</p>
                        </div>
                        <div>
                            <h3 class="font-medium text-ink mb-1">2. Base URL</h3>
                            <div x-data="{ copied: false }" class="relative">
                                <pre class="bg-surface-3 rounded-lg p-3 text-xs font-mono text-ink overflow-x-auto">{{ url('/api/v1') }}</pre>
                                <button @click="navigator.clipboard.writeText('{{ url('/api/v1') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="absolute top-2 right-2 p-1.5 rounded-md text-muted hover:text-ink hover:bg-surface transition-colors" :title="copied ? 'Copied!' : 'Copy'">
                                    <svg x-show="!copied" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-medium text-ink mb-1">3. Make Your First Request</h3>
                            <div x-data="{ copied: false }" class="relative">
                                <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono overflow-x-auto"><span class="text-emerald-400">curl</span> -X GET {{ url('/api/v1/contacts') }} \
  -H <span class="text-amber-300">"Authorization: Bearer YOUR_API_TOKEN"</span> \
  -H <span class="text-amber-300">"Accept: application/json"</span></pre>
                                <button @click="navigator.clipboard.writeText(`curl -X GET {{ url('/api/v1/contacts') }} \\\n  -H 'Authorization: Bearer YOUR_API_TOKEN' \\\n  -H 'Accept: application/json'`); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="absolute top-2 right-2 p-1.5 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                                    <svg x-show="!copied" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    <svg x-show="copied" x-cloak class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════════════
                 AUTHENTICATION
                 ═══════════════════════════════════════════════════════════════ --}}
            <section id="authentication" class="mb-16">
                <h2 class="text-2xl font-bold text-ink mb-4">Authentication</h2>
                <div class="prose prose-sm max-w-none text-muted space-y-3">
                    <p>All API requests require a valid Bearer token sent in the <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">Authorization</code> header.</p>
                    <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono">Authorization: Bearer <span class="text-amber-300">your-api-token</span></pre>
                    <p>Tokens are scoped. When you create a token, you select which API sections it can access:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 not-prose">
                        @foreach(['contacts', 'conversations', 'campaigns', 'workflows', 'knowledge-base', 'ai', 'analytics', 'tags', 'canned-responses'] as $scope)
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-mono bg-surface-3 rounded-md text-ink">{{ $scope }}</span>
                        @endforeach
                    </div>
                    <p>Requests to endpoints outside the token's scopes will receive a <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">403 Forbidden</code> response.</p>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════════════
                 RATE LIMITING
                 ═══════════════════════════════════════════════════════════════ --}}
            <section id="rate-limiting" class="mb-16">
                <h2 class="text-2xl font-bold text-ink mb-4">Rate Limiting</h2>
                <div class="prose prose-sm max-w-none text-muted space-y-3">
                    <p>API requests are rate-limited to protect service stability. Default limits:</p>
                    <div class="not-prose overflow-x-auto">
                        <table class="w-full text-sm border border-border rounded-lg overflow-hidden">
                            <thead><tr class="bg-surface-3"><th class="px-4 py-2 text-left font-medium text-ink">Scope</th><th class="px-4 py-2 text-left font-medium text-ink">Limit</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                <tr><td class="px-4 py-2 text-muted">General API</td><td class="px-4 py-2 font-mono text-ink">60 requests/minute</td></tr>
                                <tr><td class="px-4 py-2 text-muted">Import/Export</td><td class="px-4 py-2 font-mono text-ink">5 requests/minute</td></tr>
                                <tr><td class="px-4 py-2 text-muted">Campaign Send</td><td class="px-4 py-2 font-mono text-ink">5 requests/minute</td></tr>
                                <tr><td class="px-4 py-2 text-muted">AI Generate Reply</td><td class="px-4 py-2 font-mono text-ink">10 requests/minute</td></tr>
                                <tr><td class="px-4 py-2 text-muted">AI Analyze Sentiment</td><td class="px-4 py-2 font-mono text-ink">20 requests/minute</td></tr>
                                <tr><td class="px-4 py-2 text-muted">KB Scrape</td><td class="px-4 py-2 font-mono text-ink">3 requests/minute</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Rate limit headers are included in every response:</p>
                    <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono">X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
Retry-After: 42  <span class="text-gray-500">// only when rate limited (429)</span></pre>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════════════
                 ERRORS
                 ═══════════════════════════════════════════════════════════════ --}}
            <section id="errors" class="mb-16">
                <h2 class="text-2xl font-bold text-ink mb-4">Errors</h2>
                <div class="prose prose-sm max-w-none text-muted space-y-3">
                    <p>The API uses standard HTTP status codes. Errors return a JSON body with details:</p>
                    <div class="not-prose overflow-x-auto">
                        <table class="w-full text-sm border border-border rounded-lg overflow-hidden">
                            <thead><tr class="bg-surface-3"><th class="px-4 py-2 text-left font-medium text-ink">Code</th><th class="px-4 py-2 text-left font-medium text-ink">Meaning</th></tr></thead>
                            <tbody class="divide-y divide-border">
                                <tr><td class="px-4 py-2 font-mono text-ink">400</td><td class="px-4 py-2 text-muted">Bad Request - Invalid parameters</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">401</td><td class="px-4 py-2 text-muted">Unauthorized - Invalid or missing token</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">403</td><td class="px-4 py-2 text-muted">Forbidden - Token lacks required scope</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">404</td><td class="px-4 py-2 text-muted">Not Found - Resource does not exist</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">422</td><td class="px-4 py-2 text-muted">Validation Error - Check the errors object</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">429</td><td class="px-4 py-2 text-muted">Too Many Requests - Rate limit exceeded</td></tr>
                                <tr><td class="px-4 py-2 font-mono text-ink">500</td><td class="px-4 py-2 text-muted">Server Error - Unexpected failure</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono">{
  <span class="text-blue-300">"message"</span>: <span class="text-amber-300">"Contact not found."</span>
}

<span class="text-gray-500">// Validation errors (422)</span>
{
  <span class="text-blue-300">"errors"</span>: {
    <span class="text-blue-300">"email"</span>: [<span class="text-amber-300">"The email field is required."</span>]
  }
}</pre>
                </div>
            </section>

            {{-- ═══════════════════════════════════════════════════════════════
                 ENDPOINT SECTIONS
                 ═══════════════════════════════════════════════════════════════ --}}

            {{-- ── CONTACTS ─────────────────────────────────────────────── --}}
            <section id="contacts" class="mb-16 endpoint-section" x-show="matchesSearch('contacts')">
                <h2 class="text-2xl font-bold text-ink mb-1">Contacts</h2>
                <p class="text-sm text-muted mb-6">Manage your contact database. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">contacts</code> scope.</p>

                {{-- List Contacts --}}
                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/contacts',
                    'description' => 'List contacts with search, filters, and pagination.',
                    'params' => [
                        ['name' => 'search', 'type' => 'string', 'required' => false, 'desc' => 'Search by name, email, or company'],
                        ['name' => 'status', 'type' => 'string', 'required' => false, 'desc' => 'Filter: active, inactive, unsubscribed'],
                        ['name' => 'tag_id', 'type' => 'integer', 'required' => false, 'desc' => 'Filter by tag ID'],
                        ['name' => 'country', 'type' => 'string', 'required' => false, 'desc' => 'Filter by country'],
                        ['name' => 'min_score', 'type' => 'integer', 'required' => false, 'desc' => 'Minimum lead score (0-100)'],
                        ['name' => 'max_score', 'type' => 'integer', 'required' => false, 'desc' => 'Maximum lead score (0-100)'],
                        ['name' => 'sort_by', 'type' => 'string', 'required' => false, 'desc' => 'Sort field: first_name, last_name, email, company, lead_score, created_at, last_contacted_at'],
                        ['name' => 'sort_dir', 'type' => 'string', 'required' => false, 'desc' => 'asc or desc (default: desc)'],
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'desc' => 'Results per page (max 100, default 25)'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/contacts') . "?search=john&per_page=10' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "company": "Acme Inc",
      "lead_score": 75,
      "status": "active",
      "tags": [{"id": 1, "name": "VIP"}],
      "created_at": "2026-03-01T10:00:00Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 5, "per_page": 10, "total": 48 }
}',
                ])

                {{-- Create Contact --}}
                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/contacts',
                    'description' => 'Create a new contact.',
                    'params' => [
                        ['name' => 'first_name', 'type' => 'string', 'required' => true, 'desc' => 'Contact first name (max 255)'],
                        ['name' => 'last_name', 'type' => 'string', 'required' => false, 'desc' => 'Contact last name'],
                        ['name' => 'email', 'type' => 'string', 'required' => true, 'desc' => 'Valid email address'],
                        ['name' => 'phone', 'type' => 'string', 'required' => false, 'desc' => 'Phone number'],
                        ['name' => 'company', 'type' => 'string', 'required' => false, 'desc' => 'Company name'],
                        ['name' => 'job_title', 'type' => 'string', 'required' => false, 'desc' => 'Job title'],
                        ['name' => 'city', 'type' => 'string', 'required' => false, 'desc' => 'City'],
                        ['name' => 'country', 'type' => 'string', 'required' => false, 'desc' => 'Country'],
                        ['name' => 'lead_score', 'type' => 'integer', 'required' => false, 'desc' => 'Lead score (0-100)'],
                        ['name' => 'custom_fields', 'type' => 'object', 'required' => false, 'desc' => 'Custom field key-value pairs'],
                        ['name' => 'tag_ids', 'type' => 'array', 'required' => false, 'desc' => 'Array of tag IDs to assign'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/contacts') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"first_name\":\"Jane\",\"email\":\"jane@example.com\",\"company\":\"Acme\"}'",
                    'response' => '{
  "data": {
    "id": 42,
    "first_name": "Jane",
    "last_name": null,
    "email": "jane@example.com",
    "company": "Acme",
    "lead_score": 0,
    "status": "active",
    "tags": [],
    "created_at": "2026-03-22T14:30:00Z"
  }
}',
                ])

                {{-- Show Contact --}}
                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/contacts/{id}',
                    'description' => 'Retrieve a single contact by ID.',
                    'params' => [],
                    'curl' => "curl -X GET '" . url('/api/v1/contacts/42') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": {
    "id": 42,
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane@example.com",
    "conversations_count": 12,
    "deals_count": 3
  }
}',
                ])

                {{-- Update Contact --}}
                @include('api-docs._endpoint', [
                    'method' => 'PUT',
                    'path' => '/api/v1/contacts/{id}',
                    'description' => 'Update a contact. Only send fields you want to change.',
                    'params' => [
                        ['name' => 'first_name', 'type' => 'string', 'required' => false, 'desc' => 'Contact first name'],
                        ['name' => 'email', 'type' => 'string', 'required' => false, 'desc' => 'Valid email address'],
                        ['name' => 'status', 'type' => 'string', 'required' => false, 'desc' => 'active, inactive, or unsubscribed'],
                        ['name' => 'tag_ids', 'type' => 'array', 'required' => false, 'desc' => 'Replace all tags with these IDs'],
                    ],
                    'curl' => "curl -X PUT '" . url('/api/v1/contacts/42') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"lead_score\":90,\"status\":\"active\"}'",
                    'response' => '{
  "data": {
    "id": 42,
    "first_name": "Jane",
    "lead_score": 90,
    "status": "active"
  }
}',
                ])

                {{-- Delete Contact --}}
                @include('api-docs._endpoint', [
                    'method' => 'DELETE',
                    'path' => '/api/v1/contacts/{id}',
                    'description' => 'Soft-delete a contact.',
                    'params' => [],
                    'curl' => "curl -X DELETE '" . url('/api/v1/contacts/42') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'",
                    'response' => '204 No Content',
                ])

                {{-- Import Contacts --}}
                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/contacts/import',
                    'description' => 'Import contacts from a CSV file. Rate limited to 5 req/min.',
                    'params' => [
                        ['name' => 'file', 'type' => 'file', 'required' => true, 'desc' => 'CSV/XLSX file (max 10MB). Headers: first_name, last_name, email, phone, company, etc.'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/contacts/import') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -F 'file=@contacts.csv'",
                    'response' => '{
  "data": {
    "imported": 142,
    "skipped": 3,
    "errors": ["Row 15: invalid email \'not-an-email\'"]
  }
}',
                ])

                {{-- Export Contacts --}}
                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/contacts/export',
                    'description' => 'Export all contacts as a streamed CSV download. Rate limited to 5 req/min.',
                    'params' => [],
                    'curl' => "curl -X GET '" . url('/api/v1/contacts/export') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -o contacts.csv",
                    'response' => 'Binary CSV file stream',
                ])
            </section>

            {{-- ── CONVERSATIONS ─────────────────────────────────────────── --}}
            <section id="conversations" class="mb-16 endpoint-section" x-show="matchesSearch('conversations')">
                <h2 class="text-2xl font-bold text-ink mb-1">Conversations</h2>
                <p class="text-sm text-muted mb-6">Manage inbox conversations. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">conversations</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/conversations',
                    'description' => 'List conversations with filters and pagination.',
                    'params' => [
                        ['name' => 'status', 'type' => 'string', 'required' => false, 'desc' => 'Filter: open, closed, snoozed'],
                        ['name' => 'channel', 'type' => 'string', 'required' => false, 'desc' => 'Filter by channel: email, chat, whatsapp, sms'],
                        ['name' => 'assigned_to', 'type' => 'integer', 'required' => false, 'desc' => 'Filter by assigned user ID'],
                        ['name' => 'contact_id', 'type' => 'integer', 'required' => false, 'desc' => 'Filter by contact ID'],
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'desc' => 'Results per page (max 100)'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/conversations') . "?status=open' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {
      "id": 1,
      "subject": "Billing question",
      "status": "open",
      "channel": "email",
      "contact": {"id": 5, "email": "user@example.com"},
      "assigned_to": {"id": 2, "name": "Agent Smith"},
      "last_message_at": "2026-03-22T09:30:00Z"
    }
  ],
  "meta": {"current_page": 1, "total": 24}
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/conversations/{id}/reply',
                    'description' => 'Send a reply to a conversation.',
                    'params' => [
                        ['name' => 'body', 'type' => 'string', 'required' => true, 'desc' => 'Reply message body (HTML supported)'],
                        ['name' => 'internal', 'type' => 'boolean', 'required' => false, 'desc' => 'If true, add as internal note (not sent to contact)'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/conversations/1/reply') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"body\":\"Thanks for reaching out! We will look into this.\"}'",
                    'response' => '{
  "data": {
    "id": 145,
    "conversation_id": 1,
    "body": "Thanks for reaching out! We will look into this.",
    "type": "reply",
    "created_at": "2026-03-22T14:30:00Z"
  }
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/conversations/{id}/assign',
                    'description' => 'Assign a conversation to a team member.',
                    'params' => [
                        ['name' => 'user_id', 'type' => 'integer', 'required' => true, 'desc' => 'User ID to assign the conversation to'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/conversations/1/assign') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"user_id\":3}'",
                    'response' => '{
  "data": {"id": 1, "assigned_to": {"id": 3, "name": "Agent Jones"}}
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/conversations/{id}/close',
                    'description' => 'Close a conversation.',
                    'params' => [],
                    'curl' => "curl -X POST '" . url('/api/v1/conversations/1/close') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'",
                    'response' => '{
  "data": {"id": 1, "status": "closed"}
}',
                ])
            </section>

            {{-- ── CAMPAIGNS ─────────────────────────────────────────────── --}}
            <section id="campaigns" class="mb-16 endpoint-section" x-show="matchesSearch('campaigns')">
                <h2 class="text-2xl font-bold text-ink mb-1">Campaigns</h2>
                <p class="text-sm text-muted mb-6">Create and manage email campaigns. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">campaigns</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/campaigns',
                    'description' => 'List campaigns with filters.',
                    'params' => [
                        ['name' => 'status', 'type' => 'string', 'required' => false, 'desc' => 'Filter: draft, scheduled, sending, sent, paused'],
                        ['name' => 'type', 'type' => 'string', 'required' => false, 'desc' => 'Filter by campaign type'],
                        ['name' => 'search', 'type' => 'string', 'required' => false, 'desc' => 'Search by name or subject'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/campaigns') . "?status=sent' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {
      "id": 1,
      "name": "March Newsletter",
      "subject": "What is new in March",
      "status": "sent",
      "sent_count": 1250,
      "open_rate": 34.5,
      "click_rate": 8.2,
      "created_at": "2026-03-01T08:00:00Z"
    }
  ]
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/campaigns',
                    'description' => 'Create a new campaign (draft).',
                    'params' => [
                        ['name' => 'name', 'type' => 'string', 'required' => true, 'desc' => 'Campaign name'],
                        ['name' => 'subject', 'type' => 'string', 'required' => true, 'desc' => 'Email subject line'],
                        ['name' => 'html_body', 'type' => 'string', 'required' => true, 'desc' => 'HTML email body'],
                        ['name' => 'segment_id', 'type' => 'integer', 'required' => false, 'desc' => 'Segment ID to target'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/campaigns') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"name\":\"April Promo\",\"subject\":\"Special offer inside\",\"html_body\":\"<h1>Hello!</h1>\"}'",
                    'response' => '{
  "data": {"id": 15, "name": "April Promo", "status": "draft"}
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/campaigns/{id}/send',
                    'description' => 'Send or schedule a campaign. Rate limited to 5 req/min.',
                    'params' => [
                        ['name' => 'scheduled_at', 'type' => 'datetime', 'required' => false, 'desc' => 'ISO 8601 datetime to schedule (omit to send immediately)'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/campaigns/15/send') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json'",
                    'response' => '{
  "data": {"id": 15, "status": "sending"}
}',
                ])
            </section>

            {{-- ── WORKFLOWS ─────────────────────────────────────────────── --}}
            <section id="workflows" class="mb-16 endpoint-section" x-show="matchesSearch('workflows')">
                <h2 class="text-2xl font-bold text-ink mb-1">Workflows</h2>
                <p class="text-sm text-muted mb-6">Manage automation workflows. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">workflows</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/workflows',
                    'description' => 'List all workflows.',
                    'params' => [],
                    'curl' => "curl -X GET '" . url('/api/v1/workflows') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {"id": 1, "name": "Welcome Series", "status": "active", "trigger": "contact.created", "executions_count": 342}
  ]
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/workflows/{id}/activate',
                    'description' => 'Activate a paused workflow.',
                    'params' => [],
                    'curl' => "curl -X POST '" . url('/api/v1/workflows/1/activate') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'",
                    'response' => '{"data": {"id": 1, "status": "active"}}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/workflows/{id}/pause',
                    'description' => 'Pause an active workflow.',
                    'params' => [],
                    'curl' => "curl -X POST '" . url('/api/v1/workflows/1/pause') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'",
                    'response' => '{"data": {"id": 1, "status": "paused"}}',
                ])
            </section>

            {{-- ── KNOWLEDGE BASE ────────────────────────────────────────── --}}
            <section id="knowledge-base" class="mb-16 endpoint-section" x-show="matchesSearch('knowledge-base')">
                <h2 class="text-2xl font-bold text-ink mb-1">Knowledge Base</h2>
                <p class="text-sm text-muted mb-6">Manage KB documents for AI context. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">knowledge-base</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/knowledge-base',
                    'description' => 'List knowledge base documents.',
                    'params' => [
                        ['name' => 'search', 'type' => 'string', 'required' => false, 'desc' => 'Search document titles'],
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'desc' => 'Results per page'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/knowledge-base') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {"id": 1, "title": "Product FAQ", "type": "file", "file_size": 245760, "chunks_count": 24, "created_at": "2026-03-10T12:00:00Z"}
  ]
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/knowledge-base',
                    'description' => 'Upload a document to the knowledge base.',
                    'params' => [
                        ['name' => 'title', 'type' => 'string', 'required' => true, 'desc' => 'Document title'],
                        ['name' => 'file', 'type' => 'file', 'required' => true, 'desc' => 'PDF, DOCX, or TXT file'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/knowledge-base') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -F 'title=Return Policy' \\\n  -F 'file=@return-policy.pdf'",
                    'response' => '{
  "data": {"id": 5, "title": "Return Policy", "status": "processing"}
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/knowledge-base/scrape',
                    'description' => 'Scrape a website URL and add it to the knowledge base. Rate limited to 3 req/min.',
                    'params' => [
                        ['name' => 'url', 'type' => 'string', 'required' => true, 'desc' => 'URL to scrape'],
                        ['name' => 'title', 'type' => 'string', 'required' => false, 'desc' => 'Custom title (auto-detected if omitted)'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/knowledge-base/scrape') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"url\":\"https://example.com/help\"}'",
                    'response' => '{
  "data": {"id": 6, "title": "Help Center", "type": "url", "status": "processing"}
}',
                ])
            </section>

            {{-- ── AI ────────────────────────────────────────────────────── --}}
            <section id="ai" class="mb-16 endpoint-section" x-show="matchesSearch('ai')">
                <h2 class="text-2xl font-bold text-ink mb-1">AI</h2>
                <p class="text-sm text-muted mb-6">AI-powered features. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">ai</code> scope. Stricter rate limits apply.</p>

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/ai/generate-reply',
                    'description' => 'Generate an AI-powered reply for a conversation. Rate limited to 10 req/min.',
                    'params' => [
                        ['name' => 'conversation_id', 'type' => 'integer', 'required' => true, 'desc' => 'Conversation to generate a reply for'],
                        ['name' => 'tone', 'type' => 'string', 'required' => false, 'desc' => 'Reply tone: professional, friendly, concise'],
                        ['name' => 'instructions', 'type' => 'string', 'required' => false, 'desc' => 'Additional context for the AI'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/ai/generate-reply') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"conversation_id\":1,\"tone\":\"professional\"}'",
                    'response' => '{
  "data": {
    "reply": "Thank you for contacting us regarding your billing inquiry...",
    "tokens_used": 245,
    "model": "gpt-4"
  }
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/ai/analyze-sentiment',
                    'description' => 'Analyze the sentiment of a text or conversation. Rate limited to 20 req/min.',
                    'params' => [
                        ['name' => 'text', 'type' => 'string', 'required' => false, 'desc' => 'Text to analyze (provide this or conversation_id)'],
                        ['name' => 'conversation_id', 'type' => 'integer', 'required' => false, 'desc' => 'Conversation ID to analyze'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/ai/analyze-sentiment') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"text\":\"I am very frustrated with the late delivery\"}'",
                    'response' => '{
  "data": {
    "sentiment": "negative",
    "score": -0.78,
    "emotions": ["frustration", "disappointment"],
    "urgency": "high"
  }
}',
                ])
            </section>

            {{-- ── ANALYTICS ─────────────────────────────────────────────── --}}
            <section id="analytics" class="mb-16 endpoint-section" x-show="matchesSearch('analytics')">
                <h2 class="text-2xl font-bold text-ink mb-1">Analytics</h2>
                <p class="text-sm text-muted mb-6">Read-only analytics data. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">analytics</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/analytics/overview',
                    'description' => 'Get workspace analytics overview.',
                    'params' => [
                        ['name' => 'period', 'type' => 'string', 'required' => false, 'desc' => '7d, 30d, 90d (default: 30d)'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/analytics/overview') . "?period=30d' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": {
    "conversations": {"total": 524, "open": 38, "avg_resolution_hours": 4.2},
    "contacts": {"total": 12500, "new_this_period": 320},
    "campaigns": {"sent": 8, "avg_open_rate": 32.1, "avg_click_rate": 6.8},
    "ai": {"replies_generated": 186, "tokens_used": 45200}
  }
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/analytics/team',
                    'description' => 'Get team performance metrics.',
                    'params' => [
                        ['name' => 'period', 'type' => 'string', 'required' => false, 'desc' => '7d, 30d, 90d'],
                    ],
                    'curl' => "curl -X GET '" . url('/api/v1/analytics/team') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {"user_id": 2, "name": "Agent Smith", "conversations_handled": 142, "avg_response_minutes": 12, "satisfaction_score": 4.6}
  ]
}',
                ])
            </section>

            {{-- ── TAGS ──────────────────────────────────────────────────── --}}
            <section id="tags" class="mb-16 endpoint-section" x-show="matchesSearch('tags')">
                <h2 class="text-2xl font-bold text-ink mb-1">Tags</h2>
                <p class="text-sm text-muted mb-6">Manage workspace tags. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">tags</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/tags',
                    'description' => 'List all tags in the workspace.',
                    'params' => [],
                    'curl' => "curl -X GET '" . url('/api/v1/tags') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {"id": 1, "name": "VIP", "color": "#8B5CF6", "contacts_count": 42},
    {"id": 2, "name": "Lead", "color": "#3B82F6", "contacts_count": 198}
  ]
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/tags',
                    'description' => 'Create a new tag.',
                    'params' => [
                        ['name' => 'name', 'type' => 'string', 'required' => true, 'desc' => 'Tag name (unique per workspace)'],
                        ['name' => 'color', 'type' => 'string', 'required' => false, 'desc' => 'Hex color code (e.g. #8B5CF6)'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/tags') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"name\":\"Enterprise\",\"color\":\"#EF4444\"}'",
                    'response' => '{
  "data": {"id": 3, "name": "Enterprise", "color": "#EF4444"}
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'DELETE',
                    'path' => '/api/v1/tags/{id}',
                    'description' => 'Delete a tag. Contacts are NOT deleted.',
                    'params' => [],
                    'curl' => "curl -X DELETE '" . url('/api/v1/tags/3') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'",
                    'response' => '204 No Content',
                ])
            </section>

            {{-- ── CANNED RESPONSES ──────────────────────────────────────── --}}
            <section id="canned-responses" class="mb-16 endpoint-section" x-show="matchesSearch('canned-responses')">
                <h2 class="text-2xl font-bold text-ink mb-1">Canned Responses</h2>
                <p class="text-sm text-muted mb-6">Manage saved reply templates. Requires <code class="text-xs bg-surface-3 px-1.5 py-0.5 rounded text-ink">canned-responses</code> scope.</p>

                @include('api-docs._endpoint', [
                    'method' => 'GET',
                    'path' => '/api/v1/canned-responses',
                    'description' => 'List all canned responses.',
                    'params' => [],
                    'curl' => "curl -X GET '" . url('/api/v1/canned-responses') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Accept: application/json'",
                    'response' => '{
  "data": [
    {"id": 1, "title": "Greeting", "shortcut": "/greet", "body": "Hello! How can I help you today?"}
  ]
}',
                ])

                @include('api-docs._endpoint', [
                    'method' => 'POST',
                    'path' => '/api/v1/canned-responses',
                    'description' => 'Create a new canned response.',
                    'params' => [
                        ['name' => 'title', 'type' => 'string', 'required' => true, 'desc' => 'Template title'],
                        ['name' => 'shortcut', 'type' => 'string', 'required' => false, 'desc' => 'Slash command shortcut (e.g. /thanks)'],
                        ['name' => 'body', 'type' => 'string', 'required' => true, 'desc' => 'Response body (HTML supported)'],
                    ],
                    'curl' => "curl -X POST '" . url('/api/v1/canned-responses') . "' \\\n  -H 'Authorization: Bearer YOUR_TOKEN' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"title\":\"Thanks\",\"shortcut\":\"/thanks\",\"body\":\"Thank you for your patience!\"}'",
                    'response' => '{
  "data": {"id": 5, "title": "Thanks", "shortcut": "/thanks", "body": "Thank you for your patience!"}
}',
                ])
            </section>

            {{-- ═══════════════════════════════════════════════════════════════
                 TRY IT (Interactive)
                 ═══════════════════════════════════════════════════════════════ --}}
            <section id="try-it" class="mb-16">
                <h2 class="text-2xl font-bold text-ink mb-4">Try It</h2>
                <p class="text-sm text-muted mb-6">Test API endpoints directly from this page.</p>

                <div class="panel p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">API Token</label>
                            <input x-model="tryIt.token" type="password" placeholder="Paste your API token"
                                   class="input w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-muted mb-1">Endpoint</label>
                            <select x-model="tryIt.endpoint" class="input w-full text-sm">
                                <option value="">Select an endpoint...</option>
                                <optgroup label="Contacts">
                                    <option value="GET /api/v1/contacts">GET /api/v1/contacts</option>
                                    <option value="POST /api/v1/contacts">POST /api/v1/contacts</option>
                                </optgroup>
                                <optgroup label="Conversations">
                                    <option value="GET /api/v1/conversations">GET /api/v1/conversations</option>
                                </optgroup>
                                <optgroup label="Campaigns">
                                    <option value="GET /api/v1/campaigns">GET /api/v1/campaigns</option>
                                </optgroup>
                                <optgroup label="Tags">
                                    <option value="GET /api/v1/tags">GET /api/v1/tags</option>
                                    <option value="POST /api/v1/tags">POST /api/v1/tags</option>
                                </optgroup>
                                <optgroup label="Canned Responses">
                                    <option value="GET /api/v1/canned-responses">GET /api/v1/canned-responses</option>
                                </optgroup>
                                <optgroup label="Analytics">
                                    <option value="GET /api/v1/analytics/overview">GET /api/v1/analytics/overview</option>
                                </optgroup>
                                <optgroup label="AI">
                                    <option value="POST /api/v1/ai/analyze-sentiment">POST /api/v1/ai/analyze-sentiment</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-muted mb-1">Request Body <span class="text-muted/60">(JSON, for POST/PUT)</span></label>
                        <textarea x-model="tryIt.body" rows="4" placeholder='{"first_name":"Test","email":"test@example.com"}'
                                  class="input w-full text-sm font-mono"></textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button @click="sendTryIt()" :disabled="tryIt.loading || !tryIt.token || !tryIt.endpoint"
                                class="btn btn-primary text-sm disabled:opacity-50">
                            <template x-if="tryIt.loading">
                                <svg class="w-4 h-4 mr-1.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                            </template>
                            Send Request
                        </button>
                        <span x-show="tryIt.status" class="text-sm font-mono"
                              :class="tryIt.status >= 200 && tryIt.status < 300 ? 'text-emerald-600' : 'text-red-600'"
                              x-text="tryIt.status + ' ' + tryIt.statusText" x-cloak></span>
                        <span x-show="tryIt.duration" class="text-xs text-muted" x-text="tryIt.duration + 'ms'" x-cloak></span>
                    </div>

                    <div x-show="tryIt.response !== null" x-cloak>
                        <label class="block text-xs font-medium text-muted mb-1">Response</label>
                        <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 text-xs font-mono overflow-x-auto max-h-80" x-text="tryIt.response"></pre>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<script>
function apiDocs() {
    return {
        searchQuery: '',
        mobileNav: false,
        sections: [
            { id: 'contacts', title: 'Contacts', keywords: 'contacts import export csv' },
            { id: 'conversations', title: 'Conversations', keywords: 'conversations inbox reply assign close' },
            { id: 'campaigns', title: 'Campaigns', keywords: 'campaigns email send schedule' },
            { id: 'workflows', title: 'Workflows', keywords: 'workflows automation activate pause' },
            { id: 'knowledge-base', title: 'Knowledge Base', keywords: 'knowledge base kb documents scrape' },
            { id: 'ai', title: 'AI', keywords: 'ai generate reply sentiment analysis' },
            { id: 'analytics', title: 'Analytics', keywords: 'analytics overview team metrics' },
            { id: 'tags', title: 'Tags', keywords: 'tags labels' },
            { id: 'canned-responses', title: 'Canned Responses', keywords: 'canned responses templates shortcuts' },
        ],
        tryIt: {
            token: '',
            endpoint: '',
            body: '',
            loading: false,
            response: null,
            status: null,
            statusText: '',
            duration: null,
        },

        get filteredSections() {
            if (!this.searchQuery) return this.sections;
            const q = this.searchQuery.toLowerCase();
            return this.sections.filter(s => s.title.toLowerCase().includes(q) || s.keywords.includes(q));
        },

        matchesSearch(sectionId) {
            if (!this.searchQuery) return true;
            const section = this.sections.find(s => s.id === sectionId);
            if (!section) return true;
            const q = this.searchQuery.toLowerCase();
            return section.title.toLowerCase().includes(q) || section.keywords.includes(q);
        },

        scrollTo(id) {
            document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        async sendTryIt() {
            if (!this.tryIt.token || !this.tryIt.endpoint) return;

            this.tryIt.loading = true;
            this.tryIt.response = null;
            this.tryIt.status = null;

            const parts = this.tryIt.endpoint.split(' ');
            const method = parts[0];
            const path = parts.slice(1).join(' ');

            const start = performance.now();

            try {
                const opts = {
                    method: method,
                    headers: {
                        'Authorization': 'Bearer ' + this.tryIt.token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                };

                if (['POST', 'PUT', 'PATCH'].includes(method) && this.tryIt.body.trim()) {
                    opts.body = this.tryIt.body;
                }

                const res = await fetch(path, opts);
                this.tryIt.duration = Math.round(performance.now() - start);
                this.tryIt.status = res.status;
                this.tryIt.statusText = res.statusText;

                const text = await res.text();
                try {
                    this.tryIt.response = JSON.stringify(JSON.parse(text), null, 2);
                } catch {
                    this.tryIt.response = text;
                }
            } catch (e) {
                this.tryIt.response = 'Error: ' + e.message;
                this.tryIt.status = 0;
                this.tryIt.statusText = 'Network Error';
                this.tryIt.duration = Math.round(performance.now() - start);
            } finally {
                this.tryIt.loading = false;
            }
        }
    };
}
</script>
</x-layouts.guest>

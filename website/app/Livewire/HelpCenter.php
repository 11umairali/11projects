<?php

namespace App\Livewire;

use App\Models\HelpArticle;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class HelpCenter extends Component
{
    public string $search = '';
    public string $activeCategory = '';
    public ?int $selectedArticleId = null;

    protected $queryString = ['activeCategory', 'search'];

    public function updatedSearch(): void
    {
        $this->selectedArticleId = null;
    }

    public function selectCategory(string $category): void
    {
        $this->activeCategory = $this->activeCategory === $category ? '' : $category;
        $this->selectedArticleId = null;
    }

    public function selectArticle(int $id): void
    {
        $this->selectedArticleId = $id;
    }

    public function back(): void
    {
        $this->selectedArticleId = null;
    }

    public function markHelpful(int $id): void
    {
        if (!auth()->check()) return;

        $cacheKey = 'helpful_' . auth()->id() . '_' . $id;
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, true, 60);

        HelpArticle::published()->where('id', $id)->increment('helpful_count');
    }

    public function markNotHelpful(int $id): void
    {
        if (!auth()->check()) return;

        $cacheKey = 'not_helpful_' . auth()->id() . '_' . $id;
        if (Cache::has($cacheKey)) return;
        Cache::put($cacheKey, true, 60);

        HelpArticle::published()->where('id', $id)->increment('not_helpful_count');
    }

    public function getArticlesProperty()
    {
        $query = HelpArticle::published()->orderBy('sort_order');

        if ($this->search) {
            $search = mb_substr($this->search, 0, 200);
            $query->search($search);
        }

        if ($this->activeCategory) {
            $query->category($this->activeCategory);
        }

        return $query->get();
    }

    public function getSelectedArticleProperty()
    {
        if (!$this->selectedArticleId) return null;
        return HelpArticle::published()->find($this->selectedArticleId);
    }

    public function getCategoriesProperty(): array
    {
        return [
            'getting-started' => ['label' => 'Getting Started', 'icon' => 'rocket'],
            'email' => ['label' => 'Email', 'icon' => 'mail'],
            'contacts' => ['label' => 'Contacts', 'icon' => 'users'],
            'campaigns' => ['label' => 'Campaigns', 'icon' => 'send'],
            'workflows' => ['label' => 'Workflows', 'icon' => 'git-branch'],
            'deals' => ['label' => 'Deals', 'icon' => 'briefcase'],
            'ai' => ['label' => 'AI Assistant', 'icon' => 'brain'],
            'billing' => ['label' => 'Billing', 'icon' => 'credit-card'],
            'integrations' => ['label' => 'Integrations', 'icon' => 'plug'],
            'troubleshooting' => ['label' => 'Troubleshooting', 'icon' => 'wrench'],
        ];
    }

    public function render()
    {
        return view('livewire.help-center');
    }
}

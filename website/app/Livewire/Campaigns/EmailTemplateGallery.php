<?php

namespace App\Livewire\Campaigns;

use App\Models\EmailTemplate;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Url;
use Livewire\Component;

class EmailTemplateGallery extends Component
{
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $category = 'all';

    public string $search = '';

    /** The template ID currently being previewed in the modal. */
    public ?int $previewId = null;

    /**
     * Category definitions for the filter pills.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'all'           => 'All Templates',
            'onboarding'    => 'Welcome / Onboarding',
            'newsletter'    => 'Newsletter',
            'marketing'     => 'Promotional',
            'engagement'    => 'Follow-up / Re-engagement',
            'transactional' => 'Transactional',
            'events'        => 'Event / Webinar',
            'reporting'     => 'Reports',
            'product'       => 'Product Updates',
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-6 animate-pulse">
            <div class="flex items-center justify-between">
                <div class="h-8 w-48 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                <div class="h-10 w-64 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
            </div>
            <div class="flex gap-2">
                <div class="h-8 w-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                <div class="h-8 w-24 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                <div class="h-8 w-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                <div class="h-8 w-28 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="h-72 bg-gray-200 dark:bg-gray-700 rounded-2xl"></div>
                <div class="h-72 bg-gray-200 dark:bg-gray-700 rounded-2xl"></div>
                <div class="h-72 bg-gray-200 dark:bg-gray-700 rounded-2xl"></div>
            </div>
        </div>
        HTML;
    }

    public function updatedCategory(): void
    {
        // Reset search when category changes
    }

    /**
     * Open the preview modal for a given template.
     */
    public function preview(int $templateId): void
    {
        $this->previewId = $templateId;
    }

    /**
     * Close the preview modal.
     */
    public function closePreview(): void
    {
        $this->previewId = null;
    }

    /**
     * "Use This Template" — redirect to campaign creation with template pre-loaded.
     */
    public function useTemplate(int $templateId): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $template = EmailTemplate::forWorkspace($workspaceId)->findOrFail($templateId);
        $template->incrementUsage();

        $this->redirect("/campaigns/create?template={$templateId}", navigate: true);
    }

    /**
     * Render a template's blocks array into preview-safe HTML.
     * This is a simplified renderer for the gallery preview.
     */
    public static function renderBlocksPreview(array $blocks): string
    {
        $html = '<div style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;">';

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            switch ($type) {
                case 'header':
                    $bgColor = $data['bg_color'] ?? '#6366F1';
                    $company = $data['company_name'] ?? '';
                    $html .= "<div style=\"background:{$bgColor};padding:24px 32px;text-align:center;\"><span style=\"color:#ffffff;font-size:18px;font-weight:700;\">" . e($company) . "</span></div>";
                    break;

                case 'text':
                    $content = $data['content'] ?? '';
                    $html .= "<div style=\"padding:16px 32px;\">{$content}</div>";
                    break;

                case 'image':
                    $src = $data['src'] ?? '';
                    $alt = $data['alt'] ?? '';
                    if ($src) {
                        $html .= "<div style=\"padding:0 32px;\"><img src=\"" . e($src) . "\" alt=\"" . e($alt) . "\" style=\"width:100%;height:auto;display:block;border-radius:8px;\" /></div>";
                    }
                    break;

                case 'button':
                    $text = $data['text'] ?? 'Click Here';
                    $bgColor = $data['bg_color'] ?? '#6366F1';
                    $textColor = $data['text_color'] ?? '#FFFFFF';
                    $html .= "<div style=\"padding:16px 32px;text-align:" . ($data['align'] ?? 'center') . ";\"><a style=\"display:inline-block;padding:12px 28px;background:{$bgColor};color:{$textColor};text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;\">" . e($text) . "</a></div>";
                    break;

                case 'columns':
                    $left = $data['left_content'] ?? '';
                    $right = $data['right_content'] ?? '';
                    $html .= "<div style=\"padding:8px 32px;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"><tr><td width=\"48%\" style=\"vertical-align:top;padding:8px;\">{$left}</td><td width=\"4%\"></td><td width=\"48%\" style=\"vertical-align:top;padding:8px;\">{$right}</td></tr></table></div>";
                    break;

                case 'divider':
                    $color = $data['color'] ?? '#E5E7EB';
                    $html .= "<div style=\"padding:8px 32px;\"><hr style=\"border:none;border-top:1px solid {$color};\" /></div>";
                    break;

                case 'spacer':
                    $height = $data['height'] ?? '16';
                    $html .= "<div style=\"height:{$height}px;\"></div>";
                    break;

                case 'social':
                    $html .= "<div style=\"padding:16px 32px;text-align:center;\">";
                    foreach ($data['links'] ?? [] as $link) {
                        $platform = ucfirst($link['platform'] ?? '');
                        $html .= "<a style=\"display:inline-block;margin:0 8px;color:#6366F1;text-decoration:none;font-size:13px;font-weight:500;\">" . e($platform) . "</a>";
                    }
                    $html .= "</div>";
                    break;

                case 'footer':
                    $text = $data['text'] ?? '';
                    $unsub = $data['unsubscribe_text'] ?? 'Unsubscribe';
                    $html .= "<div style=\"padding:20px 32px;text-align:center;background:#F8FAFC;border-top:1px solid #E5E7EB;\"><p style=\"margin:0 0 8px 0;font-size:12px;color:#94A3B8;\">" . e($text) . "</p><a style=\"font-size:12px;color:#6366F1;text-decoration:underline;\">" . e($unsub) . "</a></div>";
                    break;
            }
        }

        $html .= '</div>';

        return $html;
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $query = EmailTemplate::forWorkspace($workspaceId);

        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where('name', 'like', $term);
        }

        $templates = $query->orderByDesc('usage_count')->get();

        // Load preview template if modal is open
        $previewTemplate = null;
        $previewHtml = '';
        if ($this->previewId) {
            $previewTemplate = EmailTemplate::forWorkspace($workspaceId)->find($this->previewId);
            if ($previewTemplate) {
                $previewHtml = self::renderBlocksPreview($previewTemplate->blocks ?? []);
            }
        }

        // Category counts for pills
        $categoryCounts = EmailTemplate::forWorkspace($workspaceId)
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $totalCount = $categoryCounts->sum();

        return view('livewire.campaigns.email-template-gallery', [
            'templates'       => $templates,
            'categories'      => self::categories(),
            'categoryCounts'  => $categoryCounts,
            'totalCount'      => $totalCount,
            'previewTemplate' => $previewTemplate,
            'previewHtml'     => $previewHtml,
        ]);
    }
}

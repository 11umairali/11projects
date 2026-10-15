<?php

namespace App\Livewire\Campaigns;

use App\Models\EmailTemplate;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EmailBuilder extends Component
{
    use AuthorizesWorkspaceActions;

    /** @var array<int, array{type: string, data: array}> */
    public array $blocks = [];

    public ?int $templateId = null;

    public string $templateName = '';

    public int $selectedBlockIndex = -1;

    public bool $showPreview = false;

    /** Autosave state */
    public ?string $lastSavedAt = null;
    public bool $hasUnsavedChanges = false;
    public bool $autoSaveEnabled = true;

    /** Block type definitions for the palette */
    protected array $blockTypes = [
        'header' => [
            'label' => 'Header',
            'icon' => 'heading',
            'defaults' => [
                'logo_url' => '',
                'company_name' => '{company}',
                'bg_color' => '#4F46E5',
            ],
        ],
        'text' => [
            'label' => 'Text',
            'icon' => 'text',
            'defaults' => [
                'content' => '<p>Enter your text here...</p>',
                'align' => 'left',
                'font_size' => '16',
            ],
        ],
        'image' => [
            'label' => 'Image',
            'icon' => 'image',
            'defaults' => [
                'src' => 'https://placehold.co/600x200/E5E7EB/9CA3AF?text=Your+Image',
                'alt' => 'Image description',
                'width' => '100',
                'link_url' => '',
            ],
        ],
        'button' => [
            'label' => 'Button',
            'icon' => 'button',
            'defaults' => [
                'text' => 'Click Here',
                'url' => 'https://example.com',
                'bg_color' => '#4F46E5',
                'text_color' => '#FFFFFF',
                'align' => 'center',
            ],
        ],
        'divider' => [
            'label' => 'Divider',
            'icon' => 'divider',
            'defaults' => [
                'color' => '#E5E7EB',
                'width' => '100',
                'style' => 'solid',
            ],
        ],
        'columns' => [
            'label' => '2 Columns',
            'icon' => 'columns',
            'defaults' => [
                'left_content' => '<p>Left column content</p>',
                'right_content' => '<p>Right column content</p>',
            ],
        ],
        'spacer' => [
            'label' => 'Spacer',
            'icon' => 'spacer',
            'defaults' => [
                'height' => '30',
            ],
        ],
        'social' => [
            'label' => 'Social Links',
            'icon' => 'social',
            'defaults' => [
                'links' => [
                    ['platform' => 'twitter', 'url' => 'https://twitter.com/'],
                    ['platform' => 'linkedin', 'url' => 'https://linkedin.com/'],
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/'],
                ],
            ],
        ],
        'footer' => [
            'label' => 'Footer',
            'icon' => 'footer',
            'defaults' => [
                'text' => '{company} | 123 Business Street',
                'unsubscribe_text' => 'Unsubscribe from these emails',
            ],
        ],
    ];

    public function mount(?int $templateId = null): void
    {
        $this->templateId = $templateId;

        if ($templateId) {
            $this->loadTemplate($templateId);
        }
    }

    /**
     * Add a new block at the given position, or at the end if null.
     */
    public function addBlock(string $type, ?int $position = null): void
    {
        if (! isset($this->blockTypes[$type])) {
            return;
        }

        $block = [
            'type' => $type,
            'data' => $this->blockTypes[$type]['defaults'],
        ];

        if ($position !== null && $position >= 0 && $position <= count($this->blocks)) {
            array_splice($this->blocks, $position, 0, [$block]);
            // If we inserted before or at the selected block, shift selection
            if ($this->selectedBlockIndex >= 0 && $position <= $this->selectedBlockIndex) {
                $this->selectedBlockIndex++;
            }
        } else {
            $this->blocks[] = $block;
        }

        // Select the newly added block
        $this->selectedBlockIndex = $position ?? (count($this->blocks) - 1);
    }

    /**
     * Remove a block by index.
     */
    public function removeBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        array_splice($this->blocks, $index, 1);
        $this->blocks = array_values($this->blocks);

        // Adjust selection
        if ($this->selectedBlockIndex === $index) {
            $this->selectedBlockIndex = -1;
        } elseif ($this->selectedBlockIndex > $index) {
            $this->selectedBlockIndex--;
        }
    }

    /**
     * Move a block from one position to another.
     */
    public function moveBlock(int $from, int $to): void
    {
        if (! isset($this->blocks[$from]) || $to < 0 || $to >= count($this->blocks) || $from === $to) {
            return;
        }

        $block = $this->blocks[$from];
        array_splice($this->blocks, $from, 1);
        array_splice($this->blocks, $to, 0, [$block]);
        $this->blocks = array_values($this->blocks);

        // Track selection through the move
        if ($this->selectedBlockIndex === $from) {
            $this->selectedBlockIndex = $to;
        } elseif ($from < $to) {
            if ($this->selectedBlockIndex > $from && $this->selectedBlockIndex <= $to) {
                $this->selectedBlockIndex--;
            }
        } else {
            if ($this->selectedBlockIndex >= $to && $this->selectedBlockIndex < $from) {
                $this->selectedBlockIndex++;
            }
        }
    }

    /**
     * Reorder blocks from a drag-drop sort operation.
     * Receives the full ordered list of indices from Alpine.
     */
    public function reorderBlocks(array $order): void
    {
        $newBlocks = [];
        foreach ($order as $oldIndex) {
            if (isset($this->blocks[$oldIndex])) {
                $newBlocks[] = $this->blocks[$oldIndex];
            }
        }
        $this->blocks = $newBlocks;

        // Find the new position of the selected block
        if ($this->selectedBlockIndex >= 0) {
            $newPos = array_search($this->selectedBlockIndex, $order);
            $this->selectedBlockIndex = $newPos !== false ? $newPos : -1;
        }
    }

    /**
     * Update a block's data by index.
     */
    public function updateBlock(int $index, array $data): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->blocks[$index]['data'] = array_merge(
            $this->blocks[$index]['data'],
            $data
        );
    }

    /**
     * Select a block for editing in the properties panel.
     */
    public function selectBlock(int $index): void
    {
        $this->selectedBlockIndex = ($this->selectedBlockIndex === $index) ? -1 : $index;
    }

    /**
     * Duplicate a block.
     */
    public function duplicateBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $clone = $this->blocks[$index];
        array_splice($this->blocks, $index + 1, 0, [$clone]);
        $this->blocks = array_values($this->blocks);
        $this->selectedBlockIndex = $index + 1;
    }

    /**
     * Compile the block structure into responsive email-compatible HTML.
     * Uses TABLE-based layout with inline styles for maximum email client compatibility.
     */
    public function compileToHtml(): string
    {
        $rows = '';
        foreach ($this->blocks as $block) {
            $rows .= $this->compileBlock($block);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Email</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .fluid { max-width: 100% !important; height: auto !important; margin-left: auto !important; margin-right: auto !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; direction: ltr !important; }
            .stack-column-center { text-align: center !important; }
            .center-on-narrow { text-align: center !important; display: block !important; margin-left: auto !important; margin-right: auto !important; float: none !important; }
            table.center-on-narrow { display: inline-block !important; }
            .mobile-padding { padding-left: 16px !important; padding-right: 16px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F3F4F6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <center style="width:100%;background-color:#F3F4F6;">
        <!--[if mso]>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center"><tr><td>
        <![endif]-->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;" class="email-container">
            {$rows}
        </table>
        <!--[if mso]>
        </td></tr></table>
        <![endif]-->
    </center>
</body>
</html>
HTML;
    }

    /**
     * Compile a single block to its HTML table row representation.
     */
    protected function compileBlock(array $block): string
    {
        $type = $block['type'];
        $data = $block['data'];

        return match ($type) {
            'header' => $this->compileHeader($data),
            'text' => $this->compileText($data),
            'image' => $this->compileImage($data),
            'button' => $this->compileButton($data),
            'divider' => $this->compileDivider($data),
            'columns' => $this->compileColumns($data),
            'spacer' => $this->compileSpacer($data),
            'social' => $this->compileSocial($data),
            'footer' => $this->compileFooter($data),
            default => '',
        };
    }

    protected function compileHeader(array $data): string
    {
        $bgColor = e($data['bg_color'] ?? '#4F46E5');
        $companyName = e($data['company_name'] ?? '');
        $logoUrl = e($data['logo_url'] ?? '');

        $logoHtml = '';
        if (! empty($logoUrl)) {
            $logoHtml = '<img src="' . $logoUrl . '" alt="' . $companyName . '" width="40" height="40" style="display:inline-block;vertical-align:middle;margin-right:12px;border-radius:8px;">';
        }

        return <<<HTML
<tr>
    <td style="background-color:{$bgColor};padding:24px 32px;text-align:center;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="text-align:center;font-size:20px;font-weight:700;color:#FFFFFF;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    {$logoHtml}{$companyName}
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected function compileText(array $data): string
    {
        $align = e($data['align'] ?? 'left');
        $fontSize = intval($data['font_size'] ?? 16);
        $content = $data['content'] ?? '';

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:{$align};font-size:{$fontSize}px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="mobile-padding">
        {$content}
    </td>
</tr>
HTML;
    }

    protected function compileImage(array $data): string
    {
        $src = e($data['src'] ?? '');
        $alt = e($data['alt'] ?? '');
        $width = intval($data['width'] ?? 100);
        $linkUrl = e($data['link_url'] ?? '');

        $widthPx = intval(600 * $width / 100);
        $widthStyle = $width < 100 ? "width:{$widthPx}px;max-width:{$width}%;" : 'width:100%;';

        $imgTag = '<img src="' . $src . '" alt="' . $alt . '" style="' . $widthStyle . 'height:auto;display:block;margin:0 auto;border:0;" class="fluid">';

        if (! empty($linkUrl)) {
            $imgTag = '<a href="' . $linkUrl . '" target="_blank" style="text-decoration:none;">' . $imgTag . '</a>';
        }

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:0;text-align:center;">
        {$imgTag}
    </td>
</tr>
HTML;
    }

    protected function compileButton(array $data): string
    {
        $text = e($data['text'] ?? 'Click Here');
        $url = e($data['url'] ?? '#');
        $bgColor = e($data['bg_color'] ?? '#4F46E5');
        $textColor = e($data['text_color'] ?? '#FFFFFF');
        $align = e($data['align'] ?? 'center');

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:{$align};" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;" align="{$align}">
            <tr>
                <td style="border-radius:8px;background-color:{$bgColor};">
                    <a href="{$url}" target="_blank" style="display:inline-block;padding:14px 32px;font-size:16px;font-weight:600;color:{$textColor};text-decoration:none;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                        <!--[if mso]>&nbsp;&nbsp;&nbsp;<![endif]-->{$text}<!--[if mso]>&nbsp;&nbsp;&nbsp;<![endif]-->
                    </a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected function compileDivider(array $data): string
    {
        $color = e($data['color'] ?? '#E5E7EB');
        $width = intval($data['width'] ?? 100);
        $style = e($data['style'] ?? 'solid');

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:16px 32px;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="{$width}%" align="center">
            <tr>
                <td style="border-top:1px {$style} {$color};font-size:0;line-height:0;">&nbsp;</td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected function compileColumns(array $data): string
    {
        $leftContent = $data['left_content'] ?? '';
        $rightContent = $data['right_content'] ?? '';

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <!--[if mso]><td valign="top" width="264"><![endif]-->
                <td width="48%" style="padding-right:12px;vertical-align:top;font-size:16px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="stack-column">
                    {$leftContent}
                </td>
                <!--[if mso]></td><td valign="top" width="264"><![endif]-->
                <td width="48%" style="padding-left:12px;vertical-align:top;font-size:16px;line-height:1.6;color:#1E293B;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;" class="stack-column">
                    {$rightContent}
                </td>
                <!--[if mso]></td><![endif]-->
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected function compileSpacer(array $data): string
    {
        $height = intval($data['height'] ?? 30);

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;font-size:0;line-height:0;height:{$height}px;">&nbsp;</td>
</tr>
HTML;
    }

    protected function compileSocial(array $data): string
    {
        $links = $data['links'] ?? [];

        $iconsHtml = '';
        foreach ($links as $link) {
            $platform = e($link['platform'] ?? '');
            $url = e($link['url'] ?? '#');
            $label = ucfirst($platform);

            // Use simple text-based icons for maximum email client compatibility
            $colors = [
                'twitter' => '#1DA1F2',
                'linkedin' => '#0A66C2',
                'facebook' => '#1877F2',
                'instagram' => '#E4405F',
                'youtube' => '#FF0000',
                'github' => '#333333',
            ];
            $color = $colors[$platform] ?? '#64748B';

            $iconsHtml .= <<<HTML
                <td style="padding:0 8px;">
                    <a href="{$url}" target="_blank" style="display:inline-block;width:36px;height:36px;line-height:36px;text-align:center;background-color:{$color};color:#FFFFFF;border-radius:50%;text-decoration:none;font-size:14px;font-weight:600;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                        {$label[0]}
                    </a>
                </td>
HTML;
        }

        return <<<HTML
<tr>
    <td style="background-color:#FFFFFF;padding:24px 32px;text-align:center;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;">
            <tr>
                {$iconsHtml}
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    protected function compileFooter(array $data): string
    {
        $text = e($data['text'] ?? '');
        $unsubscribeText = e($data['unsubscribe_text'] ?? 'Unsubscribe');

        return <<<HTML
<tr>
    <td style="background-color:#F8FAFC;padding:24px 32px;text-align:center;border-top:1px solid #E5E7EB;" class="mobile-padding">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="font-size:13px;line-height:1.5;color:#94A3B8;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    {$text}
                </td>
            </tr>
            <tr>
                <td style="font-size:13px;line-height:1.5;color:#94A3B8;text-align:center;padding-top:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                    <a href="{{unsubscribe_url}}" style="color:#64748B;text-decoration:underline;">{$unsubscribeText}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
HTML;
    }

    /**
     * Save the current block layout as a reusable template.
     */
    public function saveAsTemplate(?string $name = null): void
    {
        if (! $this->authorizeWorkspaceAction('create')) {
            return;
        }

        $name = $name ?: $this->templateName;

        if (empty($name)) {
            $this->addError('templateName', 'Please enter a template name.');
            return;
        }

        if (empty($this->blocks)) {
            $this->addError('templateName', 'Cannot save an empty template.');
            return;
        }

        $workspaceId = $this->workspaceId();

        $data = [
            'workspace_id' => $workspaceId,
            'name' => $name,
            'blocks' => $this->blocks,
            'category' => 'custom',
            'is_default' => false,
        ];

        if ($this->templateId) {
            $template = EmailTemplate::where('workspace_id', $workspaceId)
                ->where('id', $this->templateId)
                ->where('is_default', false)
                ->first();

            if ($template) {
                $template->update($data);
            } else {
                // If the loaded template was a default, save as new
                $template = EmailTemplate::create($data);
                $this->templateId = $template->id;
            }
        } else {
            $template = EmailTemplate::create($data);
            $this->templateId = $template->id;
        }

        $this->templateName = $name;
        $this->dispatch('template-saved');
        session()->flash('success', 'Template saved successfully.');
    }

    /**
     * Load blocks from a saved template.
     */
    public function loadTemplate(int $templateId): void
    {
        $workspaceId = $this->workspaceId();

        $template = EmailTemplate::forWorkspace($workspaceId)
            ->where('id', $templateId)
            ->first();

        if (! $template) {
            \Illuminate\Support\Facades\Log::warning('EmailBuilder: loadTemplate not found', [
                'template_id' => $templateId,
                'workspace_id' => $workspaceId,
            ]);
            session()->flash('error', 'Template not found in your workspace.');
            return;
        }

        // Blocks is an `array` cast on the model, but defensive here in case
        // something wrote a raw JSON string to the column (e.g. imported rows)
        // — we still want a usable array of blocks, not null or a string.
        $rawBlocks = $template->blocks;
        if (is_string($rawBlocks)) {
            $decoded = json_decode($rawBlocks, true);
            $rawBlocks = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($rawBlocks)) {
            $rawBlocks = [];
        }

        \Illuminate\Support\Facades\Log::info('EmailBuilder: loadTemplate', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'workspace_id' => $template->workspace_id,
            'is_default' => (bool) $template->is_default,
            'blocks_count' => count($rawBlocks),
        ]);

        if (empty($rawBlocks)) {
            session()->flash('error', 'This template has no blocks. Nothing to load.');
            return;
        }

        $this->blocks = $rawBlocks;
        $this->templateId = $template->id;
        $this->templateName = $template->name;
        $this->selectedBlockIndex = -1;

        // Drop out of preview mode so the user immediately sees the editor
        // populated with the new blocks — staying in preview after loading
        // a template was the "broken" feeling (looked like nothing happened).
        $this->showPreview = false;

        $template->incrementUsage();

        session()->flash('success', "Loaded template: {$template->name}");
    }

    /**
     * Computed property: the compiled HTML for preview.
     */
    #[Computed]
    public function previewHtml(): string
    {
        if (empty($this->blocks)) {
            return '';
        }

        return $this->compileToHtml();
    }

    /**
     * Dispatch the compiled HTML to the campaign editor.
     */
    public function useInCampaign(): void
    {
        if (empty($this->blocks)) {
            $this->addError('templateName', 'Add at least one block before using in a campaign.');
            return;
        }

        $html = $this->compileToHtml();
        $this->dispatch('builder-html-ready', html: $html, blocks: $this->blocks);
    }

    /**
     * Get available block types for the palette.
     */
    #[Computed]
    public function availableBlockTypes(): array
    {
        return $this->blockTypes;
    }

    /**
     * Get the selected block's data for the properties panel.
     */
    #[Computed]
    public function selectedBlock(): ?array
    {
        if ($this->selectedBlockIndex < 0 || ! isset($this->blocks[$this->selectedBlockIndex])) {
            return null;
        }

        return $this->blocks[$this->selectedBlockIndex];
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    /**
     * Autosave the current template every 30 seconds (called via wire:poll).
     * Silently persists to database; never interrupts the user on failure.
     */
    public function autoSave(): void
    {
        if (!$this->autoSaveEnabled || !$this->hasUnsavedChanges) {
            return;
        }

        if (empty($this->blocks)) {
            return;
        }

        try {
            $this->saveDraft();
            $this->lastSavedAt = now()->format('g:i A');
            $this->hasUnsavedChanges = false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Campaign autosave failed', [
                'template_id' => $this->templateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist the current blocks as a draft template.
     * Used by both autosave and explicit save actions.
     */
    public function saveDraft(): void
    {
        $workspaceId = $this->workspaceId();
        $name = $this->templateName ?: 'Untitled Template';

        $data = [
            'workspace_id' => $workspaceId,
            'name' => $name,
            'blocks' => $this->blocks,
            'category' => 'custom',
            'is_default' => false,
        ];

        if ($this->templateId) {
            $template = EmailTemplate::where('workspace_id', $workspaceId)
                ->where('id', $this->templateId)
                ->where('is_default', false)
                ->first();

            if ($template) {
                $template->update($data);
            } else {
                $template = EmailTemplate::create($data);
                $this->templateId = $template->id;
            }
        } else {
            $template = EmailTemplate::create($data);
            $this->templateId = $template->id;
        }

        $this->templateName = $name;
    }

    /**
     * Track property changes to flag unsaved state.
     * Excludes autosave bookkeeping properties from triggering the flag.
     */
    public function updated($propertyName): void
    {
        $excludedProps = ['lastSavedAt', 'hasUnsavedChanges', 'autoSaveEnabled', 'showPreview', 'selectedBlockIndex'];

        // Also exclude the property name if it starts with an excluded prefix
        $rootProp = explode('.', $propertyName)[0];

        if (!in_array($rootProp, $excludedProps)) {
            $this->hasUnsavedChanges = true;
        }
    }

    public function rendering(): void
    {
        // Clean up any malformed blocks (missing 'type' key) that may have been
        // introduced by drag-drop or Alpine.js state corruption
        $this->blocks = array_values(array_filter($this->blocks, fn ($b) => isset($b['type'])));
    }

    public function render()
    {
        $workspaceId = $this->workspaceId();

        $savedTemplates = EmailTemplate::forWorkspace($workspaceId)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        // Diagnostic — log how many templates the current workspace can
        // see. If the dropdown says "No templates yet" but you DO have
        // templates, check the log: the template rows might live on a
        // different workspace_id or lack the is_default flag.
        \Illuminate\Support\Facades\Log::info('EmailBuilder: templates loaded', [
            'workspace_id' => $workspaceId,
            'count_visible' => $savedTemplates->count(),
            'count_all_in_db' => EmailTemplate::count(),
            'count_for_this_workspace_only' => EmailTemplate::where('workspace_id', $workspaceId)->count(),
            'count_defaults_only' => EmailTemplate::where('is_default', true)->count(),
        ]);

        return view('livewire.campaigns.email-builder', [
            'savedTemplates' => $savedTemplates,
        ]);
    }
}

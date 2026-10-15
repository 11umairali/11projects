<?php

namespace App\Helpers;

/**
 * Whitelist-based HTML sanitizer using DOMDocument.
 * Strips all tags/attributes not explicitly allowed.
 * Prevents XSS via event handlers, javascript: URIs, and data: URIs.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'img', 'span', 'div',
        'hr', 'sub', 'sup', 'dl', 'dt', 'dd', 'figure', 'figcaption',
        // Email-layout staples. <style> is kept because most HTML emails
        // ship their typography/responsive rules in a head <style> block
        // (.nl-container, .container, media queries); stripping it left
        // every email collapsed. Its text content is scrubbed through
        // sanitizeStyle() below for the same dangerous-CSS patterns we
        // enforce on inline style="..." attributes. <center> and <font>
        // are legacy but still ubiquitous in marketing emails (Gmail,
        // Outlook, Mailchimp, SendGrid templates all emit them).
        'style', 'center', 'font',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'title'],
        'td' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height', 'bgcolor'],
        'th' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height', 'bgcolor'],
        'tr' => ['align', 'valign', 'bgcolor'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'align', 'width', 'height', 'bgcolor'],
        'font' => ['color', 'size', 'face'],
        // `style` is allowed back into the global list because strip­ping it
        // was wrecking every HTML email's layout (tables collapsed, images
        // flowed wrong, buttons lost their backgrounds). Values still go
        // through sanitizeStyle() below, which blocks javascript:, data:,
        // expression(), -moz-binding, etc. — the actual CSS injection
        // vectors that FIX-027 was trying to close.
        '*' => ['class', 'dir', 'lang', 'style', 'align', 'valign', 'width', 'height'],
    ];

    private const DANGEROUS_CSS_PATTERNS = [
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/-moz-binding/i',
        '/behavior\s*:/i',
        '/url\s*\(\s*["\']?\s*data:/i',
    ];

    public static function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Wrap in container to handle fragments
        $wrapped = '<div id="__sanitize_root__">' . $html . '</div>';

        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Suppress errors from malformed HTML
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('__sanitize_root__');
        if (!$root) {
            return e($html); // Fallback: escape everything
        }

        self::sanitizeNode($root);

        // Extract inner HTML of root
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        $removeQueue = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tagName = strtolower($child->tagName);

                // <style> is now allowlisted (emails need it), but its
                // CSS text content must still be scrubbed for the same
                // dangerous patterns we enforce on inline style="...".
                // Handle it BEFORE the generic allow/deny branches so
                // the rest of the loop doesn't try to recurse into its
                // text node.
                if ($tagName === 'style') {
                    self::sanitizeAttributes($child, $tagName);
                    $cssText = $child->textContent ?? '';
                    $safeCss = self::sanitizeStyle($cssText);
                    if ($safeCss === '') {
                        $removeQueue[] = $child;
                    } else {
                        // Replace the text content with the scrubbed CSS
                        while ($child->firstChild) {
                            $child->removeChild($child->firstChild);
                        }
                        $child->appendChild($child->ownerDocument->createTextNode($safeCss));
                    }
                    continue;
                }

                // Remove disallowed tags but keep their children
                if (!in_array($tagName, self::ALLOWED_TAGS)) {
                    // For non-content tags, remove everything including
                    // children. `head` and `title` are here so that a full
                    // `<!DOCTYPE html>...<title>Email</title>...` document
                    // doesn't leak its title text into the preview body.
                    if (in_array($tagName, ['script', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'link', 'meta', 'base', 'head', 'title'])) {
                        $removeQueue[] = $child;
                        continue;
                    }

                    // For other disallowed tags, unwrap (keep children)
                    self::sanitizeNode($child);
                    $fragment = $child->ownerDocument->createDocumentFragment();
                    while ($child->firstChild) {
                        $fragment->appendChild($child->firstChild);
                    }
                    $child->parentNode->replaceChild($fragment, $child);
                    continue;
                }

                // Sanitize attributes on allowed tags
                self::sanitizeAttributes($child, $tagName);

                // Recurse into children
                self::sanitizeNode($child);
            } elseif ($child instanceof \DOMComment) {
                $removeQueue[] = $child;
            }
        }

        foreach ($removeQueue as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    private static function sanitizeAttributes(\DOMElement $element, string $tagName): void
    {
        $allowedForTag = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];
        $allowedGlobal = self::ALLOWED_ATTRIBUTES['*'] ?? [];
        $allowed = array_merge($allowedForTag, $allowedGlobal);

        $removeAttrs = [];
        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->name);

            // Remove any event handler attributes (on*)
            if (str_starts_with($attrName, 'on')) {
                $removeAttrs[] = $attr->name;
                continue;
            }

            // Remove disallowed attributes
            if (!in_array($attrName, $allowed)) {
                $removeAttrs[] = $attr->name;
                continue;
            }

            // Validate href/src values
            if (in_array($attrName, ['href', 'src'])) {
                $value = trim($attr->value);
                $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');

                $allowedSchemes = ['http', 'https', 'mailto', 'tel', ''];
                if ($attrName === 'src') {
                    $allowedSchemes = ['http', 'https', ''];
                }

                if (!in_array($scheme, $allowedSchemes)) {
                    $removeAttrs[] = $attr->name;
                    continue;
                }

                // FIX-028: Decode HTML entities BEFORE checking for dangerous schemes
                // This prevents bypass via jav&#97;script: or &#106;avascript:
                $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $normalized = preg_replace('/[\s\x00-\x1F]+/', '', strtolower($decoded));
                if (preg_match('/^(javascript|data|vbscript):/i', $normalized)) {
                    $removeAttrs[] = $attr->name;
                    continue;
                }
            }

            // Sanitize style attribute
            if ($attrName === 'style') {
                $sanitizedStyle = self::sanitizeStyle($attr->value);
                if ($sanitizedStyle === '') {
                    $removeAttrs[] = $attr->name;
                } else {
                    $attr->value = $sanitizedStyle;
                }
            }
        }

        foreach ($removeAttrs as $attrName) {
            $element->removeAttribute($attrName);
        }

        // Force rel="noopener noreferrer" on external links
        if ($tagName === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function sanitizeStyle(string $style): string
    {
        foreach (self::DANGEROUS_CSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $style)) {
                return '';
            }
        }

        return $style;
    }
}

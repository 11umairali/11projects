<?php

namespace App\Helpers;

/**
 * Sanitizes SVG fragment content (path data within an <svg> wrapper).
 *
 * Only allows known-safe SVG child elements and attributes.
 * Strips event handlers, script tags, and dangerous URIs.
 */
class SvgSanitizer
{
    /**
     * Allowed SVG child element names (lowercase).
     */
    private const ALLOWED_ELEMENTS = [
        'circle', 'ellipse', 'line', 'path', 'polygon', 'polyline',
        'rect', 'g', 'use', 'text', 'tspan', 'defs', 'clippath',
        'lineargradient', 'radialgradient', 'stop', 'mask',
    ];

    /**
     * Allowed SVG attributes (lowercase).
     */
    private const ALLOWED_ATTRIBUTES = [
        'cx', 'cy', 'r', 'rx', 'ry', 'x', 'x1', 'x2', 'y', 'y1', 'y2',
        'd', 'points', 'width', 'height', 'viewbox',
        'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit',
        'opacity', 'fill-opacity', 'stroke-opacity', 'fill-rule', 'clip-rule',
        'transform', 'class', 'id', 'clip-path', 'mask',
        'offset', 'stop-color', 'stop-opacity',
        'gradientunits', 'gradienttransform', 'spreadmethod',
        'xmlns', 'xmlns:xlink', 'xlink:href',
        'text-anchor', 'dominant-baseline', 'font-size', 'font-weight',
        'dx', 'dy', 'rotate', 'textlength', 'lengthadjust',
        'scope',
    ];

    /**
     * Sanitize an SVG fragment (the inner content of an <svg> tag).
     *
     * Returns only safe SVG child elements with safe attributes.
     * Falls back to empty string on parse failure.
     */
    public static function sanitize(?string $svgFragment): string
    {
        if ($svgFragment === null || trim($svgFragment) === '') {
            return '';
        }

        // Quick reject: if it contains obvious attack patterns, strip them
        if (preg_match('/<script|javascript:|on\w+\s*=/i', $svgFragment)) {
            $svgFragment = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $svgFragment);
            $svgFragment = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svgFragment);
            $svgFragment = preg_replace('/javascript\s*:/i', '', $svgFragment);
        }

        // Wrap in a root element for DOM parsing
        $wrapped = '<svg xmlns="http://www.w3.org/2000/svg">' . $svgFragment . '</svg>';

        // Suppress warnings from malformed SVG
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadXML($wrapped);
        libxml_clear_errors();

        $svg = $doc->documentElement;
        if (!$svg) {
            return '';
        }

        // Process children recursively
        self::sanitizeNode($svg);

        // Extract inner HTML (children of the wrapping <svg>)
        $result = '';
        foreach ($svg->childNodes as $child) {
            $result .= $doc->saveXML($child);
        }

        return $result;
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        $toRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->localName);

                // Remove disallowed elements
                if (!in_array($tagName, self::ALLOWED_ELEMENTS, true)) {
                    $toRemove[] = $child;
                    continue;
                }

                // Remove disallowed attributes
                $attrsToRemove = [];
                foreach ($child->attributes as $attr) {
                    $attrName = strtolower($attr->name);
                    $attrValue = strtolower(trim($attr->value));

                    if (!in_array($attrName, self::ALLOWED_ATTRIBUTES, true)) {
                        $attrsToRemove[] = $attr->name;
                        continue;
                    }

                    // Block dangerous URI values in attributes
                    if (str_starts_with($attrValue, 'javascript:') || str_starts_with($attrValue, 'data:')) {
                        $attrsToRemove[] = $attr->name;
                    }
                }

                foreach ($attrsToRemove as $attrName) {
                    $child->removeAttribute($attrName);
                }

                // Recurse into children
                self::sanitizeNode($child);
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                // Text nodes are safe — allow them (e.g. inside <text>)
            } else {
                // Remove comments, processing instructions, CDATA, etc.
                $toRemove[] = $child;
            }
        }

        foreach ($toRemove as $node) {
            $node->parentNode->removeChild($node);
        }
    }
}

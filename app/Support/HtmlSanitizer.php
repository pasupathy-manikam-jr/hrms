<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML cleaner for admin-written custom pages: keeps simple formatting tags,
 * drops every attribute except a safe a[href], unwraps unknown tags and removes scripts.
 */
class HtmlSanitizer
{
    private const ALLOWED = ['b', 'strong', 'i', 'em', 'br', 'p', 'ul', 'ol', 'li', 'a', 'h2', 'h3'];

    /** Removed together with their contents. */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'textarea', 'select', 'title', 'head'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        // The XML prolog makes libxml read the input as UTF-8.
        @$doc->loadHTML('<?xml encoding="UTF-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        $root = $doc->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($child);
            } elseif ($child->nodeType !== XML_TEXT_NODE) {
                $node->removeChild($child); // comments, processing instructions, CDATA
            }
        }
    }

    private static function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $parent = $element->parentNode;

        if ($parent === null || in_array($tag, self::DROPPED, true)) {
            $parent?->removeChild($element);

            return;
        }

        self::cleanChildren($element);

        if (! in_array($tag, self::ALLOWED, true)) {
            // Unknown tag: keep its (already cleaned) children, drop the tag itself.
            while ($element->firstChild) {
                $parent->insertBefore($element->firstChild, $element);
            }
            $parent->removeChild($element);

            return;
        }

        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        if ($href !== '' && preg_match('#^(https?://|/(?![/\\\\]))#i', $href)) {
            $element->setAttribute('href', $href);
        }
    }
}

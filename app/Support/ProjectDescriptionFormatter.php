<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

class ProjectDescriptionFormatter
{
    private const ALLOWED_ELEMENTS = [
        'b',
        'blockquote',
        'br',
        'div',
        'em',
        'h2',
        'h3',
        'h4',
        'i',
        'li',
        'ol',
        'p',
        'strong',
        'u',
        'ul',
    ];

    private const DISCARDED_ELEMENTS = [
        'embed',
        'iframe',
        'math',
        'object',
        'script',
        'style',
        'svg',
        'template',
    ];

    public function sanitize(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (! preg_match('/<\s*\/?\s*[a-z][a-z0-9:-]*(?:\s|\/?>)/i', $content)) {
            return $this->plainTextToHtml($content);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body>'.$content.'</body></html>',
                LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
            if (! $loaded) {
                throw new RuntimeException('Unable to parse the project description.');
            }

            $body = $document->getElementsByTagName('body')->item(0);
            if (! $body instanceof DOMElement) {
                throw new RuntimeException('Unable to parse the project description.');
            }

            $children = [];
            foreach ($body->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }

            $html = '';
            foreach ($body->childNodes as $child) {
                $markup = $document->saveHTML($child);
                if ($markup === false) {
                    throw new RuntimeException('Unable to serialize the project description.');
                }

                $html .= $markup;
            }

            return trim($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    public function plainText(string $content): string
    {
        $html = $this->sanitize($content);
        $withLineBreaks = preg_replace(
            '/<br\s*\/?>|<\/(?:p|div|li|h[2-4]|blockquote)>/i',
            "\n",
            $html,
        );

        return trim(html_entity_decode(strip_tags($withLineBreaks ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function sanitizeNode(DOMNode $node): void
    {
        if ($node instanceof DOMComment || (! $node instanceof DOMElement && ! $node instanceof DOMText)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node instanceof DOMText) {
            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DISCARDED_ELEMENTS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $parent = $node->parentNode;
        if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $this->sanitizeNode($child);
            }

            if ($parent !== null) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }

            return;
        }

        while ($node->attributes->length > 0) {
            $attribute = $node->attributes->item(0);
            if ($attribute === null) {
                break;
            }

            $node->removeAttributeNode($attribute);
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $this->sanitizeNode($child);
        }
    }

    private function plainTextToHtml(string $content): string
    {
        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/', trim($content)) ?: [];
        $html = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $escaped = str_replace(["\r\n", "\r", "\n"], '<br>', $escaped);
            $html[] = '<p>'.$escaped.'</p>';
        }

        return implode('', $html);
    }
}

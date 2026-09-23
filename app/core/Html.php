<?php
declare(strict_types=1);

namespace Cros\Core;

use DOMDocument;
use DOMElement;

/**
 * Sanejador d'HTML per als textos editables des del panell d'administració.
 * Només es permeten etiquetes bàsiques de format.
 */
class Html
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'small' => [], 'span' => ['class'], 'div' => ['class'],
        'a' => ['href', 'title', 'target', 'rel'], 'hr' => [], 'table' => ['class'],
        'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'figure' => [], 'figcaption' => [],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
    ];

    /** Neteja una cadena d'HTML deixant només etiquetes segures. */
    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        if (!str_contains($html, '<')) {
            // Text pla: es converteix en paràgrafs.
            return self::paragraphs($html);
        }
        if (!class_exists(DOMDocument::class)) {
            return strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><h4><a>');
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="cros-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('cros-root');
        if (!$root) {
            return strip_tags($html);
        }
        self::sanitizeNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);
                if (!array_key_exists($tag, self::ALLOWED)) {
                    // Etiqueta no permesa: es conserva el contingut de text.
                    self::sanitizeNode($child);
                    $fragment = $child->ownerDocument->createDocumentFragment();
                    while ($child->firstChild) {
                        $fragment->appendChild($child->firstChild);
                    }
                    $child->parentNode->replaceChild($fragment, $child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                    $name = strtolower($attribute->nodeName);
                    if (!in_array($name, self::ALLOWED[$tag], true)) {
                        $child->removeAttribute($attribute->nodeName);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attribute->nodeValue)) {
                        $child->removeAttribute($attribute->nodeName);
                    }
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                self::sanitizeNode($child);
            } elseif ($child instanceof \DOMComment) {
                $child->parentNode->removeChild($child);
            }
        }
    }

    /** L'adreça és segura per posar-la en un enllaç o en una imatge? */
    public static function safeUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $url)) {
            return true;
        }
        return !preg_match('#^[a-z0-9.+-]*:#i', $url);
    }

    /** Converteix text pla amb salts de línia en paràgrafs HTML. */
    public static function paragraphs(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $blocks = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text)) ?: [];
        $out = '';
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $out .= '<p>' . nl2br(htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

/**
 * El text amb format dels blocs del correu (paràgrafs, negreta, llistes,
 * enllaços…).
 *
 * L'HTML que arriba del navegador no és de fiar: es reconstrueix només amb una
 * llista tancada d'etiquetes, sense cap atribut excepte l'adreça dels enllaços
 * (http, https o mailto, o bé un camp com {{web}}). Tota la resta (scripts,
 * estils, imatges, esdeveniments…) es descarta; el text sempre es conserva.
 *
 * És el mateix filtre que fa servir el Mailing del Tauler de Gestió, sense la
 * part de taules i imatges, que en un correu no hi van dins del text.
 */
final class RichText
{
    /** Etiqueta acceptada => etiqueta amb què es desa. */
    private const TAGS = [
        'p' => 'p', 'div' => 'p', 'br' => 'br',
        'strong' => 'strong', 'b' => 'strong',
        'em' => 'em', 'i' => 'em',
        'u' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's',
        'h1' => 'h3', 'h2' => 'h3', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h4', 'h6' => 'h4',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'blockquote' => 'blockquote',
        'a' => 'a',
    ];

    /** Elements que es descarten amb tot el que contenen. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math',
        'head', 'title', 'meta', 'link', 'form', 'input', 'button', 'select', 'textarea', 'img', 'video', 'audio'];

    /** Blocs que no es poden niuar (un paràgraf dins d'un altre es desfà). */
    private const BLOCKS = ['p', 'h3', 'h4'];

    public const MAX_LENGTH = 100000;

    /** HTML net, o '' si no hi queda text. */
    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $html = mb_substr($html, 0, self::MAX_LENGTH * 2);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="rt-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('rt-root');
        if (!$root) {
            return '';
        }
        $out = trim(self::children($root));
        // Paràgrafs i elements de llista buits (els deixa l'editor) fora.
        $out = trim((string) preg_replace('#<(p|li|h3|h4|blockquote)>(\s|&nbsp;|<br>)*</\1>#u', '', $out));

        return trim(strip_tags($out)) === '' ? '' : mb_substr($out, 0, self::MAX_LENGTH);
    }

    /** Text pla (la versió de text del correu). */
    public static function text(?string $html): string
    {
        if (!$html) {
            return '';
        }
        $html = (string) preg_replace('#<li>#', '• ', $html);
        $html = (string) preg_replace('#</(p|h3|h4|li|blockquote|ul|ol)>|<br>#', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function children(\DOMNode $node, bool $inBlock = false): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child, $inBlock);
        }

        return $out;
    }

    private static function node(\DOMNode $node, bool $inBlock): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
        }
        if (!$node instanceof \DOMElement) {
            return ''; // comentaris, instruccions…
        }
        $name = strtolower($node->nodeName);
        // Etiquetes amb prefix (o:p de Word, w:…): només el text.
        if (str_contains($name, ':') || ($node->prefix ?? '') !== '') {
            return self::children($node, $inBlock);
        }
        if (in_array($name, self::DROP, true)) {
            return '';
        }
        $tag = self::TAGS[$name] ?? null;
        $isBlock = in_array($tag, self::BLOCKS, true);
        $inner = self::children($node, $inBlock || $isBlock);
        if ($isBlock && $inBlock) {
            return $inner;
        }
        if ($tag === null) {
            // Etiqueta no admesa (span, font, table…): es queda el text.
            return in_array($name, ['tr', 'table', 'section', 'article', 'header', 'footer'], true) ? $inner . ' ' : $inner;
        }
        if ($tag === 'br') {
            return '<br>';
        }
        if ($tag === 'a') {
            $href = trim((string) $node->getAttribute('href'));
            if (!preg_match('#^(https?://|mailto:|\{\{[a-z]+\}\})#i', $href)) {
                return $inner;
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_HTML401, 'UTF-8') . '">' . $inner . '</a>';
        }
        // Un paràgraf que conté llistes o altres blocs (Chrome ho fa) es desfà.
        if ($tag === 'p' && preg_match('#<(ul|ol|blockquote|h3|h4)>#', $inner)) {
            return $inner;
        }
        if (in_array($tag, ['ul', 'ol'], true) && !str_contains($inner, '<li>')) {
            return $inner === '' ? '' : '<p>' . $inner . '</p>';
        }

        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    }
}

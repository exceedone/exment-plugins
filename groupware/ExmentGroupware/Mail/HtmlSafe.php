<?php

namespace App\Plugins\ExmentGroupware\Mail;

/**
 * Making the HTML of a mail safe to show.
 *
 * Two layers. Here: scripts, frames, forms, event handlers and javascript:
 * links are removed, links open in a new tab, the mail's own images
 * (cid:) are put inline. In the browser: the mail is shown in a sandboxed
 * iframe without scripts, with a Content-Security-Policy that loads nothing
 * from outside until the user presses "画像を表示" - a remote image is how
 * a sender learns that, when and where the mail was opened.
 */
class HtmlSafe
{
    const DROP = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'base', 'meta', 'link', 'audio', 'video', 'source', 'track', 'portal', 'svg', 'math', 'template', 'noscript'];

    const URL_ATTRS = ['href', 'src', 'action', 'background', 'poster', 'formaction', 'xlink:href', 'srcset', 'lowsrc', 'dynsrc'];

    /**
     * @param array<string, string> $cid Content-ID => data: URL
     * @param bool $keepStyleBlocks false for HTML that goes into the page itself (a quote in the editor)
     * @param bool $strict only a short list of attributes, no remote images
     *                     (a quote in the editor: it is in the page, not in the sandbox)
     * @return array{html: string, remote: bool}
     */
    public static function clean(string $html, array $cid = [], bool $keepStyleBlocks = true, bool $strict = false): array
    {
        $remote = false;
        if (trim($html) === '') {
            return ['html' => '', 'remote' => false];
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // The XML declaration tells libxml the text is UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $drop = static::DROP;
        if (!$keepStyleBlocks) {
            $drop[] = 'style';
            $drop[] = 'title';
            $drop[] = 'head';
        }
        $remove = [];
        foreach ($drop as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $node) {
                $remove[] = $node;
            }
        }
        foreach ($remove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
        // Comments can hide conditional markup for old Outlook.
        $xpath = new \DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $c) {
            if ($c->parentNode) {
                $c->parentNode->removeChild($c);
            }
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $el) {
            if (!$el instanceof \DOMElement) {
                continue;
            }
            if ($strict && !static::strictElement($el)) {
                continue;
            }
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $name = strtolower($attr->nodeName);
                $value = strval($attr->nodeValue);
                if (strpos($name, 'on') === 0) {
                    $el->removeAttribute($attr->nodeName);
                    continue;
                }
                if (in_array($name, static::URL_ATTRS, true)) {
                    $v = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? '');
                    if (preg_match('/^(javascript|vbscript|data:text\/html|livescript):?/', $v) === 1 || strpos($v, 'javascript:') !== false) {
                        $el->removeAttribute($attr->nodeName);
                        continue;
                    }
                    if ($name === 'srcset') {
                        $el->removeAttribute($attr->nodeName);
                        continue;
                    }
                    if (strpos($v, 'cid:') === 0) {
                        $key = trim(substr($value, strpos($value, ':') + 1), '<> ');
                        if (isset($cid[$key])) {
                            $el->setAttribute($attr->nodeName, $cid[$key]);
                        } else {
                            $el->removeAttribute($attr->nodeName);
                        }
                        continue;
                    }
                    if ($name !== 'href' && preg_match('#^(https?:)?//#', $v) === 1) {
                        $remote = true;
                    }
                }
                if ($name === 'style' && preg_match('/url\s*\(/i', $value) === 1) {
                    $remote = $remote || preg_match('/url\s*\(\s*[\'"]?\s*(https?:)?\/\//i', $value) === 1;
                }
                if ($name === 'style' && preg_match('/expression\s*\(|behavior\s*:|-moz-binding/i', $value) === 1) {
                    $el->removeAttribute($attr->nodeName);
                }
            }
            if (strtolower($el->nodeName) === 'a' && $el->hasAttribute('href')) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
        if ($keepStyleBlocks) {
            foreach ($doc->getElementsByTagName('style') as $style) {
                if (preg_match('/url\s*\(\s*[\'"]?\s*(https?:)?\/\//i', strval($style->textContent)) === 1 || stripos(strval($style->textContent), '@import') !== false) {
                    $remote = true;
                }
            }
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        if ($keepStyleBlocks) {
            foreach ($doc->getElementsByTagName('style') as $style) {
                if ($body === null || !static::inside($style, $body)) {
                    $out .= $doc->saveHTML($style);
                }
            }
        }
        if ($body !== null) {
            foreach ($body->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        } else {
            $out .= strval($doc->saveHTML());
        }

        return ['html' => $out, 'remote' => $remote];
    }

    const STRICT_ATTRS = ['href', 'src', 'alt', 'title', 'style', 'colspan', 'rowspan', 'align', 'valign', 'width', 'height', 'border', 'cellpadding', 'cellspacing', 'bgcolor', 'color', 'face', 'size', 'dir', 'lang', 'start', 'type', 'target', 'rel'];

    /**
     * For the strict mode: keep only plain attributes (no id, class, data-*
     * or name that the page's own script or styles could pick up), keep
     * styles that cannot cover the page or load anything, and put the alt
     * text in place of an image that is not inside the mail.
     *
     * @return bool false when the element was replaced
     */
    protected static function strictElement(\DOMElement $el): bool
    {
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, static::STRICT_ATTRS, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }
            if ($name === 'style') {
                $value = strval($attr->nodeValue);
                if (preg_match('/url\s*\(|expression\s*\(|behavior\s*:|-moz-binding|@import|position\s*:|z-index|\\\\/i', $value) === 1) {
                    $el->removeAttribute($attr->nodeName);
                }
            }
        }
        if (strtolower($el->nodeName) === 'img') {
            $src = strtolower(trim(strval($el->getAttribute('src'))));
            if (strpos($src, 'data:image/') !== 0) {
                $alt = trim(strval($el->getAttribute('alt')));
                $text = $el->ownerDocument !== null ? $el->ownerDocument->createTextNode($alt !== '' ? '[' . $alt . ']' : '') : null;
                if ($el->parentNode !== null) {
                    if ($text !== null) {
                        $el->parentNode->replaceChild($text, $el);
                    } else {
                        $el->parentNode->removeChild($el);
                    }
                }

                return false;
            }
        }
        if ($el->hasAttribute('background')) {
            $el->removeAttribute('background');
        }

        return true;
    }

    protected static function inside(\DOMNode $node, \DOMNode $parent): bool
    {
        for ($n = $node->parentNode; $n !== null; $n = $n->parentNode) {
            if ($n->isSameNode($parent)) {
                return true;
            }
        }

        return false;
    }

    /** Plain text as HTML: escaped, line breaks kept, links clickable. */
    public static function textToHtml(string $text): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace_callback('#\bhttps?://[^\s<>"\']+#u', function ($m) {
            return '<a href="' . $m[0] . '" target="_blank" rel="noopener noreferrer">' . $m[0] . '</a>';
        }, $html) ?? $html;

        return '<div style="white-space:pre-wrap;word-break:break-word">' . $html . '</div>';
    }
}

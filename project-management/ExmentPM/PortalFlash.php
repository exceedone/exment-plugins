<?php

namespace App\Plugins\ExmentPM;

/**
 * The messages Exment leaves for the next page (admin_error(), admin_success(), admin_warning(),
 * admin_info()), drawn by the portal itself (_flash.blade.php).
 *
 * The usual one is Exment's answer to a form sent with an expired or missing token: it goes back
 * to the page with 「ページの有効期限が切れました。…」 in the session. Exment draws these in its
 * content frame, but on the portal's screens nobody saw it, so the portal shows them in its own
 * box under the top bar, and hides Exment's copy of the same messages there.
 */
class PortalFlash
{
    /** The session keys admin_error() and its siblings write, in the order they are shown. */
    const KEYS = ['error', 'warning', 'success', 'info'];

    /** A message longer than this is cut (the log keeps it in full). */
    const MAX = 1000;

    /**
     * The messages of this request, read from the session.
     *
     * @return array<int, array{type: string, title: string, message: string}>
     */
    public static function messages(): array
    {
        try {
            $session = session();
        } catch (\Throwable $e) {
            return [];
        }
        $values = [];
        foreach (static::KEYS as $key) {
            $values[$key] = $session->get($key);
        }

        return static::fromValues($values);
    }

    /**
     * The messages in the values of the session keys: a MessageBag (title, message), as
     * admin_error() writes it, or a plain string. Text only: the page writes it escaped.
     *
     * @param array<string, mixed> $values
     * @return array<int, array{type: string, title: string, message: string}>
     */
    public static function fromValues(array $values): array
    {
        $out = [];
        foreach (static::KEYS as $key) {
            $value = $values[$key] ?? null;
            $title = '';
            $message = '';
            if (is_string($value)) {
                $message = $value;
            } elseif (is_object($value) && method_exists($value, 'get')) {
                $title = static::first($value->get('title'));
                $message = static::first($value->get('message'));
            } else {
                continue;
            }
            $title = static::text($title);
            $message = static::text($message);
            if ($title === '' && $message === '') {
                continue;
            }
            $out[] = ['type' => $key, 'title' => $title, 'message' => $message];
        }

        return $out;
    }

    /**
     * @param mixed $value
     */
    protected static function first($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? strval($value) : '';
    }

    /**
     * A message as plain text: Exment's alert prints it as HTML, so a line break in it stays a
     * line break and the rest of the markup goes.
     */
    protected static function text(string $html): string
    {
        $text = preg_replace('#<br\s*/?>|</p>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/[ \t]*\n\s*/u", "\n", $text) ?? $text);
        if (mb_strlen($text) > static::MAX) {
            $text = mb_substr($text, 0, static::MAX) . '…';
        }

        return $text;
    }
}

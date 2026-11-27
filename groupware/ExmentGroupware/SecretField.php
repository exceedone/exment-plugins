<?php

namespace App\Plugins\ExmentGroupware;

use ExmentAdminCore\Admin\Form\Field\Password;

/**
 * A secret of the plugin's settings form (SecretOption): the stored value is never written into
 * the page, neither decrypted nor encrypted. The field is always empty; its help says whether a
 * value is set (「設定済み」 / "Set"), with a check box to remove it.
 *
 *   typed in              the new value, encrypted (SecretOption::seal)
 *   left empty            the stored value is kept (and encrypted, if it was not yet)
 *   empty + "remove"      the value is removed
 *
 * Add it to an embedded form (the plugin's custom_options) with pushField:
 *
 *   $form->pushField(new SecretField('ms_client_secret', ['クライアント シークレット']));
 *
 * The check box is sent as <name>_clear (custom_options[ms_client_secret_clear]); it is not a
 * field of the form, so it is never saved.
 */
class SecretField extends Password
{
    /** @var bool whether a value is stored */
    protected $isSet = false;

    /**
     * The stored value is only looked at, never kept for the page.
     *
     * @return void
     */
    protected function formatValue()
    {
        $this->isSet = SecretOption::has($this->value);
        $this->value = null;
    }

    /**
     * @param mixed $value what was typed
     * @return mixed
     */
    public function prepare($value)
    {
        return SecretOption::submitted($value, $this->original, $this->clearRequested());
    }

    public function render()
    {
        $this->value = null;
        $this->attribute('value', '');
        $this->attribute('autocomplete', 'new-password');
        $this->placeholder(static::t($this->isSet ? 'placeholder_set' : 'placeholder_not_set'));
        $this->help(static::helpHtml($this->isSet, $this->clearName()), $this->isSet ? 'fa-check' : 'fa-info-circle');

        return parent::render();
    }

    /**
     * The help under the field: set or not, and the check box to remove a value that is set.
     */
    public static function helpHtml(bool $isSet, string $clearName): string
    {
        if (!$isSet) {
            return e(static::t('not_set')) . ' ' . e(static::t('help_not_set'));
        }

        return '<strong>' . e(static::t('set')) . '</strong> ' . e(static::t('help_set'))
            . ' <label style="font-weight:normal;margin-left:1em;"><input type="checkbox" name="' . e($clearName) . '" value="1"> '
            . e(static::t('clear')) . '</label>';
    }

    /** custom_options[ms_client_secret] => custom_options[ms_client_secret_clear] */
    public static function clearNameOf(string $elementName): string
    {
        if (substr($elementName, -1) === ']') {
            return substr($elementName, 0, -1) . '_clear]';
        }

        return $elementName . '_clear';
    }

    /** custom_options[ms_client_secret_clear] => custom_options.ms_client_secret_clear */
    public static function dotted(string $name): string
    {
        return str_replace(['[', ']'], ['.', ''], $name);
    }

    protected function clearName(): string
    {
        $name = $this->getElementName();

        return static::clearNameOf(is_string($name) && $name !== '' ? $name : strval($this->column));
    }

    protected function clearRequested(): bool
    {
        try {
            return boolval(request()->input(static::dotted($this->clearName())));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** ja / en (resources/lang, groupware.secret), Japanese when the words are not loaded. */
    public static function t(string $key): string
    {
        $k = 'exment_exment_groupware::groupware.secret.' . $key;
        try {
            $v = trans($k);
            if (is_string($v) && $v !== $k) {
                return $v;
            }
        } catch (\Throwable $e) {
            // the words below
        }

        return static::JA[$key] ?? $key;
    }

    const JA = [
        'set' => '設定済み',
        'not_set' => '未設定',
        'help_set' => '保存した値は表示しません。変えるときだけ新しい値を入れます。空のまま保存すると今の値のままです。',
        'help_not_set' => '値を入れて保存します。保存した値は暗号化して保存し、あとで表示しません。',
        'placeholder_set' => '設定済み（変えるときだけ入力）',
        'placeholder_not_set' => '',
        'clear' => '値を消す',
    ];
}

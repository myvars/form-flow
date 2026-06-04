<?php

namespace MyVars\FormFlow\Guard;

use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Detects clicks on a dedicated `auto-update` submit and can clear form errors.
 */
final class AutoUpdateGuard
{
    private const string FIELD = 'auto-update';

    /**
     * True when the `auto-update` button exists and was clicked.
     *
     * @param FormInterface<mixed> $form
     */
    public function is(FormInterface $form): bool
    {
        if (!$form->has(self::FIELD)) {
            return false;
        }

        $button = $form->get(self::FIELD);

        return $button instanceof ClickableInterface && $button->isClicked();
    }

    /**
     * Clears current form errors when in auto-update mode, if supported by the form.
     *
     * @param FormInterface<mixed> $form
     */
    public function clear(FormInterface $form): void
    {
        if ($this->is($form) && \method_exists($form, 'clearErrors')) {
            $form->clearErrors(true);
        }
    }
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Block\Frontend;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\AdvancedContactUs\ViewModel\ContactForm;

/**
 * Renders the contact form and guarantees the hidden anti-spam fields are inside
 * the <form>, even when a theme overrides the template and forgets them.
 */
class Form extends Template
{
    public function __construct(
        Context $context,
        private readonly ContactForm $contactForm,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        $html = parent::_toHtml();

        if ($html === '' || str_contains($html, ContactForm::MARKER)) {
            return $html;
        }

        $fields = $this->contactForm->getAntiSpamFieldsHtml();
        if ($fields === '') {
            return $html;
        }

        return preg_replace_callback(
            '~<form\b[^>]*>~i',
            static fn (array $m): string => $m[0] . $fields,
            $html,
            1
        ) ?? $html;
    }
}

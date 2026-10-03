<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Panth\AdvancedContactUs\Model\Config;

class ContactForm implements ArgumentInterface
{
    public const HONEYPOT_FIELD = 'website_url';

    public const TIMESTAMP_FIELD = '_timestamp';

    public const MARKER = 'data-panth-antispam';

    private Config $config;
    private RequestInterface $request;
    private FormKey $formKey;
    private CustomerSession $customerSession;
    private UrlInterface $urlBuilder;

    public function __construct(
        Config $config,
        RequestInterface $request,
        FormKey $formKey,
        CustomerSession $customerSession,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->formKey = $formKey;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getFormAction(): string
    {
        return $this->urlBuilder->getUrl('contact/index/post', ['_secure' => true]);
    }

    public function getUserName(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getName();
        }
        return '';
    }

    public function getUserEmail(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getEmail();
        }
        return '';
    }

    public function getCustomFieldsJson(): string
    {
        return json_encode($this->config->getCustomFields());
    }

    public function getTimestamp(): int
    {
        return time();
    }

    public function getHoneypotFieldName(): string
    {
        return self::HONEYPOT_FIELD;
    }

    /**
     * Every hidden anti-spam field the submit controller expects, ready to echo
     * inside the <form>. A custom template override only has to call this once.
     */
    public function getAntiSpamFieldsHtml(): string
    {
        $html = '';

        if ($this->config->isHoneypotEnabled()) {
            $html .= '<div class="panth-cf-hp" aria-hidden="true"'
                . ' style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">'
                . '<label for="panth-cf-hp">' . __('Leave this field empty') . '</label>'
                . '<input type="text" id="panth-cf-hp" name="' . self::HONEYPOT_FIELD . '" value=""'
                . ' tabindex="-1" autocomplete="off"/>'
                . '</div>';
        }

        if ($this->config->getMinTime() > 0) {
            $html .= '<input type="hidden" name="' . self::TIMESTAMP_FIELD . '" value="' . $this->getTimestamp() . '"/>';
        }

        return $html === '' ? '' : '<div data-panth-antispam="1">' . $html . '</div>';
    }
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Customer\Model\Session as CustomerSession;
use Panth\AdvancedContactUs\Model\Config;

class ContactForm implements ArgumentInterface
{
    private Config $config;
    private RequestInterface $request;
    private FormKey $formKey;
    private CustomerSession $customerSession;

    public function __construct(
        Config $config,
        RequestInterface $request,
        FormKey $formKey,
        CustomerSession $customerSession
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->formKey = $formKey;
        $this->customerSession = $customerSession;
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
        return '/contact/index/post';
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
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getPageTitle(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/general/page_title', ScopeInterface::SCOPE_STORE) ?: 'Contact Us';
    }

    public function getSuccessMessage(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/general/success_message', ScopeInterface::SCOPE_STORE);
    }

    public function showInfo(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/general/show_info', ScopeInterface::SCOPE_STORE);
    }

    public function getContactEmail(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/contact_info/email', ScopeInterface::SCOPE_STORE) ?: 'hello@example.com';
    }

    public function getContactPhone(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/contact_info/phone', ScopeInterface::SCOPE_STORE);
    }

    public function getContactAddress(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/contact_info/address', ScopeInterface::SCOPE_STORE);
    }

    public function getContactHours(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/contact_info/hours', ScopeInterface::SCOPE_STORE);
    }

    public function showPhone(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/fields/show_phone', ScopeInterface::SCOPE_STORE);
    }

    public function isPhoneRequired(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/fields/phone_required', ScopeInterface::SCOPE_STORE);
    }

    public function showSubject(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/fields/show_subject', ScopeInterface::SCOPE_STORE);
    }

    public function isSubjectRequired(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/fields/subject_required', ScopeInterface::SCOPE_STORE);
    }

    public function getCustomFields(): array
    {
        $value = $this->scopeConfig->getValue('panth_advancedcontact/fields/custom_fields', ScopeInterface::SCOPE_STORE);
        if (empty($value)) {
            return [];
        }
        // ArraySerialized returns array, JSON string is fallback
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        // Filter out the __empty key that Magento adds
        return array_filter($value, function ($item) {
            return is_array($item) && !empty($item['label']);
        });
    }

    public function getRecipientEmail(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/email/recipient_email', ScopeInterface::SCOPE_STORE);
    }

    public function getSenderIdentity(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/email/sender_email_identity', ScopeInterface::SCOPE_STORE) ?: 'general';
    }

    public function getAdminTemplate(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/email/admin_template', ScopeInterface::SCOPE_STORE);
    }

    public function sendConfirmation(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/email/send_confirmation', ScopeInterface::SCOPE_STORE);
    }

    public function getCustomerTemplate(): string
    {
        return (string) $this->scopeConfig->getValue('panth_advancedcontact/email/customer_template', ScopeInterface::SCOPE_STORE);
    }

    public function isHoneypotEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/protection/honeypot', ScopeInterface::SCOPE_STORE);
    }

    public function isRateLimitEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('panth_advancedcontact/protection/rate_limit', ScopeInterface::SCOPE_STORE);
    }

    public function getMaxPerHour(): int
    {
        return (int) ($this->scopeConfig->getValue('panth_advancedcontact/protection/max_per_hour', ScopeInterface::SCOPE_STORE) ?: 5);
    }

    public function getMinTime(): int
    {
        return (int) ($this->scopeConfig->getValue('panth_advancedcontact/protection/min_time', ScopeInterface::SCOPE_STORE) ?: 2);
    }
}

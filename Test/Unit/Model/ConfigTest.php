<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\AdvancedContactUs\Model\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values = [], array $flags = []): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path, $scope = null) => $scope === ScopeInterface::SCOPE_STORE
                ? ($values[$path] ?? null)
                : 'wrong-scope'
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path, $scope = null) => $scope === ScopeInterface::SCOPE_STORE
                && !empty($flags[$path])
        );

        return new Config($scopeConfig);
    }

    public static function flagProvider(): array
    {
        return [
            ['isEnabled', 'panth_advancedcontact/general/enabled'],
            ['showInfo', 'panth_advancedcontact/general/show_info'],
            ['showPhone', 'panth_advancedcontact/fields/show_phone'],
            ['isPhoneRequired', 'panth_advancedcontact/fields/phone_required'],
            ['showSubject', 'panth_advancedcontact/fields/show_subject'],
            ['isSubjectRequired', 'panth_advancedcontact/fields/subject_required'],
            ['sendConfirmation', 'panth_advancedcontact/email/send_confirmation'],
            ['isHoneypotEnabled', 'panth_advancedcontact/protection/honeypot'],
            ['isContentGuardEnabled', 'panth_advancedcontact/protection/content_guard'],
            ['isRateLimitEnabled', 'panth_advancedcontact/protection/rate_limit'],
        ];
    }

    #[DataProvider('flagProvider')]
    public function testFlagsReadTheirOwnStoreScopedPath(string $method, string $path): void
    {
        $this->assertTrue($this->config([], [$path => true])->$method());
        $this->assertFalse($this->config([], [])->$method());
    }

    public static function stringProvider(): array
    {
        return [
            ['getSuccessMessage', 'panth_advancedcontact/general/success_message'],
            ['getContactPhone', 'panth_advancedcontact/contact_info/phone'],
            ['getContactAddress', 'panth_advancedcontact/contact_info/address'],
            ['getContactHours', 'panth_advancedcontact/contact_info/hours'],
            ['getRecipientEmail', 'panth_advancedcontact/email/recipient_email'],
            ['getAdminTemplate', 'panth_advancedcontact/email/admin_template'],
            ['getCustomerTemplate', 'panth_advancedcontact/email/customer_template'],
            ['getBlockedTerms', 'panth_advancedcontact/protection/blocked_terms'],
        ];
    }

    #[DataProvider('stringProvider')]
    public function testStringSettingsAreReturnedOrEmpty(string $method, string $path): void
    {
        $this->assertSame('configured value', $this->config([$path => 'configured value'])->$method());
        $this->assertSame('', $this->config()->$method());
    }

    public static function defaultedProvider(): array
    {
        return [
            ['getPageTitle', 'panth_advancedcontact/general/page_title', 'Contact Us'],
            ['getContactEmail', 'panth_advancedcontact/contact_info/email', 'hello@example.com'],
            ['getSenderIdentity', 'panth_advancedcontact/email/sender_email_identity', 'general'],
        ];
    }

    #[DataProvider('defaultedProvider')]
    public function testDefaultedStringsFallBackWhenEmpty(string $method, string $path, string $default): void
    {
        $this->assertSame($default, $this->config()->$method());
        $this->assertSame($default, $this->config([$path => ''])->$method());
        $this->assertSame('custom', $this->config([$path => 'custom'])->$method());
    }

    public function testCustomFieldsAreEmptyWhenUnset(): void
    {
        $this->assertSame([], $this->config()->getCustomFields());
        $this->assertSame(
            [],
            $this->config(['panth_advancedcontact/fields/custom_fields' => ''])->getCustomFields()
        );
    }

    public function testCustomFieldsAreDecodedFromJsonAndUnlabelledRowsDropped(): void
    {
        $json = json_encode([
            'row_1' => ['label' => 'Company', 'type' => 'text'],
            'row_2' => ['label' => '', 'type' => 'text'],
            'row_3' => 'not-an-array',
            'row_4' => ['type' => 'select'],
            'row_5' => ['label' => 'Budget', 'type' => 'select', 'options' => 'Low,High'],
        ]);

        $fields = $this->config(['panth_advancedcontact/fields/custom_fields' => $json])->getCustomFields();

        $this->assertSame(['row_1', 'row_5'], array_keys($fields));
        $this->assertSame('Budget', $fields['row_5']['label']);
    }

    public function testCustomFieldsAcceptAnAlreadyDecodedArray(): void
    {
        $fields = $this->config([
            'panth_advancedcontact/fields/custom_fields' => [['label' => 'Order number']],
        ])->getCustomFields();

        $this->assertSame([['label' => 'Order number', 'key' => 'custom_order_number']], $fields);
    }

    public function testCustomFieldKeysAreUniqueWhenLabelsNormaliseToTheSameName(): void
    {
        $fields = $this->config([
            'panth_advancedcontact/fields/custom_fields' => [
                'a' => ['label' => 'Phone #'],
                'b' => ['label' => 'Phone ?'],
                'c' => ['label' => 'Phone _'],
                'd' => ['label' => 'Email'],
            ],
        ])->getCustomFields();

        $this->assertSame(
            ['custom_phone__', 'custom_phone___2', 'custom_phone___3', 'custom_email'],
            array_values(array_column($fields, 'key'))
        );
    }

    public function testInvalidCustomFieldsJsonYieldsNoFields(): void
    {
        $this->assertSame(
            [],
            $this->config(['panth_advancedcontact/fields/custom_fields' => '{broken'])->getCustomFields()
        );
        $this->assertSame(
            [],
            $this->config(['panth_advancedcontact/fields/custom_fields' => '"scalar"'])->getCustomFields()
        );
    }

    public function testConfirmationLimitsUseDefaultsWhenBlank(): void
    {
        $config = $this->config(['panth_advancedcontact/email/confirmation_max_per_ip' => '  ']);

        $this->assertSame(2, $config->getConfirmationMaxPerRecipient());
        $this->assertSame(5, $config->getConfirmationMaxPerIp());
    }

    public function testConfirmationLimitsAreClampedAtZero(): void
    {
        $config = $this->config([
            'panth_advancedcontact/email/confirmation_max_per_recipient' => '-3',
            'panth_advancedcontact/email/confirmation_max_per_ip' => '0',
        ]);

        $this->assertSame(0, $config->getConfirmationMaxPerRecipient());
        $this->assertSame(0, $config->getConfirmationMaxPerIp());
    }

    public function testConfirmationLimitsReadConfiguredValues(): void
    {
        $config = $this->config([
            'panth_advancedcontact/email/confirmation_max_per_recipient' => '7',
            'panth_advancedcontact/email/confirmation_max_per_ip' => '12',
        ]);

        $this->assertSame(7, $config->getConfirmationMaxPerRecipient());
        $this->assertSame(12, $config->getConfirmationMaxPerIp());
    }

    public function testMaxPerHourDefaultsToFiveWhenEmptyOrZero(): void
    {
        $this->assertSame(5, $this->config()->getMaxPerHour());
        $this->assertSame(
            5,
            $this->config(['panth_advancedcontact/protection/max_per_hour' => '0'])->getMaxPerHour()
        );
        $this->assertSame(
            9,
            $this->config(['panth_advancedcontact/protection/max_per_hour' => '9'])->getMaxPerHour()
        );
    }

    public function testMinTimeDefaultsToTwoButAllowsZero(): void
    {
        $path = 'panth_advancedcontact/protection/min_time';

        $this->assertSame(2, $this->config()->getMinTime());
        $this->assertSame(2, $this->config([$path => ' '])->getMinTime());
        $this->assertSame(0, $this->config([$path => '0'])->getMinTime());
        $this->assertSame(0, $this->config([$path => '-4'])->getMinTime());
        $this->assertSame(10, $this->config([$path => '10'])->getMinTime());
    }
}

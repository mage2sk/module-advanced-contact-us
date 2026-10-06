<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\ViewModel;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\ViewModel\ContactForm;
use PHPUnit\Framework\TestCase;

class ContactFormTest extends TestCase
{
    private Config $config;

    private function viewModel(bool $loggedIn = false, array $customFields = []): ContactForm
    {
        $this->config = $this->createStub(Config::class);
        $this->config->method('getCustomFields')->willReturn($customFields);

        $customer = new class extends Customer {
            public function __construct()
            {
                $this->_data = ['email' => 'jane@shop.test'];
            }

            public function getName()
            {
                return 'Jane Doe';
            }
        };

        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $session->method('getCustomer')->willReturn($customer);

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('fk123');

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => 'https://shop.test/' . $route
                . (!empty($params['_secure']) ? '?secure' : '')
        );

        return new ContactForm(
            $this->config,
            $this->createStub(RequestInterface::class),
            $formKey,
            $session,
            $url
        );
    }

    public function testExposesConfigAndFormKey(): void
    {
        $vm = $this->viewModel();

        $this->assertSame($this->config, $vm->getConfig());
        $this->assertSame('fk123', $vm->getFormKey());
    }

    public function testFormPostsToTheSecureContactPostRoute(): void
    {
        $this->assertSame('https://shop.test/contact/index/post?secure', $this->viewModel()->getFormAction());
    }

    public function testGuestsGetEmptyNameAndEmail(): void
    {
        $vm = $this->viewModel(false);

        $this->assertSame('', $vm->getUserName());
        $this->assertSame('', $vm->getUserEmail());
    }

    public function testLoggedInCustomersArePrefilled(): void
    {
        $vm = $this->viewModel(true);

        $this->assertSame('Jane Doe', $vm->getUserName());
        $this->assertSame('jane@shop.test', $vm->getUserEmail());
    }

    public function testCustomFieldsAreEncodedAsJson(): void
    {
        $fields = ['r1' => ['label' => 'Company', 'type' => 'text']];

        $this->assertSame(json_encode($fields), $this->viewModel(false, $fields)->getCustomFieldsJson());
        $this->assertSame('[]', $this->viewModel()->getCustomFieldsJson());
    }

    public function testHoneypotFieldNameIsTheConstant(): void
    {
        $this->assertSame(ContactForm::HONEYPOT_FIELD, $this->viewModel()->getHoneypotFieldName());
        $this->assertSame('website_url', ContactForm::HONEYPOT_FIELD);
    }
}

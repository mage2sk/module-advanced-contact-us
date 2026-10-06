<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\Model\Mail;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailTest extends TestCase
{
    private array $calls = [];
    private array $translation = [];
    private array $logged = [];

    private function mail(bool $sendConfirmation = true, ?\Exception $sendFailure = null): Mail
    {
        $this->calls = [];
        $this->translation = [];
        $this->logged = [];

        $transport = $this->createStub(TransportInterface::class);
        if ($sendFailure !== null) {
            $transport->method('sendMessage')->willThrowException($sendFailure);
        } else {
            $transport->method('sendMessage')->willReturnCallback(function () {
                $this->calls['sent'] = true;
            });
        }

        $builder = $this->createStub(TransportBuilder::class);
        $fluent = ['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFrom', 'addTo', 'setReplyTo'];
        foreach ($fluent as $method) {
            $builder->method($method)->willReturnCallback(function (...$args) use ($method, &$builder) {
                $this->calls[$method] = $args;
                return $builder;
            });
        }
        $builder->method('getTransport')->willReturn($transport);

        $state = $this->createStub(StateInterface::class);
        $state->method('suspend')->willReturnCallback(function () {
            $this->translation[] = 'suspend';
        });
        $state->method('resume')->willReturnCallback(function () {
            $this->translation[] = 'resume';
        });

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn('3');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createStub(Config::class);
        $config->method('getAdminTemplate')->willReturn('admin_tpl');
        $config->method('getCustomerTemplate')->willReturn('customer_tpl');
        $config->method('getSenderIdentity')->willReturn('support');
        $config->method('getRecipientEmail')->willReturn('owner@shop.test');
        $config->method('sendConfirmation')->willReturn($sendConfirmation);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('critical')->willReturnCallback(function ($message) {
            $this->logged[] = (string) $message;
        });

        return new Mail($builder, $state, $storeManager, $config, $logger);
    }

    public function testAdminNotificationIsAddressedToTheRecipientWithReplyToTheCustomer(): void
    {
        $this->mail()->sendAdminNotification(['name' => 'Ann', 'email' => 'ann@x.test', 'message' => 'Hi']);

        $this->assertSame('admin_tpl', $this->calls['setTemplateIdentifier'][0]);
        $this->assertSame(['area' => 'frontend', 'store' => 3], $this->calls['setTemplateOptions'][0]);
        $this->assertSame('support', $this->calls['setFrom'][0]);
        $this->assertSame('owner@shop.test', $this->calls['addTo'][0]);
        $this->assertSame(['ann@x.test', 'Ann'], array_slice($this->calls['setReplyTo'], 0, 2));
        $vars = $this->calls['setTemplateVars'][0];
        $this->assertInstanceOf(DataObject::class, $vars['data']);
        $this->assertSame('Hi', $vars['data']->getData('message'));
        $this->assertTrue($this->calls['sent']);
        $this->assertSame(['suspend', 'resume'], $this->translation);
    }

    public function testAdminNotificationFailureIsLoggedAndTranslationResumed(): void
    {
        $this->mail(true, new \RuntimeException('smtp down'))
            ->sendAdminNotification(['name' => 'Ann', 'email' => 'ann@x.test']);

        $this->assertSame(['Panth Contact admin email failed: smtp down'], $this->logged);
        $this->assertSame(['suspend', 'resume'], $this->translation);
    }

    public function testCustomerConfirmationIsSkippedWhenDisabled(): void
    {
        $this->mail(false)->sendCustomerConfirmation(['email' => 'ann@x.test']);

        $this->assertSame([], $this->calls);
        $this->assertSame([], $this->translation);
    }

    public function testCustomerConfirmationGoesToTheCustomerWithoutEchoingTheirMessage(): void
    {
        $this->mail()->sendCustomerConfirmation(['email' => 'ann@x.test', 'message' => 'secret text']);

        $this->assertSame('customer_tpl', $this->calls['setTemplateIdentifier'][0]);
        $this->assertSame('ann@x.test', $this->calls['addTo'][0]);
        $this->assertArrayNotHasKey('setReplyTo', $this->calls);
        $vars = $this->calls['setTemplateVars'][0];
        $this->assertSame([], $vars['data']->getData());
        $this->assertInstanceOf(StoreInterface::class, $vars['store']);
        $this->assertTrue($this->calls['sent']);
        $this->assertSame(['suspend', 'resume'], $this->translation);
    }

    public function testCustomerConfirmationFailureIsLogged(): void
    {
        $this->mail(true, new \RuntimeException('bounce'))->sendCustomerConfirmation(['email' => 'ann@x.test']);

        $this->assertSame(['Panth Contact customer email failed: bounce'], $this->logged);
        $this->assertSame(['suspend', 'resume'], $this->translation);
    }
}

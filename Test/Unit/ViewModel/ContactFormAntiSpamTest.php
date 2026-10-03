<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Panth\AdvancedContactUs\Model\Config;
use Panth\AdvancedContactUs\ViewModel\ContactForm;
use PHPUnit\Framework\TestCase;

class ContactFormAntiSpamTest extends TestCase
{
    private function viewModel(bool $honeypot, int $minTime): ContactForm
    {
        $config = $this->createStub(Config::class);
        $config->method('isHoneypotEnabled')->willReturn($honeypot);
        $config->method('getMinTime')->willReturn($minTime);

        return new ContactForm(
            $config,
            $this->createStub(RequestInterface::class),
            $this->createStub(FormKey::class),
            $this->createStub(CustomerSession::class),
            $this->createStub(UrlInterface::class)
        );
    }

    public function testBothLayersAreRenderedWhenEnabled(): void
    {
        $html = $this->viewModel(true, 2)->getAntiSpamFieldsHtml();

        $this->assertStringContainsString('name="' . ContactForm::HONEYPOT_FIELD . '"', $html);
        $this->assertStringContainsString('name="' . ContactForm::TIMESTAMP_FIELD . '"', $html);
        $this->assertStringContainsString(ContactForm::MARKER, $html);
    }

    public function testTheHoneypotIsHiddenFromPeopleAndAssistiveTech(): void
    {
        $html = $this->viewModel(true, 2)->getAntiSpamFieldsHtml();

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('left:-9999px', $html);
    }

    public function testOnlyTheTimestampIsRenderedWhenTheHoneypotIsOff(): void
    {
        $html = $this->viewModel(false, 2)->getAntiSpamFieldsHtml();

        $this->assertStringNotContainsString('name="' . ContactForm::HONEYPOT_FIELD . '"', $html);
        $this->assertStringContainsString('name="' . ContactForm::TIMESTAMP_FIELD . '"', $html);
    }

    public function testOnlyTheHoneypotIsRenderedWhenTimingIsOff(): void
    {
        $html = $this->viewModel(true, 0)->getAntiSpamFieldsHtml();

        $this->assertStringContainsString('name="' . ContactForm::HONEYPOT_FIELD . '"', $html);
        $this->assertStringNotContainsString('name="' . ContactForm::TIMESTAMP_FIELD . '"', $html);
    }

    public function testNothingIsRenderedWhenBothLayersAreOff(): void
    {
        $this->assertSame('', $this->viewModel(false, 0)->getAntiSpamFieldsHtml());
    }

    public function testTheTimestampIsTheCurrentTime(): void
    {
        $html = $this->viewModel(false, 2)->getAntiSpamFieldsHtml();

        preg_match('~name="' . ContactForm::TIMESTAMP_FIELD . '" value="(\d+)"~', $html, $m);
        $this->assertNotEmpty($m, 'the timestamp must carry a value');
        $this->assertEqualsWithDelta(time(), (int) $m[1], 5);
    }

    public function testTheFieldNameMatchesWhatTheControllerReads(): void
    {
        $this->assertSame('website_url', ContactForm::HONEYPOT_FIELD);
        $this->assertSame('_timestamp', ContactForm::TIMESTAMP_FIELD);
        $this->assertSame('website_url', $this->viewModel(true, 2)->getHoneypotFieldName());
    }
}

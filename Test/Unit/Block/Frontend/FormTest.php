<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Block\Frontend;

use Magento\Framework\View\Element\Template\Context;
use Panth\AdvancedContactUs\Block\Frontend\Form;
use Panth\AdvancedContactUs\ViewModel\ContactForm;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{
    private const FIELDS = '<div data-panth-antispam="1"><input name="_timestamp"/></div>';

    private function render(string $templateHtml, string $fields = self::FIELDS, string $template = 'form.phtml'): string
    {
        $viewModel = $this->createStub(ContactForm::class);
        $viewModel->method('getAntiSpamFieldsHtml')->willReturn($fields);

        $block = new class ($this->createStub(Context::class), $viewModel, ['template' => $template]) extends Form {
            public string $templateHtml = '';

            public function getTemplateFile($template = null)
            {
                return '/virtual/form.phtml';
            }

            public function fetchView($fileName)
            {
                return $this->templateHtml;
            }

            public function renderForTest(): string
            {
                return $this->_toHtml();
            }
        };
        $block->templateHtml = $templateHtml;

        return $block->renderForTest();
    }

    public function testFieldsAreInjectedRightAfterTheOpeningFormTag(): void
    {
        $html = $this->render('<div><FORM class="x" method="post"><input name="name"/></FORM></div>');

        $this->assertSame(
            '<div><FORM class="x" method="post">' . self::FIELDS . '<input name="name"/></FORM></div>',
            $html
        );
    }

    public function testOnlyTheFirstFormReceivesTheFields(): void
    {
        $html = $this->render('<form id="a"></form><form id="b"></form>');

        $this->assertSame(1, substr_count($html, ContactForm::MARKER));
        $this->assertStringStartsWith('<form id="a">' . self::FIELDS, $html);
    }

    public function testTemplatesThatAlreadyRenderTheFieldsAreLeftAlone(): void
    {
        $template = '<form>' . self::FIELDS . '</form>';

        $this->assertSame($template, $this->render($template));
    }

    public function testNothingIsInjectedWhenProtectionIsOff(): void
    {
        $this->assertSame('<form></form>', $this->render('<form></form>', ''));
    }

    public function testMarkupWithoutAFormIsUnchanged(): void
    {
        $this->assertSame('<p>closed</p>', $this->render('<p>closed</p>'));
    }

    public function testElementsMerelyStartingWithFormAreNotMatched(): void
    {
        $this->assertSame('<formatted></formatted>', $this->render('<formatted></formatted>'));
    }

    public function testEmptyTemplateOutputStaysEmpty(): void
    {
        $this->assertSame('', $this->render('irrelevant', self::FIELDS, ''));
    }
}

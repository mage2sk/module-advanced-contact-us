<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class FormTemplateStyleTest extends TestCase
{
    private const TEMPLATE = 'view/frontend/templates/form.phtml';

    private string $css = '';

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/' . self::TEMPLATE;
        $this->assertTrue(is_file($path), self::TEMPLATE . ' is missing');
        $source = (string) file_get_contents($path);
        $this->assertSame(1, preg_match('#<style>(.*?)</style>#s', $source, $match));
        $this->css = $match[1];
    }

    private function ruleBody(string $selector): string
    {
        $start = strpos($this->css, "\n" . $selector . ' {');
        $this->assertNotFalse($start, 'Selector not found: ' . $selector);
        $open = strpos($this->css, '{', (int) $start);
        $close = strpos($this->css, '}', (int) $open);

        return substr($this->css, (int) $open + 1, (int) $close - (int) $open - 1);
    }

    public function testInputsAreWhiteWithOnePixelBorderAndSixteenPixelText(): void
    {
        foreach (['.panth-cf-input', '.panth-cf-textarea', '.panth-cf-select'] as $selector) {
            $body = $this->ruleBody($selector);
            $this->assertStringContainsString(
                'border: 1px solid var(--contact-input-border, #D4D4D4);',
                $body,
                $selector
            );
            $this->assertStringContainsString('font-size: 16px;', $body, $selector);
            $this->assertStringContainsString('background: var(--contact-input-bg, #FFFFFF);', $body, $selector);
            $this->assertStringNotContainsString('#F9FAFB', $body, $selector);
        }
    }

    public function testFocusRingIsTwoPixelTeal(): void
    {
        foreach (['.panth-cf-input:focus', '.panth-cf-textarea:focus', '.panth-cf-select:focus'] as $selector) {
            $this->assertStringContainsString(
                'box-shadow: 0 0 0 2px var(--contact-input-focus, #0F766E);',
                $this->ruleBody($selector),
                $selector
            );
        }
    }

    public function testLabelsAndErrors(): void
    {
        $label = $this->ruleBody('.panth-cf-label');
        $this->assertStringContainsString('font-size: 14px;', $label);
        $this->assertStringContainsString('font-weight: 600;', $label);

        foreach (['.panth-cf-error', '.panth-cf-field-error'] as $selector) {
            $body = $this->ruleBody($selector);
            $this->assertStringContainsString('font-size: 14px;', $body, $selector);
            $this->assertStringContainsString('#B91C1C', $body, $selector);
        }
    }

    public function testSubmitButtonAndSpacing(): void
    {
        $submit = $this->ruleBody('.panth-cf-submit');
        $this->assertStringContainsString('height: 44px;', $submit);
        $this->assertStringContainsString('font-size: 15px; font-weight: 600;', $submit);
        $this->assertStringContainsString('.panth-cf-submit { height: 48px; }', $this->css);
        $this->assertStringContainsString('margin-bottom: 16px;', $this->ruleBody('.panth-cf-row'));
    }

    public function testTitleAndIntroFollowTheTypeScale(): void
    {
        $this->assertStringContainsString('font-size: 36px;', $this->ruleBody('.panth-contact-title'));
        $this->assertStringContainsString('.panth-contact-title { font-size: 28px; }', $this->css);

        $intro = $this->ruleBody('.panth-contact-subtitle');
        $this->assertStringContainsString('font-size: 16px;', $intro);
        $this->assertStringContainsString('max-width: min(72ch, 760px);', $intro);
    }
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Panth\AdvancedContactUs\Block\Adminhtml\Form\Field\CustomFields;
use PHPUnit\Framework\TestCase;

class CustomFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn($class) => $this->createStub($class));
        ObjectManager::setInstance($objectManager);
    }

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(ObjectManager::class, '_instance');
        $property->setValue(null, null);
    }

    private function block(): CustomFields
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventManager')->willReturn($this->createStub(EventManager::class));

        return new class (
            $context,
            [],
            $this->createStub(SecureHtmlRenderer::class)
        ) extends CustomFields {
            public function fetchView($fileName)
            {
                return '<table>grid</table>';
            }

            public function getTemplateFile($template = null)
            {
                return '/virtual/array.phtml';
            }

            public function renderForTest(): string
            {
                return $this->_toHtml();
            }

            public function addButtonLabel(): string
            {
                return (string) $this->_addButtonLabel;
            }
        };
    }

    public function testRendersTheFiveCustomFieldColumns(): void
    {
        $block = $this->block();
        $block->renderForTest();

        $columns = $block->getColumns();
        $this->assertSame(['label', 'type', 'required', 'placeholder', 'options'], array_keys($columns));
        $this->assertSame('required-entry', $columns['label']['class']);
        $this->assertSame('required-entry', $columns['type']['class']);
        $this->assertNull($columns['options']['class']);
        $this->assertSame('Field Label', (string) $columns['label']['label']);
        $this->assertFalse($block->isAddAfter());
        $this->assertSame('Add Custom Field', $block->addButtonLabel());
    }

    public function testGridIsWrappedInAScrollableContainer(): void
    {
        $html = $this->block()->renderForTest();

        $this->assertStringStartsWith('<div class="panth-acu-fieldarray"><table>grid</table></div>', $html);
        $this->assertStringContainsString('overflow-x:auto', $html);
    }
}

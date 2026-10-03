<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Index;

use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\AdvancedContactUs\Controller\Index\Index;
use Panth\AdvancedContactUs\Model\Config;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private array $handles = [];
    private ?string $title = null;

    private function executeWith(bool $enabled): Page
    {
        $this->handles = [];
        $this->title = null;

        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($value) {
            $this->title = (string) $value;
        });
        $pageConfig = $this->createStub(PageConfig::class);
        $pageConfig->method('getTitle')->willReturn($title);

        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($pageConfig);
        $page->method('addHandle')->willReturnCallback(function ($handle) use (&$page) {
            $this->handles[] = $handle;
            return $page;
        });
        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);

        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('getPageTitle')->willReturn('Get in touch');

        return (new Index($factory, $config))->execute();
    }

    public function testEnabledModuleAddsTheFormHandleAndTitle(): void
    {
        $result = $this->executeWith(true);

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['panth_advancedcontactus_form'], $this->handles);
        $this->assertSame('Get in touch', $this->title);
    }

    public function testDisabledModuleKeepsTheStockLayoutButSetsTheTitle(): void
    {
        $this->executeWith(false);

        $this->assertSame([], $this->handles);
        $this->assertSame('Get in touch', $this->title);
    }
}

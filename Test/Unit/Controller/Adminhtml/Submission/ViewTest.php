<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Panth\AdvancedContactUs\Controller\Adminhtml\Submission\View;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;

class ViewTest extends SubmissionControllerTestCase
{
    private array $statusWrites = [];
    private int $saves = 0;
    private array $titles = [];
    private ?string $menu = null;

    private function controller(array $params, ?int $foundId, int $status = 0): View
    {
        $this->statusWrites = [];
        $this->saves = 0;
        $this->titles = [];
        $this->menu = null;

        $submission = $this->createStub(Submission::class);
        $submission->method('getId')->willReturn($foundId);
        $submission->method('getData')->willReturnCallback(
            static fn($key = '') => $key === 'status' ? (string) $status : null
        );
        $submission->method('setData')->willReturnCallback(function ($key, $value = null) use (&$submission) {
            $this->statusWrites[$key] = $value;
            return $submission;
        });
        $factory = $this->createStub(SubmissionFactory::class);
        $factory->method('create')->willReturn($submission);

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('save')->willReturnCallback(function () use (&$resource) {
            $this->saves++;
            return $resource;
        });

        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($value) {
            $this->titles[] = (string) $value;
        });
        $pageConfig = $this->createStub(PageConfig::class);
        $pageConfig->method('getTitle')->willReturn($title);
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($pageConfig);
        $page->method('setActiveMenu')->willReturnCallback(function ($menu) use (&$page) {
            $this->menu = $menu;
            return $page;
        });
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        return new View($this->context($params), $pageFactory, $factory, $resource);
    }

    public function testMissingSubmissionRedirectsBackWithAnError(): void
    {
        $this->controller(['id' => '3'], null)->execute();

        $this->assertSame(['This submission no longer exists.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirectPath);
        $this->assertSame([], $this->titles);
    }

    public function testNewSubmissionIsMarkedReadWhenOpened(): void
    {
        $result = $this->controller(['id' => '8'], 8, Submission::STATUS_NEW)->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['status' => Submission::STATUS_READ], $this->statusWrites);
        $this->assertSame(1, $this->saves);
        $this->assertSame(['Submission #8'], $this->titles);
        $this->assertSame('Panth_AdvancedContactUs::submissions', $this->menu);
    }

    public function testAlreadyHandledSubmissionKeepsItsStatus(): void
    {
        foreach ([Submission::STATUS_READ, Submission::STATUS_REPLIED] as $status) {
            $this->controller(['id' => '8'], 8, $status)->execute();

            $this->assertSame([], $this->statusWrites);
            $this->assertSame(0, $this->saves);
            $this->assertSame(['Submission #8'], $this->titles);
        }
    }
}

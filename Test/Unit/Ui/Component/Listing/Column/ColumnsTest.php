<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Panth\AdvancedContactUs\Ui\Component\Listing\Column\Actions;
use Panth\AdvancedContactUs\Ui\Component\Listing\Column\Status;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    private function actions(): Actions
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => '/admin/' . $route . '/id/' . $params['id']
        );

        return new Actions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $url,
            [],
            ['name' => 'actions']
        );
    }

    private function statusColumn(): Status
    {
        return new Status(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            [],
            ['name' => 'status']
        );
    }

    public function testActionsAddViewAndConfirmedPostDeleteLinks(): void
    {
        $result = $this->actions()->prepareDataSource([
            'data' => ['items' => [['submission_id' => 4], ['other' => 'x']]],
        ]);

        $actions = $result['data']['items'][0]['actions'];
        $this->assertSame('/admin/panthcontact/submission/view/id/4', $actions['view']['href']);
        $this->assertSame('View', (string) $actions['view']['label']);
        $this->assertSame('/admin/panthcontact/submission/delete/id/4', $actions['delete']['href']);
        $this->assertTrue($actions['delete']['post']);
        $this->assertSame('Delete Submission', (string) $actions['delete']['confirm']['title']);
        $this->assertArrayNotHasKey('actions', $result['data']['items'][1]);
    }

    public function testActionsLeaveADataSourceWithoutItemsUntouched(): void
    {
        $source = ['data' => ['totalRecords' => 0]];

        $this->assertSame($source, $this->actions()->prepareDataSource($source));
    }

    public function testStatusIsRenderedAsABadge(): void
    {
        $result = $this->statusColumn()->prepareDataSource([
            'data' => ['items' => [
                ['status' => '0'],
                ['status' => 1],
                ['status' => '2'],
            ]],
        ]);

        $items = $result['data']['items'];
        $this->assertSame(
            '<span class="grid-severity-notice panth-contact-status-new"><span>New</span></span>',
            $items[0]['status']
        );
        $this->assertSame($items[0]['status'], $items[0]['status_label']);
        $this->assertStringContainsString('panth-contact-status-read', $items[1]['status']);
        $this->assertStringContainsString('<span>Read</span>', $items[1]['status']);
        $this->assertStringContainsString('<span>Replied</span>', $items[2]['status']);
    }

    public function testUnknownOrMissingStatusIsLeftAlone(): void
    {
        $result = $this->statusColumn()->prepareDataSource([
            'data' => ['items' => [['status' => '7'], ['name' => 'x']]],
        ]);

        $this->assertSame(['status' => '7'], $result['data']['items'][0]);
        $this->assertSame(['name' => 'x'], $result['data']['items'][1]);
    }

    public function testStatusLeavesADataSourceWithoutItemsUntouched(): void
    {
        $this->assertSame([], $this->statusColumn()->prepareDataSource([]));
    }
}

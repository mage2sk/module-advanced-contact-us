<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Ui\Component\MassAction\Filter;
use Panth\AdvancedContactUs\Controller\Adminhtml\Submission\MassDelete;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\Collection;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Panth\AdvancedContactUs\Model\Submission;

class MassDeleteTest extends SubmissionControllerTestCase
{
    private array $deleted = [];

    private function controller(array $items, ?\Exception $filterFailure = null, int $failOn = -1): MassDelete
    {
        $this->deleted = [];

        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $filter = $this->createStub(Filter::class);
        if ($filterFailure) {
            $filter->method('getCollection')->willThrowException($filterFailure);
        } else {
            $filter->method('getCollection')->willReturnArgument(0);
        }

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('delete')->willReturnCallback(function ($item) use ($failOn, &$resource) {
            if (count($this->deleted) === $failOn) {
                throw new \RuntimeException('cannot delete');
            }
            $this->deleted[] = $item;
            return $resource;
        });

        return new MassDelete($this->context(), $filter, $factory, $resource);
    }

    private function items(int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->createStub(Submission::class);
        }
        return $items;
    }

    public function testEverySelectedSubmissionIsDeletedAndCounted(): void
    {
        $items = $this->items(3);
        $this->controller($items)->execute();

        $this->assertSame($items, $this->deleted);
        $this->assertSame(['A total of 3 submission(s) have been deleted.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testEmptySelectionReportsZero(): void
    {
        $this->controller([])->execute();

        $this->assertSame(['A total of 0 submission(s) have been deleted.'], $this->messages['success']);
    }

    public function testFilterFailureIsReported(): void
    {
        $this->controller([], new \RuntimeException('bad selection'))->execute();

        $this->assertSame(['bad selection'], $this->messages['error']);
        $this->assertSame([], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testFailureMidwayStopsAndReportsTheError(): void
    {
        $this->controller($this->items(3), null, 1)->execute();

        $this->assertCount(1, $this->deleted);
        $this->assertSame(['cannot delete'], $this->messages['error']);
        $this->assertSame([], $this->messages['success']);
    }
}

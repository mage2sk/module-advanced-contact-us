<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\AdvancedContactUs\Controller\Adminhtml\Submission\MassStatus;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\Collection;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Panth\AdvancedContactUs\Model\Submission;

class MassStatusTest extends SubmissionControllerTestCase
{
    private array $saved = [];
    private array $statuses = [];
    private bool $filterUsed = false;

    private function controller(array $params, array $items, ?\Exception $filterFailure = null, int $failOn = -1): MassStatus
    {
        $this->saved = [];
        $this->filterUsed = false;

        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $filter = $this->createStub(Filter::class);
        if ($filterFailure) {
            $filter->method('getCollection')->willThrowException($filterFailure);
        } else {
            $filter->method('getCollection')->willReturnCallback(function ($c) {
                $this->filterUsed = true;
                return $c;
            });
        }

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('save')->willReturnCallback(function ($item) use ($failOn, &$resource) {
            if (count($this->saved) === $failOn) {
                throw new \RuntimeException('cannot save');
            }
            $this->saved[] = $item;
            return $resource;
        });

        return new MassStatus($this->context($params), $filter, $factory, $resource);
    }

    private function items(int $count): array
    {
        $this->statuses = [];
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = $this->createStub(Submission::class);
            $item->method('setData')->willReturnCallback(function ($key, $value = null) use ($i, &$item) {
                $this->statuses[$i][$key] = $value;
                return $item;
            });
            $items[] = $item;
        }
        return $items;
    }

    public function testOnlyAcceptsPostAndUsesTheStatusAclResource(): void
    {
        $this->assertInstanceOf(HttpPostActionInterface::class, $this->controller([], []));
        $this->assertSame('Panth_AdvancedContactUs::submission_status', MassStatus::ADMIN_RESOURCE);
    }

    public function testEverySelectedSubmissionIsMarkedAsReplied(): void
    {
        $items = $this->items(3);
        $this->controller(['status' => '2'], $items)->execute();

        $this->assertSame($items, $this->saved);
        $this->assertSame(
            [['status' => 2], ['status' => 2], ['status' => 2]],
            $this->statuses
        );
        $this->assertSame(['A total of 3 submission(s) have been marked as Replied.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testMarkAsNewAndRead(): void
    {
        $this->controller(['status' => '0'], $this->items(1))->execute();
        $this->assertSame([['status' => 0]], $this->statuses);
        $this->assertSame(['A total of 1 submission(s) have been marked as New.'], $this->messages['success']);

        $this->controller(['status' => '1'], $this->items(2))->execute();
        $this->assertSame([['status' => 1], ['status' => 1]], $this->statuses);
    }

    public function testInvalidStatusChangesNothing(): void
    {
        foreach ([null, '7', 'replied'] as $status) {
            $this->controller(['status' => $status], $this->items(2))->execute();

            $this->assertFalse($this->filterUsed);
            $this->assertSame([], $this->saved);
            $this->assertSame(['Please choose a valid status.'], $this->messages['error']);
            $this->assertSame('*/*/', $this->redirectPath);
        }
    }

    public function testFilterFailureIsReported(): void
    {
        $this->controller(['status' => '2'], [], new \RuntimeException('bad selection'))->execute();

        $this->assertSame(['bad selection'], $this->messages['error']);
        $this->assertSame([], $this->messages['success']);
    }

    public function testFailureMidwayStopsAndReportsTheError(): void
    {
        $this->controller(['status' => '2'], $this->items(3), null, 1)->execute();

        $this->assertCount(1, $this->saved);
        $this->assertSame(['cannot save'], $this->messages['error']);
        $this->assertSame([], $this->messages['success']);
    }
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\ViewModel\SubmissionView;
use PHPUnit\Framework\TestCase;

class SubmissionViewTest extends TestCase
{
    private int $loads = 0;
    private array $loadedIds = [];

    private function view(
        $idParam,
        ?int $foundId,
        ?TimezoneInterface $timezone = null,
        ?StoreManagerInterface $storeManager = null
    ): SubmissionView {
        $this->loads = 0;
        $this->loadedIds = [];

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key) => $key === 'id' ? $idParam : null
        );

        $submission = $this->createStub(Submission::class);
        $submission->method('getId')->willReturn($foundId);

        $factory = $this->createStub(SubmissionFactory::class);
        $factory->method('create')->willReturn($submission);

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('load')->willReturnCallback(function ($object, $id) use (&$resource) {
            $this->loads++;
            $this->loadedIds[] = $id;
            return $resource;
        });

        return new SubmissionView(
            $request,
            $factory,
            $resource,
            $timezone ?? $this->createStub(TimezoneInterface::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class)
        );
    }

    public function testMissingOrInvalidIdReturnsNullWithoutLoading(): void
    {
        foreach ([null, '0', '-5', 'abc'] as $param) {
            $view = $this->view($param, 1);
            $this->assertNull($view->getSubmission());
            $this->assertSame(0, $this->loads);
        }
    }

    public function testUnknownIdReturnsNull(): void
    {
        $view = $this->view('42', null);

        $this->assertNull($view->getSubmission());
        $this->assertSame([42], $this->loadedIds);
    }

    public function testExistingSubmissionIsReturnedAndMemoised(): void
    {
        $view = $this->view('7', 7);

        $first = $view->getSubmission();
        $second = $view->getSubmission();

        $this->assertInstanceOf(Submission::class, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $this->loads);
    }

    public function testNotFoundResultIsAlsoMemoised(): void
    {
        $view = $this->view('9', null);

        $view->getSubmission();
        $this->assertNull($view->getSubmission());
        $this->assertSame(1, $this->loads);
    }

    public function testStatusLabelsAndColoursCoverEveryStatus(): void
    {
        $view = $this->view(null, null);
        $statuses = [Submission::STATUS_NEW, Submission::STATUS_READ, Submission::STATUS_REPLIED];

        $this->assertSame(
            [Submission::STATUS_NEW => 'New', Submission::STATUS_READ => 'Read', Submission::STATUS_REPLIED => 'Replied'],
            $view->getStatusLabels()
        );
        $this->assertSame($statuses, array_keys($view->getStatusColors()));
        foreach ($view->getStatusColors() as $colour) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $colour);
        }
    }

    public function testSubmittedAtIsConvertedFromUtcToTheConfiguredTimezone(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->expects($this->once())->method('date')->willReturnCallback(
            static function ($date) {
                $local = clone $date;
                return $local->setTimezone(new \DateTimeZone('Asia/Kolkata'));
            }
        );
        $view = $this->view(null, null, $timezone);

        $this->assertSame('2026-10-04 16:03:23', $view->formatSubmittedAt('2026-10-04 10:33:23'));
    }

    public function testEmptySubmittedAtReturnsEmptyString(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->expects($this->never())->method('date');
        $view = $this->view(null, null, $timezone);

        $this->assertSame('', $view->formatSubmittedAt(null));
        $this->assertSame('', $view->formatSubmittedAt('  '));
    }

    public function testStoreLabelShowsWebsiteGroupAndStoreViewNames(): void
    {
        $website = $this->createStub(Website::class);
        $website->method('getName')->willReturn('Main Website');
        $group = $this->createStub(Group::class);
        $group->method('getName')->willReturn('Main Website Store');
        $store = $this->createStub(Store::class);
        $store->method('getWebsite')->willReturn($website);
        $store->method('getGroup')->willReturn($group);
        $store->method('getName')->willReturn('Default Store View');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->once())->method('getStore')->with(1)->willReturn($store);

        $view = $this->view(null, null, null, $storeManager);

        $this->assertSame('Main Website / Main Website Store / Default Store View', $view->getStoreLabel(1));
    }

    public function testUnknownStoreFallsBackToTheStoreId(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new NoSuchEntityException(__('missing')));

        $view = $this->view(null, null, null, $storeManager);

        $this->assertSame('Store #99', $view->getStoreLabel(99));
    }
}

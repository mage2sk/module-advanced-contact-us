<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\ViewModel\SubmissionView;
use PHPUnit\Framework\TestCase;

class SubmissionViewTest extends TestCase
{
    private int $loads = 0;
    private array $loadedIds = [];

    private function view($idParam, ?int $foundId): SubmissionView
    {
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

        return new SubmissionView($request, $factory, $resource);
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
}

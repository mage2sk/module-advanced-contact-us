<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Panth\AdvancedContactUs\Controller\Adminhtml\Submission\SetStatus;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;

class SetStatusTest extends SubmissionControllerTestCase
{
    private array $loaded = [];
    private array $savedStatuses = [];

    private function controller(array $params, ?int $foundId, ?\Exception $saveFailure = null): SetStatus
    {
        $this->loaded = [];
        $this->savedStatuses = [];
        $data = [];

        $submission = $this->createStub(Submission::class);
        $submission->method('getId')->willReturn($foundId);
        $submission->method('setData')->willReturnCallback(function ($key, $value = null) use (&$data, &$submission) {
            $data[$key] = $value;
            return $submission;
        });
        $factory = $this->createStub(SubmissionFactory::class);
        $factory->method('create')->willReturn($submission);

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('load')->willReturnCallback(function ($object, $id) use (&$resource) {
            $this->loaded[] = $id;
            return $resource;
        });
        $resource->method('save')->willReturnCallback(function () use ($saveFailure, &$data, &$resource) {
            if ($saveFailure) {
                throw $saveFailure;
            }
            $this->savedStatuses[] = $data['status'] ?? null;
            return $resource;
        });

        return new SetStatus($this->context($params), $factory, $resource);
    }

    public function testOnlyAcceptsPostAndUsesTheStatusAclResource(): void
    {
        $this->assertInstanceOf(HttpPostActionInterface::class, $this->controller([], null));
        $this->assertSame('Panth_AdvancedContactUs::submission_status', SetStatus::ADMIN_RESOURCE);
    }

    public function testMarkAsRepliedSavesAndReturnsToTheSubmission(): void
    {
        $this->controller(['id' => '7', 'status' => '2'], 7)->execute();

        $this->assertSame([7], $this->loaded);
        $this->assertSame([Submission::STATUS_REPLIED], $this->savedStatuses);
        $this->assertSame(['Submission #7 has been marked as Replied.'], $this->messages['success']);
        $this->assertSame('*/*/view', $this->redirectPath);
    }

    public function testMarkAsReadReturnsToTheSubmission(): void
    {
        $this->controller(['id' => '7', 'status' => '1'], 7)->execute();

        $this->assertSame([Submission::STATUS_READ], $this->savedStatuses);
        $this->assertSame('*/*/view', $this->redirectPath);
    }

    public function testMarkAsNewReturnsToTheGridSoOpeningTheViewDoesNotUndoIt(): void
    {
        $this->controller(['id' => '7', 'status' => '0'], 7)->execute();

        $this->assertSame([Submission::STATUS_NEW], $this->savedStatuses);
        $this->assertSame(['Submission #7 has been marked as New.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testInvalidStatusIsRejectedWithoutLoading(): void
    {
        foreach ([null, '', '3', '-1', 'replied', '1.5'] as $status) {
            $this->controller(['id' => '7', 'status' => $status], 7)->execute();

            $this->assertSame([], $this->loaded);
            $this->assertSame([], $this->savedStatuses);
            $this->assertSame(['Please choose a valid status.'], $this->messages['error']);
            $this->assertSame('*/*/view', $this->redirectPath);
        }
    }

    public function testInvalidStatusWithoutIdGoesToTheGrid(): void
    {
        $this->controller(['status' => 'x'], null)->execute();

        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testMissingSubmissionIsReported(): void
    {
        $this->controller(['id' => '99', 'status' => '2'], null)->execute();

        $this->assertSame([], $this->savedStatuses);
        $this->assertSame(['This submission no longer exists.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testSaveFailureIsReported(): void
    {
        $this->controller(['id' => '7', 'status' => '2'], 7, new \RuntimeException('db down'))->execute();

        $this->assertSame(['db down'], $this->messages['error']);
        $this->assertSame([], $this->messages['success']);
        $this->assertSame('*/*/view', $this->redirectPath);
    }
}

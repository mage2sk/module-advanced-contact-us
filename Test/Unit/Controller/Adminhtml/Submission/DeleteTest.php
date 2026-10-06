<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Controller\Adminhtml\Submission;

use Panth\AdvancedContactUs\Controller\Adminhtml\Submission\Delete;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;

class DeleteTest extends SubmissionControllerTestCase
{
    private array $loaded = [];
    private int $deleted = 0;

    private function controller(array $params, ?int $foundId, ?\Exception $deleteFailure = null): Delete
    {
        $this->loaded = [];
        $this->deleted = 0;

        $submission = $this->createStub(Submission::class);
        $submission->method('getId')->willReturn($foundId);
        $factory = $this->createStub(SubmissionFactory::class);
        $factory->method('create')->willReturn($submission);

        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('load')->willReturnCallback(function ($object, $id) use (&$resource) {
            $this->loaded[] = $id;
            return $resource;
        });
        $resource->method('delete')->willReturnCallback(function () use ($deleteFailure, &$resource) {
            if ($deleteFailure) {
                throw $deleteFailure;
            }
            $this->deleted++;
            return $resource;
        });

        return new Delete($this->context($params), $factory, $resource);
    }

    public function testExistingSubmissionIsDeleted(): void
    {
        $this->controller(['id' => '12'], 12)->execute();

        $this->assertSame([12], $this->loaded);
        $this->assertSame(1, $this->deleted);
        $this->assertSame(['Submission has been deleted.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testUnknownSubmissionIsSilentlyIgnored(): void
    {
        $this->controller(['id' => '99'], null)->execute();

        $this->assertSame(0, $this->deleted);
        $this->assertSame(['success' => [], 'error' => []], $this->messages);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testDeleteFailureIsReported(): void
    {
        $this->controller(['id' => '5'], 5, new \RuntimeException('locked'))->execute();

        $this->assertSame(['locked'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirectPath);
    }

    public function testRequiresTheDeletePermission(): void
    {
        $this->assertSame('Panth_AdvancedContactUs::submission_delete', Delete::ADMIN_RESOURCE);
    }
}

<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class SubmissionView implements ArgumentInterface
{
    private RequestInterface $request;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;
    private ?Submission $submission = null;
    private bool $loaded = false;

    public function __construct(
        RequestInterface $request,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        $this->request = $request;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function getSubmission(): ?Submission
    {
        if ($this->loaded) {
            return $this->submission;
        }
        $this->loaded = true;

        $id = (int) $this->request->getParam('id');
        if ($id <= 0) {
            return null;
        }

        $submission = $this->submissionFactory->create();
        $this->submissionResource->load($submission, $id);

        if (!$submission->getId()) {
            return null;
        }

        $this->submission = $submission;
        return $this->submission;
    }

    /**
     * @return array<int, string>
     */
    public function getStatusLabels(): array
    {
        return [0 => 'New', 1 => 'Read', 2 => 'Replied'];
    }

    /**
     * @return array<int, string>
     */
    public function getStatusColors(): array
    {
        return [0 => '#F59E0B', 1 => '#3B82F6', 2 => '#10B981'];
    }
}

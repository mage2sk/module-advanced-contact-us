<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class SubmissionView implements ArgumentInterface
{
    private RequestInterface $request;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;
    private TimezoneInterface $timezone;
    private StoreManagerInterface $storeManager;
    private ?Submission $submission = null;
    private bool $loaded = false;

    public function __construct(
        RequestInterface $request,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource,
        TimezoneInterface $timezone,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
        $this->timezone = $timezone;
        $this->storeManager = $storeManager;
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

    public function getStatusLabels(): array
    {
        return [0 => 'New', 1 => 'Read', 2 => 'Replied'];
    }

    public function getStatusColors(): array
    {
        return [0 => '#F59E0B', 1 => '#3B82F6', 2 => '#10B981'];
    }

    public function formatSubmittedAt(?string $createdAt): string
    {
        $createdAt = trim((string) $createdAt);
        if ($createdAt === '') {
            return '';
        }
        try {
            $utc = new \DateTime($createdAt, new \DateTimeZone('UTC'));
            return $this->timezone->date($utc)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $createdAt;
        }
    }

    public function getStoreLabel(int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $parts = [];
            $website = $store->getWebsite();
            if ($website && $website->getName()) {
                $parts[] = (string) $website->getName();
            }
            $group = $store->getGroup();
            if ($group && $group->getName()) {
                $parts[] = (string) $group->getName();
            }
            if ($store->getName()) {
                $parts[] = (string) $store->getName();
            }
            return $parts ? implode(' / ', $parts) : (string) __('Store #%1', $storeId);
        } catch (\Exception $e) {
            return (string) __('Store #%1', $storeId);
        }
    }
}

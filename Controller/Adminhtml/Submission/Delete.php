<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_delete';

    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $submission = $this->submissionFactory->create();
            $this->submissionResource->load($submission, $id);
            if ($submission->getId()) {
                $this->submissionResource->delete($submission);
                $this->messageManager->addSuccessMessage(__('Submission has been deleted.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}

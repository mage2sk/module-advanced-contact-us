<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class SetStatus extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_status';

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
        $status = Submission::normaliseStatus($this->getRequest()->getParam('status'));
        $redirect = $this->resultRedirectFactory->create();

        if ($status === null) {
            $this->messageManager->addErrorMessage(__('Please choose a valid status.'));
            return $id > 0 ? $redirect->setPath('*/*/view', ['id' => $id]) : $redirect->setPath('*/*/');
        }

        try {
            $submission = $this->submissionFactory->create();
            $this->submissionResource->load($submission, $id);
            if (!$submission->getId()) {
                $this->messageManager->addErrorMessage(__('This submission no longer exists.'));
                return $redirect->setPath('*/*/');
            }
            $submission->setData('status', $status);
            $this->submissionResource->save($submission);
            $this->messageManager->addSuccessMessage(
                __('Submission #%1 has been marked as %2.', $id, __(Submission::STATUS_LABELS[$status]))
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        if ($status === Submission::STATUS_NEW) {
            return $redirect->setPath('*/*/');
        }
        return $redirect->setPath('*/*/view', ['id' => $id]);
    }
}

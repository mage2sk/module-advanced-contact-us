<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Panth\AdvancedContactUs\Model\SubmissionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;

class View extends Action
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_view';

    private PageFactory $resultPageFactory;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $submission = $this->submissionFactory->create();
        $this->submissionResource->load($submission, $id);

        if (!$submission->getId()) {
            $this->messageManager->addErrorMessage(__('This submission no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        // Mark as read
        if ((int) $submission->getData('status') === Submission::STATUS_NEW) {
            $submission->setData('status', Submission::STATUS_READ);
            $this->submissionResource->save($submission);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_AdvancedContactUs::submissions');
        $resultPage->getConfig()->getTitle()->prepend(__('Submission #%1', $id));
        return $resultPage;
    }
}

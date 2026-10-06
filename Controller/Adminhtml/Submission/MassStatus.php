<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;
use Panth\AdvancedContactUs\Model\Submission;

class MassStatus extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_status';

    private Filter $filter;
    private CollectionFactory $collectionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $status = Submission::normaliseStatus($this->getRequest()->getParam('status'));
        if ($status === null) {
            $this->messageManager->addErrorMessage(__('Please choose a valid status.'));
            return $redirect;
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $item) {
                $item->setData('status', $status);
                $this->submissionResource->save($item);
                $count++;
            }
            $this->messageManager->addSuccessMessage(
                __('A total of %1 submission(s) have been marked as %2.', $count, __(Submission::STATUS_LABELS[$status]))
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}

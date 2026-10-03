<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission\CollectionFactory;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_delete';

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
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $item) {
                $this->submissionResource->delete($item);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 submission(s) have been deleted.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}

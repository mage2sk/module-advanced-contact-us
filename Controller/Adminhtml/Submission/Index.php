<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Panth_AdvancedContactUs::submission_view';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_AdvancedContactUs::submissions');
        $resultPage->getConfig()->getTitle()->prepend(__('Contact Submissions'));
        return $resultPage;
    }
}

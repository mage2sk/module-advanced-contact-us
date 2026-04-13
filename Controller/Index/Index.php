<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Panth\AdvancedContactUs\Model\Config;

class Index implements HttpGetActionInterface
{
    private PageFactory $resultPageFactory;
    private Config $config;

    public function __construct(PageFactory $resultPageFactory, Config $config)
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->config = $config;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set($this->config->getPageTitle());
        return $resultPage;
    }
}

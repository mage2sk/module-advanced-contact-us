<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Model;

use Magento\Framework\Model\AbstractModel;

class Submission extends AbstractModel
{
    const STATUS_NEW = 0;
    const STATUS_READ = 1;
    const STATUS_REPLIED = 2;

    protected function _construct()
    {
        $this->_init(\Panth\AdvancedContactUs\Model\ResourceModel\Submission::class);
    }
}

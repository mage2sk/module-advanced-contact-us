<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Model\ResourceModel\Submission;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\AdvancedContactUs\Model\Submission;
use Panth\AdvancedContactUs\Model\ResourceModel\Submission as SubmissionResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'submission_id';

    protected function _construct()
    {
        $this->_init(Submission::class, SubmissionResource::class);
    }
}

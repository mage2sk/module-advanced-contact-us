<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Model;

use Magento\Framework\Model\AbstractModel;

class Submission extends AbstractModel
{
    const STATUS_NEW = 0;
    const STATUS_READ = 1;
    const STATUS_REPLIED = 2;

    const STATUS_LABELS = [
        self::STATUS_NEW => 'New',
        self::STATUS_READ => 'Read',
        self::STATUS_REPLIED => 'Replied',
    ];

    protected function _construct()
    {
        $this->_init(\Panth\AdvancedContactUs\Model\ResourceModel\Submission::class);
    }

    public static function normaliseStatus($value): ?int
    {
        if (is_int($value)) {
            $status = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $status = (int) trim($value);
        } else {
            return null;
        }
        return array_key_exists($status, self::STATUS_LABELS) ? $status : null;
    }
}

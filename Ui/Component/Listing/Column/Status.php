<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    private const STATUS_MAP = [
        0 => ['New', 'grid-severity-notice panth-contact-status-new'],
        1 => ['Read', 'grid-severity-minor panth-contact-status-read'],
        2 => ['Replied', 'grid-severity-notice'],
    ];

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['status'])) {
                    $status = (int) $item['status'];
                    if (isset(self::STATUS_MAP[$status])) {
                        [$label, $class] = self::STATUS_MAP[$status];
                        $item['status_label'] = '<span class="' . $class . '"><span>'
                            . htmlspecialchars((string) __($label), ENT_QUOTES) . '</span></span>';
                        $item['status'] = $item['status_label'];
                    }
                }
            }
        }
        return $dataSource;
    }
}

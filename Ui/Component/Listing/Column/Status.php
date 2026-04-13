<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['status'])) {
                    $status = (int) $item['status'];
                    switch ($status) {
                        case 0:
                            $item['status_label'] = '<span style="background:#FEF3C7;color:#92400E;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">New</span>';
                            break;
                        case 1:
                            $item['status_label'] = '<span style="background:#DBEAFE;color:#1E40AF;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Read</span>';
                            break;
                        case 2:
                            $item['status_label'] = '<span style="background:#D1FAE5;color:#065F46;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">Replied</span>';
                            break;
                    }
                }
            }
        }
        return $dataSource;
    }
}

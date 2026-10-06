<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class CustomFields extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('label', [
            'label' => __('Field Label'),
            'class' => 'required-entry',
            'style' => 'width:150px',
        ]);
        $this->addColumn('type', [
            'label' => __('Type'),
            'class' => 'required-entry',
            'style' => 'width:100px',
            'comment' => 'text, textarea, select, radio, checkbox, email, tel',
        ]);
        $this->addColumn('required', [
            'label' => __('Required'),
            'style' => 'width:70px',
            'comment' => '1 or 0',
        ]);
        $this->addColumn('placeholder', [
            'label' => __('Placeholder'),
            'style' => 'width:150px',
        ]);
        $this->addColumn('options', [
            'label' => __('Options'),
            'style' => 'width:180px',
            'comment' => 'Comma-separated (for select/radio)',
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Custom Field');
    }

    protected function _toHtml()
    {
        return '<div class="panth-acu-fieldarray">' . parent::_toHtml() . '</div>'
            . '<style>.panth-acu-fieldarray{width:0;min-width:100%;}'
            . '.panth-acu-fieldarray .admin__control-table-wrapper{overflow-x:auto;}'
            . '.panth-acu-fieldarray .admin__control-table input.input-text{min-width:80px;max-width:100%;}</style>';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\ClassServices;

class Localizedfields extends AbstractField
{
    protected $optionKey;

    public function __construct($data, $layout = null, $value = null, $localized = false, $optionKey = [])
    {
        parent::__construct($data, $layout, $value, $localized);

        $this->optionKey = $optionKey;
    }

    // 
    public function formatDocument($value)
    {
        return $this->format($value);
    }

    // chưa xử lý
    public function format($value)
    {
        return null;
    }

    public function formatBlock($value) {
        $data = [];

        $items = $value->getItems();

        // update object
        if($this->getDataValue()) {
            $data = $items;
        }

        // detail object
        if(!$this->getDataValue() && $items && $this->getLocalized() && isset($items[$this->getLocalized()])) {
            $data = $items[$this->getLocalized()];
        }

        return $data;
    }

    // chưa xử lý
    public function formatDataSave($value)
    {
        return null;
    }

    public function getDataSave() {
        $data = null;
        if ($this->getLocalized()) {
            $data = new \Pimcore\Model\DataObject\Localizedfield([
                $this->getLocalized() => $this->getDataValue()
            ]);
        }

        return $data;
    }

    public function getDefinitions()
    {
        $layouts = [];
        $children = $this->layout->children;
        if (!empty($children)) {
            foreach ($children as $key => $value) {
                $layout = ClassServices::getFieldProperty($value, $this->getLocalized(), $this->getObject()?->getClassId());
                if(in_array( $layout['fieldtype'], ClassServices::TYPE_OPTION)) {
                    $this->optionKey[$layout['name']] = [
                        'fieldId' => $layout['name'],
                    ];
                }
                $layouts[$key] = $layout;
            }
        }

        return $layouts;
    }

    public function getOptionKey()
    {
        return $this->optionKey;
    }

    public function getFrontEndType()
    {
        return '';
    }
}

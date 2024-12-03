<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\DataObjectServices;
use CorepulseBundle\Services\Helper\ArrayHelper;
use Pimcore\Model\DataObject\Data\BlockElement;
use CorepulseBundle\Services\ClassServices;
use Pimcore\Model\DataObject\Localizedfields;

class Block extends AbstractField
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
    public function formatBlock($value)
    {
        return $this->format($value);
    }

    public function format($value)
    {
        $result = null;

        if ($value) {
            $result = [];
            $fieldDefinitions = $this->getLayout()->children;
            foreach ($value as $block) {
                $resultItem = [];
                /**
                 * @var  string $key
                 * @var  BlockElement $fieldValue
                 */
                foreach ($block as $key => $fieldValue) {
                    $fd = ArrayHelper::filterData($fieldDefinitions, 'name', $key);

                    if (empty($fd)) {
                        $resultItem[$key] = null;
                        continue;
                    }

                    $field = DataObjectServices::convertField(reset($fd));

                    if (isset($field['invisible']) && $field['invisible']) {
                        continue;
                    }

                    $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($field['fieldtype']);
                    if (!class_exists($getClass)) {
                        $resultItem[$key] = null;
                        continue;
                    }

                    $componentData = new $getClass($fieldValue, $field, $this->getDataValue(), $this->getLocalized());

                    $resultItem[$key] = $componentData->getValue();
                }
                $result[] = $resultItem;
            }
        }

        return $result;
    }

    public function formatDocumentSave($value)
    {
        return $value;
    }
    
    public function formatDataSave($values)
    {
        $datas = [];
        if ($values) {
            foreach ($values as $key => $value) {
                // dd($value, $this->layout->getChildren(), $this->data);
                $data = [];
                foreach ($value as $k => $v) {
                    $filter = array_filter($this->layout->getChildren(), function($item) use ($k) {
                        return $item->name === $k;
                    });
                    if ($filter && $definition = reset($filter)) {
                        $type = $definition->getFieldType();
                        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($type);
                        if (!class_exists($getClass)) continue;
                        
                        $component = new $getClass($this->getObjectOrDocument(), $this->getLayout(), $v, $this->getLocalized());
                        $valueData =  $component->getDataSave();
                        if($type == 'localizedfields' && $this->getValue()) {
                            $revertItems = $valueData->getItems();
                            $valueOld = $this->getValue()[$key]['localizedfields'];
                            unset($valueOld[$this->getLocalized()]);
                            $valueData->setItems(array_merge($valueOld, $revertItems));
                        }
                        $blockElement = new BlockElement($k, $type, $valueData);
                        $data[$k] = $blockElement;
                    }
                }
                $datas[] = $data;
            }

            if ($this->getValue() && ($countDatas = count($datas)) < count($this->getValue())) {
                foreach ($this->getValue() as $key => $value) {
                    if ($key > $countDatas - 1 && $value && isset($value['localizedfields'])) {
                        unset($value['localizedfields'][$this->getLocalized()]);
                        $valueData = new \Pimcore\Model\DataObject\Localizedfield($value['localizedfields']);
                        $data = new BlockElement('localizedfields', 'localizedfields', $valueData);
                        $datas[] = $data;
                    }
                }
            }
        }

        return $datas;
    }

    public function getDefinitions()
    {
        $layouts = [];
        $children = $this->layout->children;
        if (!empty($children)) {
            foreach ($children as $key => $value) {
                $layout = ClassServices::getFieldProperty($value, $this->getLocalized(), $this->getObjectOrDocument()?->getClassId());
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

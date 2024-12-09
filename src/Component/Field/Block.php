<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Services\DataObjectServices;
use CorepulseBundle\Services\Helper\ArrayHelper;
use Pimcore\Model\DataObject\Data\BlockElement;
use CorepulseBundle\Services\ClassServices;
use Pimcore\Model\DataObject\Localizedfields;
use Pimcore\Model\Document\Editable\Block as DocumentBlock;

class Block extends AbstractField
{
    protected $optionKey;

    public function __construct($objectOrDocument, $layout = null, $value = null, $localized = false, $isObject = true)
    {
        parent::__construct($objectOrDocument, $layout, $value, $localized, $isObject);

        $this->optionKey = [];
    }

    public function formatDocument($value)
    {
        $prefixName = $value->getName();
        $allValue = $this->filterAllValue($this->getObjectOrDocument()->getEditables(), $prefixName);

        $datas = [];
        foreach ($allValue as $k => $v) {
            $fieldType = $v->getType();
            $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);
        
            try {
                if (class_exists($getClass)) {
                    $component = new $getClass($this->getObjectOrDocument(), $v, null, null, false);
                    $datas[$k] = $component->getValue();
                }
                
            } catch (\Throwable $th) {
                // dd($fieldType, $th->getMessage());
            }
        }

        $revertData = [];

        $indices = $value->getIndices();
        
        if (is_array($indices) && count($indices)) {
            foreach ($datas as $k => $v) {
               

                if (preg_match("/$prefixName:(\d+)\.(.*)/", $k, $matches)) {
                    $bannerNumber = $matches[1];
                    $field = $matches[2];
    
                    if ($bannerNumber > count($indices)) continue;
                    
                    if (!isset($revertData[$bannerNumber])) {
                        $revertData[$bannerNumber] = [];
                    }
    
                    $revertData[$bannerNumber][$field] = $v;
                }
            }    
        }
        
        $revertData = array_values($revertData);

        return $revertData;
    }

    public function filterAllValue($data, $prefix)
    {
        $prefix = $prefix . ':';
        $result = array_filter($data, function ($value, $key) use ($prefix) {
            return strpos($key, $prefix) === 0;
        }, ARRAY_FILTER_USE_BOTH);

        return $result;
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
        $editable = new DocumentBlock();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);

        $config = $this->getLayout()?->config['template']['editables'];
        $i = 0;
        $blockData = [];
        foreach ($value as $key => $item) {
            $i++;
            $blockData[] = $i;
            foreach ($item as $itemKey => $itemValue) {
                $fd = ArrayHelper::filterData($config, 'realName', $itemKey, true);
                if (empty($fd)) {
                    continue;
                }

                $itemConfig = reset($fd);

                $fieldType = $itemConfig['type'];
                $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);
            
                try {
                    if (class_exists($getClass)) {
                        $name = $itemConfig['name'];
                        $itemConfig['name'] = str_replace(':1000000.', ":$i.", $name);
                        $component = new $getClass($this->getObjectOrDocument(), $itemConfig, $itemValue, null, false, true);
                        $this->getObjectOrDocument()->setEditable($component->getDataSave());
                    }
                } catch (\Throwable $th) {
                    dd($fieldType, $th->getMessage());
                }
            }
        }
        
        $editable->setDataFromEditmode($blockData);

        return $this->getObjectOrDocument()->setEditable($editable);
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

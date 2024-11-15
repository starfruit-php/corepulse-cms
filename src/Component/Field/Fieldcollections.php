<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\ClassDefinition\Layout\Tabpanel;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject;
use CorepulseBundle\Services\DataObjectServices;
use CorepulseBundle\Services\ClassServices;

class Fieldcollections extends AbstractField
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
        if (!$value) return null;

        $resultItems = [];
        foreach ($value->getItems() as $item) {
            $type = $item->getType();
            $definition = DataObject\Fieldcollection\Definition::getByKey($type);
            $resultItems[] = array_merge(['type' => $type], DataObjectServices::getData($item, $definition->getFieldDefinitions()));
        }

        return $resultItems;
    }

    public function formatDataSave($values)
    {
        $items = new DataObject\Fieldcollection();
        if ($values) {
            foreach ($values as $value) {
                $fieldCollection = $this->createFieldCollection($value['type'], $value );
                if ($fieldCollection) {
                    $items->add($fieldCollection);
                }
            }
        }
        
        return $items;
    }

    private function createFieldCollection($type, $data)
    {
        $func = "Pimcore\\Model\\DataObject\\Fieldcollection\\Data\\" . ucfirst($type);
        $fieldCollection = new $func();

        $definition = DataObject\Fieldcollection\Definition::getByKey($type);
        $fieldDefinitions = $definition->getFieldDefinitions();

        foreach ($data as $key => $value) {
            if ($key != 'type' && isset($fieldDefinitions[$key])) {
                $component = $this->createComponent($fieldDefinitions[$key], $value);
                if ($component) {
                    $fieldCollection->{'set' . ucfirst($key)}($component->getDataSave());
                }
            }
        }

        return $fieldCollection;
    }

    private function createComponent($fieldDefinition, $value)
    {
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldDefinition->getFieldType());
        return class_exists($getClass) ? new $getClass($this->getObject(), null, $value) : null;
    }

    public function getDefinitions()
    {
        $layouts = [];
        foreach ($this->layout->allowedTypes ?? [] as $type) {
            $layouts[$type] = $this->getDefinition($type);
        }

        return $layouts;
    }

    public function getDefinition($type)
    {
        $definition = DataObject\Fieldcollection\Definition::getByKey($type);

        //convent panel to tabpanel
        $parentLayout = $definition->getLayoutDefinitions();

        $dataPanel = [];
        foreach ($parentLayout->getChildren() as $key => $layout) {
            if($layout instanceof Panel) $dataPanel[] = $layout;
        }

        if (!empty($dataPanel)) {
            $tabpanel = new Tabpanel();
            $tabpanel->setTitle('Convert Panel');
            $tabpanel->setName('Convert Panel');
            $tabpanel->setChildren($dataPanel);
            $parentLayout->setChildren([$tabpanel]);
        }

        $layouts = $this->getObjectVarsRecursive($parentLayout, $type);

        return $layouts;
    }

    public function getObjectVarsRecursive($layout, $type, $optionKey = null)
    {
        $vars = get_object_vars($layout);
        if (method_exists($layout, 'getFieldType')) {
            $vars['fieldtype'] = $layout->getFieldType();
            $optionKey = $vars['fieldtype'] == 'block' ? 'block' : $optionKey;
            $optionKey = $vars['fieldtype'] == 'localizedfields' ? 'localizedfields' : $optionKey;

            if(in_array( $vars['fieldtype'], ClassServices::TYPE_OPTION)) {
                if ($optionKey) {
                    $this->optionKey[$type][$optionKey][$vars['name']] = [
                        'fieldId' => $vars['name'],
                        // 'class' => $classId
                    ];
                } else {
                    $this->optionKey[$type][$vars['name']] = [
                        'fieldId' => $vars['name'],
                        // 'class' => $classId
                    ];
                }
            }
        }

        if (isset($vars['children']) && isset($vars['fieldtype'])) {
            foreach ($vars['children'] as $key => $value) {
                $vars['children'][$key] = $this->getObjectVarsRecursive($value,$type, $optionKey);
            }
        }

        return $vars;
    }

    public function getOptionKey()
    {
        return $this->optionKey;
    }

    public function getFrontEndType(): string
    {
        return '';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Select as DocumentSelect;

class Select extends AbstractField
{
    public function format($value)
    {
        return $value;
    }

    public function formatDocument($value)
    {
        return $value?->getText();
    }

    public function formatBlock($value)
    {
        return $value;
    }
    
    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentSelect();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $editable->setDataFromEditmode($value);

        return $editable;
    }

    public function formatDataSave($value)
    {
        return $value;
    }

    public function getOption()
    {
        $layoutDefinition = $this->layout;
        $options = [];
        switch ($layoutDefinition->optionsProviderType) {
            case 'class':
                $optionsProviderClass = $layoutDefinition->optionsProviderClass;
                if (class_exists($optionsProviderClass)) {
                    $optionProvider = new $optionsProviderClass;
                    $options = $optionProvider->getOptions( $this->getObject() ? compact('object') : [], $layoutDefinition);
                } else {
                    $options = $layoutDefinition->options;
                }

                break;
            case 'select_options':
                $optionsProviderClass = 'Pimcore\\Model\\DataObject\\SelectOptions\\' . $layoutDefinition->optionsProviderData;
                if (method_exists($optionsProviderClass, 'cases')) {
                    $cases = $optionsProviderClass::cases();

                    foreach ($cases as $key => $value) {
                        $options[] = [
                            'key' => $value->getLabel(),
                            'value' => $value->value,
                        ];
                    }
                } else {
                    $options = $layoutDefinition->options;
                }
                break;

            default:
                $options = $layoutDefinition->options;
                break;
        }

        return $options;
    }

    public function getFrontEndType()
    {
        return 'select';
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use CorepulseBundle\Component\Field\FieldInterface;
use Pimcore\Model\DataObject\Data\BlockElement;
use Pimcore\Model\Document\Editable\Input as DocumentInput;

class Input extends AbstractField
{
    public function format($value)
    {
        return $value;
    }

    public function formatDocument($value) 
    {
        return $value->getValue();
    }

    public function formatBlock($value)
    {
        return $value;
    }

    public function formatDataSave($value)
    {
        return $value;
    }

    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentInput();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }
        $editable->setDataFromEditmode($value ? $value : '');
       
        return $editable;
    }

    public function getFrontEndType()
    {
        return 'string';
    }
}

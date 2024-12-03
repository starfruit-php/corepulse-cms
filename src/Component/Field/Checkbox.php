<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Checkbox as DocumentCheckbox;

class Checkbox extends Input
{
    public function formatDocumentSave($value)
    {
        $editable = new DocumentCheckbox();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);
        $editable->setDataFromResource($value);
       
        return $editable;
    }

    public function getFrontEndType()
    {
        return 'boolean';
    }
}

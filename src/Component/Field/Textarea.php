<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Textarea as DocumentTextarea;

class Textarea extends Input
{
    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentTextarea();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $editable->setDataFromEditmode($value ? $value : '');

        return $editable;
    }
}

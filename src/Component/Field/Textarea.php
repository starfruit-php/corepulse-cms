<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Textarea as DocumentTextarea;

class Textarea extends Input
{
    public function formatDocumentSave($value)
    {
        $editable = new DocumentTextarea();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);
        $editable->setDataFromResource($value ? $value : '');
       
        return $editable;
    }
}

<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Numeric as DocumentNumeric;

class Numeric extends Input
{
    public function formatDocumentSave($value)
    {
        $editable = new DocumentNumeric();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);
        $editable->setDataFromResource((string)$value);

        return $editable;
    }

    public function getFrontEndType()
    {
        return 'number';
    }
}

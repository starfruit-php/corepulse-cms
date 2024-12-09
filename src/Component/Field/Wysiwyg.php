<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Wysiwyg as DocumentWysiwyg;

class Wysiwyg extends Input
{
    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentWysiwyg();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $editable->setDataFromEditmode($value);

        return $editable;
    }

    public function getFrontEndType()
    {
        return 'wysiwyg';
    }
}

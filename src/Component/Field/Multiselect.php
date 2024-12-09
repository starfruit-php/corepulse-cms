<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Document\Editable\Multiselect as DocumentMultiselect;

class Multiselect extends Select
{
    public function format($value)
    {
        if (!empty($value)) {
            $data = [];
            foreach ($value as $k => $v) {
                $data[] = $v;
            }

            return $data;
        }

        return null;
    }

    public function formatDocument($value) 
    {
        return $value->getValues();
    }

    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentMultiselect();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $editable->setDataFromEditmode($value);

        return $editable;
    }

    public function getFrontEndType()
    {
        return 'multiselect';
    }
}

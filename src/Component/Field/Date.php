<?php

namespace CorepulseBundle\Component\Field;

use Carbon\Carbon;
use Pimcore\Model\Document\Editable\Date as DocumentDate;

class Date extends AbstractField
{
    public function format($value)
    {
        return $value?->format("Y/m/d");
    }

    public function formatBlock($value)
    {
        return $value?->format("Y/m/d");
    }

    public function formatDocument($value)
    {
        return $value?->getData()?->format("Y/m/d");
    }

    public function formatDataSave($value)
    {
        if ($value) {
            $data = Carbon::createFromFormat('Y/m/d', $value);

            return $data;
        }

        return null;
    }

    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentDate();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $editable->setDataFromEditmode($value);

        return $editable;
    }

    public function getFrontEndType()
    {
        return 'date';
    }
}

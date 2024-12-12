<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Editable\Relations as DocumentRelations;

class Relations extends Relation
{
    public function formatDocumentSave($values)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentRelations();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $datas = [];
        if ($values) {
            foreach ($values as $key => $value) {
                $data = null;
                if (is_array($value)) {
                    $type = $value[0] ?? $value['type'] ?? null;
                    $id = $value[2] ?? $value['id'] ?? null;
                    $subtype = $value[1] ?? $value['subType'] ?? null;

                    if ($id) {
                        if ($type === 'DataObject') {
                            $object = DataObject::getById($id);
                            $type = 'object';
                            $subtype = $object->getType();
                        }

                        $data = [
                            'id' => (int) $id,
                            'type' => strtolower($type),
                            'subtype' => $subtype,
                        ];
                    }
                }

                if ($data) {
                    $datas[] = $data;
                }
            }
        }
        $editable->setDataFromEditmode($datas);

        return $editable;
    }

    public function formatDocument($value)
    {
        if ($value) {
            $data = [];
            $elements = $value->getElements();

            foreach ($elements as $key => $element) {
                $data[] = $this->getElementData($element);
            }

            return $data;
        }

        return null;
    }
}
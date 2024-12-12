<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Editable\Relation as DocumentRelation;

class Relation extends ManyToOneRelation
{
    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentRelation();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        if ($value && is_array($value)) {
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
                $editable->setDataFromEditmode($data);
            }
        }

        return $editable;
    }

    public function formatDocument($value)
    {
        if ($value) {
            $element = $value->getElement();

            $data = $this->getElementData($element);

            return $data;
        }

        return null;
    }

    public function getElementData($element)
    {
        $data = [];
        if ($element instanceof Asset) {
            $data = array_merge($data, [
                'type' => 'asset',
                'id' => $element->getId(),
                'subType' => $element->getType(),
                'fullpath' => 'Asset/' . $element->getType() . '/' . $element->getId(),
                'key' => $element->getFileName(),
            ]);
        } elseif ($element instanceof Document) {
            $data = array_merge($data, [
                'type' => 'document',
                'id' => $element->getId(),
                'subType' => $element->getType(),
                'fullpath' => 'Document/' . $element->getType() . '/' . $element->getId(),
                'key' => $element->getKey(),
            ]);
        } elseif ($element instanceof DataObject\AbstractObject) {
            $data = array_merge($data, [
                'type' => 'object',
                'id' => $element->getId(),
                'subType' => $element->getClassName(),
                'fullpath' => 'DataObject/' . $element->getClassName() . '/' . $element->getId(),
                'key' => $element->getKey(),
            ]);
        }

        return $data;
    }
}
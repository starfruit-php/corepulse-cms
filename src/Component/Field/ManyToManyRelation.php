<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;

class ManyToManyRelation extends ManyToOneRelation
{
    public function format($value)
    {
        if (!empty($value)) {
            $result = [];
            foreach ($value as $element) {
                $result[] = $this->getElementType($element);
            }

            return $result;
        }

        return null;
    }

    public function formatDataSave($values)
    {
        $datas = [];
        if ($values) {
            foreach ($values as $key => $value) {
                $data = null;
                if (is_array($value)) {
                    $type = $value[0] ?? $value['type'] ?? null;
                    $subType = $value[1] ?? $value['subType'] ?? null;
                    $id = $value[2] ?? $value['id'] ?? null;
                    switch (strtolower($type)) {
                        case 'asset':
                            $data = Asset::getById($id);
                            break;
                        case 'document':
                            $data = Document::getById($id);
                            break;
                        case 'object':
                        case 'dataobject':
                            if ($id) $data = DataObject::getById($id);
                            else if ($subType) {
                                $listing = new DataObject\Listing();
                                $listing->setCondition('className = ?', $subType);

                                foreach ($listing as $key => $value) {
                                    $dataList = DataObject::getById($value->getId());
                                    if ($dataList) {
                                        $datas[] = $dataList;
                                    }
                                }
                            }
                            break;

                        default:
                            $data = null;
                            break;
                    }
                }

                if ($data) {
                    $datas[] = $data;
                }
            }
        }

        return $datas;
    }

    public function getFrontEndType()
    {
        return 'manyToManyRelation';
    }
}

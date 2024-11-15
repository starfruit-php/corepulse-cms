<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Localizedfield;

class BlockJson extends JsonHelper
{
    const KEY_DOCUMENT = 'Document';
    const KEY_OBJECT = 'DataObject';
    const KEY_ASSET = 'Asset';

    public static function getJson($block, $data, $lang)
    {

        foreach ($block as $key => $value) {
            $values = [];
            if ($value->getType() == 'localizedfields') {
                if (array_key_exists($lang, $value->getData()->getItems())) {
                    $valueData = $value->getData()->getItems()[$lang];
                    foreach ($valueData as $key => $dataValue) {
                        if ($dataValue) {
                            $checkRelation =  self::checkMultiselect($dataValue, 3);

                            $data['localizedfields']['children'][$key]['value'] = $checkRelation;
                        } else {
                            $data['localizedfields']['children'][$key]['value'] = '';
                        }
                    }
                }
            } else {

                if ($value->getData()) {

                    if ($value->getType() === "numericRange") {
                        $data[$key]['value'] = [
                            $value->getData()->getMinimum(),
                            $value->getData()->getMaximum(),
                        ];
                    } elseif (
                        $value->getType() === "manyToOneRelation" ||
                        $value->getType() === "manyToManyObjectRelation" ||
                        $value->getType() === "manyToManyRelation"
                    ) {
                        if (isset($data[$key]['options'])) {
                            $data[$key]['value'] = self::checkMultiselect($value->getData(), 3);
                            // if (isset($data[$key]['options'][0]['children'])) {
                            //     if (isset($data[$key]['options'][0]['children'][0]['children'])) {
                            //         $childrenKey = 3;

                            //         $data[$key]['value'] = self::checkMultiselect($value->getData(), $childrenKey);
                            //     } else {
                            //         $childrenKey = 2;

                            //         $data[$key]['value'] = self::checkMultiselect($value->getData(), $childrenKey);
                            //     }
                            // } else {
                            //     $childrenKey = 1;

                            //     $data[$key]['value'] = self::checkMultiselect($value->getData(), $childrenKey);
                            // }
                        }
                    } elseif ($value->getType() === "datetime") {
                        $data[$key]['value'] = [
                            $value->getData()->format('Y-m-d'),
                            $value->getData()->format('h:m'),
                        ];
                    } elseif ($value->getType() === "multiselect") {
                        foreach ($value->getData() as $item) {
                            $values[] = $item;
                        }
                        $data[$key]['value'] = $values;
                    } elseif ($value->getType() === "dateRange") {
                        $data[$key]['value'] = [
                            $value->getData()->getStartDate()->format('Y-m-d'),
                            $value->getData()->getEndDate()->format('Y-m-d')
                        ];
                    } elseif ($value->getType() === 'image') {
                        $data[$key]['value'] = $value->getData()->getFullPath();
                    } elseif ($value->getType() === 'imageGallery') {

                        foreach ($value->getData() as $item) {
                            $values[] = $item->getImage()->getFullPath();
                        }

                        $data[$key]['value'] = $values;
                    } elseif ($value->getType() === "date") {
                        $data[$key]['value'] = $value->getData()->format('Y-m-d');
                    } elseif ($value->getType() === "booleanSelect") {
                        if ($value->getData() === true) {
                            $data[$key]['value'] = 1;
                        } elseif ($value->getData() === false) {
                            $data[$key]['value'] = -1;
                        } elseif ($value->getData() === null) {
                            $data[$key]['value'] = 0;
                        }
                    } elseif ($value->getType() === "link") {
                        $d = [];
                        $v = $value->getData();

                        if ($v) {
                            $d['internalType'] = $v->getInternalType();
                            $d['internal'] = $v->getInternal();
                            $d['direct'] = $v->getDirect();
                            $d['linktype'] = $v->getLinktype();
                            $d['path'] = $v->getPath();
                            $d['text'] = $v->getText();
                            $d['title'] = $v->getTitle();
                            $d['target'] = $v->getTarget();
                            $d['parameters'] = $v->getParameters();
                            $d['anchorLink'] = $v->getAnchor();
                            $d['attributes'] = $v->getAttributes();
                            $d['accessKey'] = $v->getAccesskey();
                            $d['rel'] = $v->getRel();
                            $d['tabIndex'] = $v->getTabindex();
                            $d['class'] = $v->getClass();
                        }
                        $data[$key]['value'] = $d;
                    } else {
                        $data[$key]['value'] = $value->getData();
                    }
                } else {
                    $data[$key]['value'] = '';
                }
            }
        }

        return $data;
    }

    static public function checkMultiselect($values, $childrenKey)
    {
        $data = [];
        if (is_array($values)) {
            foreach ($values as $itemValue) {
                $data[] = self::getValueRelation($itemValue, $childrenKey);
            }
        } else {
            $data = self::getValueRelation($values, $childrenKey);
        }

        return $data;
    }

    static public function getValueRelation($value, $childrenKey)
    {
        $data = [];
        $model = '';
        $id = '';
        $type = '';

        if ($value instanceof Document) {
            $model = self::KEY_DOCUMENT;
        } elseif ($value instanceof Asset) {
            $model = self::KEY_ASSET;
        } elseif ($value instanceof DataObject) {
            $model = self::KEY_OBJECT;
        }

        if ($model) {
            $id = $value->getId();
            $type = $value->getType() == 'object' ? $value->getClassName() : $value->getType();

            if ($childrenKey == 3) {
                $data = [
                    0 => $model,
                    1 => $type,
                    2 => $id,
                ];
            } elseif ($childrenKey == 2) {
                $data = [
                    0 => $type,
                    1 => $id,
                ];
            } elseif ($childrenKey == 1) {
                $data = [
                    0 => $id,
                ];
            }

            return $data;
        }

        return $value;
    }
}

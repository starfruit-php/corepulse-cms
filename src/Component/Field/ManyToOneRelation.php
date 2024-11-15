<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use CorepulseBundle\Services\ClassServices;
use Pimcore\Db;

class ManyToOneRelation extends Select
{
    const SYSTEM_CONVERT_DATE = ['creationDate', 'modificationDate'];

    public function format($value)
    {
        if ($value) {
            return $this->getElementType($value);
        }

        return null;
    }

    public function formatBlock($value)
    {
        if ($value) {
            return $this->getElementType($value);
        }

        return null;
    }

    public function formatDataSave($value)
    {
        $data = null;
        if ($value && is_array($value)) {
            $type = $value[0] ?? $value['type'] ?? null;
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
                    $data = DataObject::getById($id);
                    break;

                default:
                    $data = null;
                    break;
            }
        }

        return $data;
    }

    public function getElementType(ElementInterface $element)
    {
        $data = [];

        // Các trường hiển thị mặc định
        $visibleFields = ['key', 'path', 'fullpath'];
        $displayMode = $this->layout->displayMode;

        // Kiểm tra và cập nhật các trường hiển thị nếu được chỉ định trong layout
        if (property_exists($this->layout, 'visibleFields') && !empty($this->layout->visibleFields)) {
            $visibleFields = explode(',', $this->layout->visibleFields);
        }

        // Loại bỏ trường 'filename' khỏi danh sách các trường hiển thị
        if (($key = array_search("filename", $visibleFields)) !== false) {
            unset($visibleFields[$key]);
        }

        // Lấy giá trị của các trường hiển thị
        foreach ($visibleFields as $field) {
            $method = 'get' . ucfirst($field);
            if (method_exists($element, $method)) {
                $value = $element->$method();
                // Chuyển đổi ngày cho các trường thuộc loại SYSTEM_CONVERT_DATE
                if (in_array($field, self::SYSTEM_CONVERT_DATE)) {
                    $value = date('Y/m/d', $value);
                }
                $data[$field] = $value;
            }
        }

        // Xử lý các loại element khác nhau (Asset, Document, DataObject)
        if ($element instanceof Asset) {
            $data = array_merge($data, [
                'type' => 'asset',
                'id' => $element->getId(),
                'subType' => $element->getType(),
                'fullpath' => 'Asset/' . $element->getType() . '/' . $element->getId(),
            ]);
        } elseif ($element instanceof Document) {
            $data = array_merge($data, [
                'type' => 'document',
                'id' => $element->getId(),
                'subType' => $element->getType(),
                'fullpath' => 'Document/' . $element->getType() . '/' . $element->getId(),
            ]);
        } elseif ($element instanceof DataObject\AbstractObject) {
            $data = array_merge($data, [
                'type' => 'object',
                'id' => $element->getId(),
                'subType' => $element->getClassName(),
                'fullpath' => 'DataObject/' . $element->getClassName() . '/' . $element->getId(),
            ]);
        }

        return $data;
    }

    public function getOption()
    {
        $layoutDefinition = $this->layout;

        $data = [];

        if ($layoutDefinition->objectsAllowed) {
            $classes = $layoutDefinition->classes;
            $blackList = ["user", "role"];
            $listObject = self::getClassList($blackList);

            $data[] = self::getRelationType($classes, ClassServices::KEY_OBJECT, 'classes', $listObject);
        }

        if ($layoutDefinition->documentsAllowed) {
            $document = $layoutDefinition->documentTypes;
            $listDocument = ['email', 'link', 'hardlink', 'snippet', 'folder', 'page'];

            $data[] = self::getRelationType($document, ClassServices::KEY_DOCUMENT, 'documentTypes', $listDocument);
        }

        if ($layoutDefinition->assetsAllowed) {
            $asset = $layoutDefinition->assetTypes;
            $listAsset = ['archive', 'image', 'audio', 'document', 'text', 'folder', 'video', 'unknown'];

            $data[] = self::getRelationType($asset, ClassServices::KEY_ASSET, 'assetTypes', $listAsset);
        }

        // if ($options && count($options) == 1) {
        //     $options = isset($options[0]['children']) ? $options[0]['children'] : [];
        //     if ($options && count($options) == 1) {
        //         $options = $options[0]['children'];
        //     }
        // }

        return $data;
    }

    public static function getObjectRelation($name, $fields)
    {
        $data = [];
        $dataobject = "Pimcore\\Model\\DataObject\\" . $name . '\Listing';

        $listing = new $dataobject();
        foreach ($listing as $key =>  $item) {
            $data[] = [
                'key' => $item->getKey(),
                'value' => $item->getId(),
                'classname' => $item->getClassname(),
            ];
        }
        return $data;
    }

    //type : loại trường đc cấu hình; model : asset, object , document
    public static function getRelationData($type, $model)
    {
        $data = [];
        $listing = '';
        $modelName = '';

        try {
            if ($model == ClassServices::KEY_OBJECT) {
                if ($type != 'All' && $type != 'folder') {
                    $modelName = "Pimcore\\Model\\" . $model . "\\" . $type . '\Listing';
                } else {
                    $modelName = "Pimcore\\Model\\" . $model . '\Listing';
                }
            } else {
                $modelName = "Pimcore\\Model\\" . $model . '\Listing';
            }

            $listing = new $modelName();
            if ($listing) {
                // if ($model != 'Asset') {
                //     $listing->setUnpublished(true);
                // }

                if ($model !== ClassServices::KEY_OBJECT && $type != 'All' || ($model == ClassServices::KEY_OBJECT && $type == 'folder')) {
                    $listing->setCondition('type = ?', [$type]);
                }

                foreach ($listing as $item) {
                    $key = ($model == ClassServices::KEY_ASSET) ? $item->getFilename() : $item->getKey();

                    $data[] = [
                        'key' => $key,
                        'value' => $item->getId(),
                        'type' => $model,
                        'label' => $item->getFullPath(),
                    ];
                }
            }

            return $data;
        } catch (\Throwable $th) {
            return $data;
        }
    }

    static public function getOptions($type, $layoutDefinition, $object = null)
    {
        $options = [];
        $allowedFieldTypes = ['manyToOneRelation', 'manyToManyRelation', 'advancedManyToManyRelation'];

        if (in_array($type, $allowedFieldTypes)) {
            if ($layoutDefinition->getObjectsAllowed()) {
                $classes = $layoutDefinition->getClasses();
                $blackList = ["user", "role"];
                $listObject = self::getClassList($blackList);

                $options[] = self::getRelationType($classes, ClassServices::KEY_OBJECT, 'classes', $listObject);
            }

            if ($layoutDefinition->getDocumentsAllowed()) {
                $document = $layoutDefinition->getDocumentTypes();
                $listDocument = ['email', 'link', 'hardlink', 'snippet', 'folder', 'page'];

                $options[] = self::getRelationType($document, ClassServices::KEY_DOCUMENT, 'documentTypes', $listDocument);
            }

            if ($layoutDefinition->getAssetsAllowed()) {
                $asset = $layoutDefinition->getAssetTypes();
                $listAsset = ['archive', 'image', 'audio', 'document', 'text', 'folder', 'video', 'unknown'];

                $options[] = self::getRelationType($asset, ClassServices::KEY_ASSET, 'assetTypes', $listAsset);
            }

            // if ($options && count($options) == 1) {
            //     $options = isset($options[0]['children']) ? $options[0]['children'] : [];
            //     if ($options && count($options) == 1) {
            //         $options = $options[0]['children'];
            //     }
            // }
        }

        $allowedObjectTypes = ['manyToManyObjectRelation', 'advancedManyToManyObjectRelation'];
        if (in_array($type, $allowedObjectTypes)) {
            $classes = $layoutDefinition->getClasses();
            $blackList = ["user", "role"];
            $listObject = self::getClassList($blackList);

            $options[] = self::getRelationType($classes, ClassServices::KEY_OBJECT, 'classes', $listObject);

            // if ($options && count($options) == 1) {
            //     $options = isset($options[0]['children']) ? $options[0]['children'] : [];
            //     if ($options && count($options) == 1) {
            //         $options = $options[0]['children'];
            //     }
            // }
        }

        // $optionTypes = ['gender', 'select', 'multiselect', 'booleanSelect'];
        // if (in_array($type, $optionTypes)) {
        //     $optionsProviderClass = $layoutDefinition->optionsProviderClass;
        //     if ($optionsProviderClass && class_exists($optionsProviderClass) && $object) {
        //         $optionProvider = new $optionsProviderClass;
        //         $options = $optionProvider->getOptions(compact('object'), $layoutDefinition);
        //     } else {
        //         $options = $layoutDefinition->getOptions();
        //     }
        // }

        // if ($type == 'video') {
        //     if ($layoutDefinition->getAllowedTypes() && count($layoutDefinition->getAllowedTypes())) {
        //         $options = $layoutDefinition->getAllowedTypes();
        //     } else $options = $layoutDefinition->getSupportedTypes();
        // }

        // if ($type == 'fieldcollections') {
        //     $options = $layoutDefinition->getAllowedTypes();
        // }

        return $options;
    }

    // danh sách các classes
    static public function getClassList($blackList)
    {
        $query = 'SELECT * FROM `classes` WHERE id NOT IN ("' . implode('","', $blackList) . '")';
        $classListing = Db::get()->fetchAllAssociative($query);
        $data = [];
        foreach ($classListing as $class) {
            $data[] = $class['name'];
        }

        return $data;
    }

    // danh sách các options theo type
    static public function getRelationType($key, $type, $typeKey, $listKey)
    {
        $options = [
            'label' => $type,
            'value' => $type,
        ];

        if ($key) {
            foreach ($key as $value) {
                $children = self::getRelationData($value[$typeKey], $type);
                if (count($children)) {
                    $datas =  [
                        'label' => $value[$typeKey],
                        'value' => $value[$typeKey],
                        'children' => $children
                    ];

                    $options['children'][] = $datas;
                }
            }
        } else {
            foreach ($listKey as $value) {
                $children = self::getRelationData($value, $type);
                if (count($children)) {
                    $datas =  [
                        'label' => $value,
                        'value' => $value,
                        'children' => $children
                    ];

                    $options['children'][] = $datas;
                }
            }
        }
        return $options;
    }

    public function getFrontEndType()
    {
        return 'relation';
    }
}

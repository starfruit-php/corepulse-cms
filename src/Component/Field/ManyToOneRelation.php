<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use CorepulseBundle\Services\ClassServices;
use CorepulseBundle\Services\Helper\SearchHelper;
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
            if ($id) {
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

    static public function mapTypes($items, $key) 
    {
        return array_map(fn($item) => $item[$key] ?? null, $items ?: []);
    }
    
    public function getOption()
    {
        $layoutDefinition = $this->layout;
        $allType = array_filter([
            $layoutDefinition->objectsAllowed ? 'object' : null,
            $layoutDefinition->documentsAllowed ? 'document' : null,
            $layoutDefinition->assetsAllowed ? 'asset' : null,
        ]);
    
        // Lấy danh sách các subtype
        $assetTypes = self::mapTypes($layoutDefinition->assetTypes, 'assetTypes');
        $documentTypes = self::mapTypes($layoutDefinition->documentTypes, 'documentTypes');
        $classes = self::mapTypes($layoutDefinition->classes, 'classes');
        $config = [
            'types' => $allType,
            'classes' => $classes,
        ];

        $subtypes = [
            'asset' => $assetTypes,
            'document' => $documentTypes,
            // 'object' => [],
        ];

        return ClassServices::getCommonOptions($config, $subtypes);
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

    public function getFrontEndType()
    {
        return 'relation';
    }
}

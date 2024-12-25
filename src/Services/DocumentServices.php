<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Services\ClassServices;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Asset;
use Pimcore\Model\User as AdminUser;
use Pimcore\Db;

class DocumentServices
{
    const KEY_DOCUMENT = 'Document';
    const KEY_OBJECT = 'DataObject';
    const KEY_ASSET = 'Asset';

    public static function isParent($id)
    {
        $select = Db::get()->fetchAssociative('SELECT count(*) FROM `documents` WHERE `parentId` = ?', [$id]);

        return reset($select);
    }

    static public function processSetting($document, $key, $value)
    {
        $params = ['controller', 'template', 'prettyUrl', 'title', 'description'];
        try {
            if (in_array($key, $params)) {
                $method = "set" . ucfirst($key);
                if (method_exists($document, $method)) {
                    $document->$method($value);
                }
            }
        
            return $document;
        } catch (\Throwable $th) {
            $param['error'] = $th->getMessage();
            return $param;
        }
    }
    static public function processField($document, $param) 
    {
        if(self::checkBlockName($param['name'])) return $document;
        
        $fieldType = $param['type'];
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);

        try {
            if (class_exists($getClass)) {
                $component = new $getClass($document, $param, $param['data'], null, false);
                if ($fieldType == 'block') {
                    return $component->getDataSave();
                } else {
                    $document->setEditable($component->getDataSave());
                }
            }
        
            return $document;
        } catch (\Throwable $th) {
            $param['error'] = $th->getMessage();
            return $param;
        }
    }

    static public function checkBlockName($string) {
        $hasColonAndNumber = preg_match('/:\d+/', $string);
        
        $hasDot = strpos($string, '.') !== false;
        
        return $hasColonAndNumber && $hasDot;
    }

    public static function createDoc($key, $title, $type, $parentId)
    {
        $page = new \Pimcore\Model\Document\Page();

        if ($type == 'Snippet') {
            $page = new \Pimcore\Model\Document\Snippet();
        } elseif ($type == 'Link') {
            $page = new \Pimcore\Model\Document\Link();
        } elseif ($type == 'Email') {
            $page = new \Pimcore\Model\Document\Email();

            $folder =  Document::getByPath("/emails");
            if (!$folder) {
                $folder = new \Pimcore\Model\Document\Folder();
                $folder->setKey('emails');
                $folder->setParentId(1);
                $folder->save();
            }
            $parentId = $folder->getId();
        } elseif ($type == 'Hardlink') {
            $page = new \Pimcore\Model\Document\Hardlink();
        } elseif ($type == 'Folder') {
            $page = new \Pimcore\Model\Document\Folder();
        } elseif ($type == 'Page') {
            $page->setTitle($title);
        }

        $page->setKey($key);
        $page->setParentId($parentId);

        $page->save();

        return $page;
    }

    // lấy dữ liệu đổ vào cái field tương ứng
    public static function getDataDocument($document) {
        $data = [];

        if ($document) {
            foreach ($document->getEditables() as $key => $value) {
                if (!strpos($key, ":") === false) continue;
                
                $fieldType = $value->getType();
                $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);
        
                try {
                    if (class_exists($getClass)) {
                        $component = new $getClass($document, $value, null, null, false);
                        $data[$key] = $component->getValue();
                    }
                
                } catch (\Throwable $th) {
                    // dd($fieldType, $th->getMessage());
                }
            }
        }
        return $data;
    }

    static public function getSidebar($document)
    {
        $userOwner =  AdminUser::getById($document->getUserOwner());
        $userModification = AdminUser::getById($document->getUserModification());

        $data = [
            'id' => $document->getId(),
            'key' => $document->getKey(),
            'published' => $document->getPublished(),
            'fullPath' => $document->getFullPath(),
            'creationDate' => $document->getCreationDate() ? date('d-m-Y', $document->getCreationDate()) : '',
            'modificationDate' => $document->getModificationDate() ? date('d-m-Y', $document->getModificationDate()) : '',
            'type' => $document->getType(),
            // 'template' => $document->getTemplate(),
            // 'controller' => $document->getController(),
            'userOwner' => $userOwner ? $userOwner->getName() : '',
            'userModification' => $userModification ? $userModification->getName() : '',
        ];

        return $data;
    }

    // lấy danh sách các field xuất hiện trong 1 item của block
    public static function filterArray($inputArray, $filterKey) {
        $filteredValues = [];

        foreach ($inputArray as $key => $value) {
            if (strpos($key, $filterKey) === 0) {
                // Remove the filter key prefix from the current key
                $cleanKey = substr($key, strlen($filterKey) + 1);

                // Add the cleaned key to the result array
                $filteredValues[$key] = $cleanKey;
            }
        }

        return $filteredValues;
    }

    // lấy danh sách name các field xuất hiện trong block
    public static function getEditableBlock($document, $nameBlock) {
        $filteredArray = self::filterArray($document->getEditables(), $nameBlock);
        $arrTypeChillBlock = [];
        foreach ($filteredArray as $k => $v) {
            $type = explode('.', $v);
            $arrTypeChillBlock[$k] = $document->getEditable($k)->getType();
        }

        return $arrTypeChillBlock;
    }

    static public function getOption($config)
    {
        $subtypes = isset($config['subtypes']) ? $config['subtypes'] : [];
        return ClassServices::getCommonOptions($config, $subtypes);
    }
}

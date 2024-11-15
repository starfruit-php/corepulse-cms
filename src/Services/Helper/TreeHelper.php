<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject;

class TreeHelper
{
    public static function checkRoot($root)
    {
        $config = ['DataObject', 'Document', 'Asset'];

        return in_array($root, $config);
    }

    public static function getListing($root)
    {
        $modelName = 'Pimcore\\Model\\' . $root . '\Listing';
        $listing = new $modelName();

        if ($root != 'Asset') {
            $listing->setUnpublished(true);
        }

        if ($root == 'DataObject') {
            $listing->setObjectTypes([DataObject::OBJECT_TYPE_FOLDER, DataObject::OBJECT_TYPE_OBJECT, DataObject::OBJECT_TYPE_VARIANT]);
        }

        return $listing;
    }

    public static function getTreeItemJson($item, $root)
    {
        $dataChildren = [];

        foreach ($item->getChildren([DataObject::OBJECT_TYPE_FOLDER, DataObject::OBJECT_TYPE_OBJECT, DataObject::OBJECT_TYPE_VARIANT], true) as $children) {
            $dataChildren[] = (string)$children->getId();
        }

        $data = [
            'id' => $item->getId(),
            'type' => $item->getType(),
            'children' => $dataChildren,
            'icon' => SearchHelper::getIcon($item->getType()),
        ];

        if ($root == 'Asset') {
            $data['key'] = $item->getFileName();
            $data['publish'] = true;
        } elseif ($root == 'DataObject') {
            $data['key'] = $item->getKey();
            $data['published'] = $item->getType() != 'folder' ? $item->getPublished() : true;
            $data['classId'] = $item->getType() != 'folder' ? $item->getClassId() : 'tree-folder';
        } elseif ($root == 'Document') {
            $data['key'] = $item->getKey();
            $data['publish'] = $item->getType() != 'folder' ? $item->getPublished() : true;
        }

        return $data;
    }

    public static function getItemJson($item, $root)
    {
        $data = [
            'id' => (string)$item->getId(),
            'type' => $item->getType(),
            "path" => $item->getFullPath(),
            "parentId" => (string)$item->getParentId(),
            "unSelecte" => false
        ];

        if ($root == 'Asset') {
            $data['fileName'] = $item->getFileName();
        } elseif ($root == 'DataObject') {
            $data['key'] = $item->getKey();
            $data['published'] = $item->getType() != 'folder' ?($item->getPublished() ? 'published' : 'unpublished') : '';
            $data['classId'] = $item->getType() != 'folder' ? $item->getClassId() : 'tree-folder';
            $data["className"] = $item->getType() != 'folder' ? $item->getClassName() : '';
        } elseif ($root == 'Document') {
            $data['key'] = $item->getKey();
            $data['published'] = $item->getType() != 'folder' ?($item->getPublished() ? 'published' : 'unpublished') : '';
        }

        return $data;
    }

    public static function getObjectItemJson($item)
    {
        $dataChildren = [];

        // foreach ($item->getChildren([DataObject::OBJECT_TYPE_FOLDER, DataObject::OBJECT_TYPE_OBJECT, DataObject::OBJECT_TYPE_VARIANT], true) as $children) {
        //     $dataChildren[] = (string)$children->getId();
        // }

        $data = [
            'id' => (string)$item->getId(),
            'type' => $item->getType(),
            'children' => $dataChildren,
            'icon' => SearchHelper::getIcon($item->getType()),
            "parentId" => (string)$item->getParentId(),
            "path" => $item->getFullPath(),
        ];

        $data['key'] = $item->getKey() ? $item->getKey() : 'Home' ;
        $data['published'] = $item->getType() != 'folder' ? $item->getPublished() : true;
        $data['classId'] = $item->getType() != 'folder' ? $item->getClassId() : 'tree-folder';
        $data["className"] = $item->getType() != 'folder' ? $item->getClassName() : '';

        // if ($item->getType() !== 'folder' && count($dataChildren)) {
        //     $data['state'] = [
        //         'opened' => true,
        //     ];
        // }
        return $data;
    }
}

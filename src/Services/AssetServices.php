<?php

namespace CorepulseBundle\Services;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;

class AssetServices
{
    public static function createFolder($folderName, $folderId = '')
    {
        if ($folderId) {
            $parentId = Asset::getById($folderId);
            if ($parentId) {
                $folderName = str_replace($folderId == 1 ? 'null' : $folderId, $parentId->getFilename(), $folderName);
            }
        }

        $folder = Asset::getByPath($folderName) ?? Asset\Service::createFolderByPath($folderName);

        return $folder;
    }

    public static function createFile($file, $folder)
    {
        try {
            $asset = new Asset();
            $filename = time() . '-' . $file->getClientOriginalName();

            // convent filename
            $filename = preg_replace('/[^a-zA-Z0-9.]/', '-', $filename);
            $filename = preg_replace('/-+/', '-', $filename);
            $filename = trim($filename, '-');

            $asset->setFilename($filename);
            $asset->setData(file_get_contents($file));
            $asset->setParent($folder);

            $asset->save();

            return $asset;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function getJson($item)
    {
        $languages = \Pimcore\Tool::getValidLanguages();

        $format = '';
        if ($item->getMimeType()) {
            $format = explode('/', $item->getMimeType());
            $format = $format[1];
        }

        $sidebar = [
            'id' => $item->getId(),
            'filename' => $item->getFileName(),
            'publicURL' => $item->getFrontendPath(),
            'path' => $item->getPath() . $item->getFileName(),
            'fileSize' => round((int) $item->getFileSize() / (1024 * 1024), 3) . " MB",
            'fileType' => $format,
            'type' => $item->getType(),
            'mimetype' => $item->getMimetype(),
            'creationDate' => date('Y/m/d', $item->getCreationDate()),
            'modificationDate' => date('Y/m/d', $item->getModificationDate()),
        ];

        if ($item->getType() == 'image') {
            $sidebar['width'] = $item->getWidth();
            $sidebar['height'] = $item->getHeight();
        }

        $metaData = [];
        foreach ($item->getMetaData() as $metaItem) {
            $metaData[] = self::getMetaData($metaItem);
        }

        $json = [
            'sidebar' => $sidebar,
            'languages' => $languages,
            'metaData' => $metaData,
            'customSettings' => $item->getCustomSettings(),
        ];

        return $json;
    }

    public static function getMetaData($item)
    {
        $name = $item['name'] ?? null;
        $language = $item['language'] ?? null;
        $type = $item['type'] ?? null;
        $element = $item['data'] ?? null;

        $data = null;
        if ($element) {
            $data = $element;
            if ($element instanceof Asset) {
                $data = [
                    'type' => 'asset',
                    'id' => $element->getId(),
                    'subType' => $element->getType(),
                    'fullpath' => 'Asset/' . $element->getType() . '/' . $element->getId(),
                ];
            } elseif ($element instanceof Document) {
                $data = [
                    'type' => 'document',
                    'id' => $element->getId(),
                    'subType' => $element->getType(),
                    'fullpath' => 'Document/' . $element->getType() . '/' . $element->getId(),
                ];
            } elseif ($element instanceof DataObject\AbstractObject) {
                $data = [
                    'type' => 'object',
                    'id' => $element->getId(),
                    'subType' => $element->getClassName(),
                    'fullpath' => 'DataObject/' . $element->getClassName() . '/' . $element->getId(),
                ];
            } elseif ($type === 'date') {
                $data = date('Y-m-d', $element);
            } 
        }

        $item['data'] = $data;
        return $item;
    }

    public static function update($asset, $params)
    {
        foreach ($params as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($asset, $method)) {
                $asset->{$method}($value);
            }
        }
        $asset->save();

        return $asset;
    }

    public static function updateMetaData($asset, $metaData)
    {
        $datas = [];
        foreach ($metaData as $key => $value) {
            $data = $value;
            if (in_array($value['type'], ['asset','document', 'object', 'dataobject'])) {
                $data['data'] = self::formatDataSave($value['data']);
            } elseif ($value['type'] === 'date') {
                $data['data'] = strtotime($value['data']);
            }

            $datas[] = $data;
        }
        $asset->setMetaData($datas);
        $asset->save();
        return $asset;
    } 

    public static function formatDataSave($value)
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
}

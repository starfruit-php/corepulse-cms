<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Controller\Cms\FieldController;
use Pimcore\Db;
use DateTime;
use Pimcore\Model\Document;
use Pimcore\Model\Asset;
use Pimcore\Model\User as AdminUser;

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

    static public function processField($document, $param) 
    {
        $fieldType = $param['type'];
        $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($fieldType);

        try {
            if (class_exists($getClass)) {
                $component = new $getClass($document, $param, $param['data'], null, false);
                $document->setEditable($component->getDataSave());
            }
        
            return $document;
        } catch (\Throwable $th) {
            $param['error'] = $th->getMessage();
            return $param;
        }
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

    public static function getListClass($type, $request)
    {
        $search = $request->get('search');

        $conditionQuery = "id != 1 AND type != 'folder'";
        $conditionParams = [];

        $listClass = [];
        if ($type == "document") {
            if ($search) {
                $conditionQuery .= ' AND `key` LIKE :search';
                $conditionParams['search'] = '%' . $search . '%';
            }
            $documentListing = new \Pimcore\Model\Document\Listing();
            $documentListing->setCondition($conditionQuery, $conditionParams);
            foreach ($documentListing as $doc) {
                    $listClass[] = [
                        'id' => $doc->getId(),
                        'name' => $doc->getKey(),
                        'subtype' => $doc->getType(),
                        'type' => 'document',
                    ];
            }
        }
        if ($type == "asset") {
            if ($search) {
                $conditionQuery .= ' AND filename LIKE :search';
                $conditionParams['search'] = '%' . $search . '%';
            }
            $assetListing = new \Pimcore\Model\Asset\Listing();
            $assetListing->setCondition($conditionQuery, $conditionParams);
            foreach ($assetListing as $asset) {
                $listClass[] = [
                    'id' => $asset->getId(),
                    'name' => $asset->getKey(),
                    'subtype' => $asset->getType(),
                    'type' => 'asset',
                ];
            }
        }
        if ($type == "object") {
            if ($search) {
                $conditionQuery .= ' AND `key` LIKE :search';
                $conditionParams['search'] = '%' . $search . '%';
            }
            $objectListing = new \Pimcore\Model\DataObject\Listing();
            $objectListing->setCondition($conditionQuery, $conditionParams);
            foreach ($objectListing as $object) {
                $listClass[] = [
                    'id' => $object->getId(),
                    'name' => $object->getKey(),
                    'subtype' => $object->getType(),
                    'type' => 'object',
                    'class' => ($object->getType() != 'folder') ? $object?->getClass()->getName() : '',
                ];
            }
        }

        if ($type == "snippet") {
            $conditionQuery .= ' AND `type` = :type';
            $conditionParams['type'] = $type;

            if ($search) {
                $conditionQuery .= ' AND `key` LIKE :search';
                $conditionParams['search'] = '%' . $search . '%';
            }
            $documentListing = new \Pimcore\Model\Document\Listing();
            $documentListing->setCondition($conditionQuery, $conditionParams);
            foreach ($documentListing as $doc) {
                    $listClass[] = [
                        'id' => $doc->getId(),
                        'name' => $doc->getKey(),
                        'subtype' => $doc->getType(),
                        'type' => 'document',
                    ];
            }
        }

        return $listClass;
    }

    // lấy dữ liệu đổ vào cái field tương ứng
    public static function getDataDocument($document) {
        $data = [];

        if ($document) {
            foreach ($document->getEditables() as $key => $value) {
                $type = $value->getType();
                $function = 'get'. ucwords($type);
                $data[$key] = FieldServices::{$function}($document, $value);
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

    // lấy danh sách các field xuất hiện trong block được user set
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

    // lấy danh sách name các field xuất hiện trong block được user set
    public static function getEditableBlock($document, $nameBlock) {
        $filteredArray = self::filterArray($document->getEditables(), $nameBlock);
        $arrTypeChillBlock = [];
        foreach ($filteredArray as $k => $v) {
            $type = explode('.', $v);
            $arrTypeChillBlock[$k] = $document->getEditable($k)->getType();
        }

        return $arrTypeChillBlock;
    }


    // lấy dữ liệu của các field trong block
    public static function getDataBlock($document, $arrTypeChillBlock) {
        $list = [];
        $arrType = ['input', "wysiwyg", "textarea"];
        foreach ($arrTypeChillBlock as $keyBlockT => $type) {
            $titleBlock = '';
            if (in_array($type, $arrType)) {
                $titleBlock = $document->getEditable($keyBlockT)?->getText();
            }

            $contentField = $titleBlock;
            if ($type == 'image' || $type == 'video' || $type == 'pdf') {
                $idImage = $document->getEditable($keyBlockT)?->getId();
                $asset = Asset::getById((int)$idImage);
                if ($asset) {
                    $contentField = $asset->getFullPath();
                }
            }
            if ($type == "video") {
                $contentField =  $document->getEditable($keyBlockT)->getData();
            }
            if ($type == "date") {
                $contentField = $document->getEditable($keyBlockT)?->getDate()?->format('Y-m-d');
            }
            if ($type == "checkbox") {
                $contentField = $document->getEditable($keyBlockT)?->getValue();
            }
            if ($type == 'link') {
                // dd($document->getEditable($keyBlockT));
                if ($document->getEditable($keyBlockT)?->getData()) {
                    $contentField = $document->getEditable($keyBlockT)?->getData();
                } else {
                    $contentField = [
                        "internalType" => "",
                        "linktype" => "",
                        "text" => "",
                        "path" => "",
                        "internal" => "",
                        "internalId" => "",
                    ];
                }
            }
            if ($type == 'numeric') {
                $contentField = $document->getEditable($keyBlockT)?->getNumber();
            }
            if ($type == 'multiselect') {
                $contentField = $document->getEditable($keyBlockT)?->getValues();
            }
            if ($type == 'table') {
                $contentField = $document->getEditable($keyBlockT)?->getData();
            }
            if ($type == 'relation') {
                $subtype = $document->getEditable($keyBlockT)->getSubtype();
                $contentField = [
                    0 => ($subtype != "document" || $subtype != "asset") ? "DataObject" : $subtype,
                    1 =>$document->getEditable($keyBlockT)->getElement()?->getClassName(),
                    2 => $document->getEditable($keyBlockT)->getId(),
                ];
                // dd($contentField);
            }
            if ($type == 'snippet') {
                $idSnippet = $document->getEditable($keyBlockT)?->getId();
                $snippeted = Document::getById((int)$idSnippet);

                $contentField = [
                    'id' => $snippeted->getId(),
                    'name' => $snippeted->getKey(),
                    'subtype' => $snippeted->getType(),
                    'type' => 'documment',
                ];
            }

            if ($type == 'select') {
                $contentField = $document->getEditable($keyBlockT)?->getData();
            }

            $list[$keyBlockT] =
            [
                'type' => $type,
                'value' => $contentField
            ];
        }

        return $list;
    }

    static public function getOptions($type, $arrTypes)
    {
        $options = [];
        $allowedFieldTypes = ['manyToOneRelation', 'manyToManyRelation', 'advancedManyToManyRelation'];

        if (in_array($type, $allowedFieldTypes)) {

            if (!$arrTypes) {
                $arrTypes = [
                    'object' => [],
                    'document' => [],
                    'asset' => [],
                ];
            }

            foreach ($arrTypes as $key => $value) {
                if ($key == 'object') {
                    $classes = $value;
                    $blackList = ["user", "role"];
                    $listObject = FieldController::getClassList($blackList);

                    $options[] = FieldController::getRelationType($classes, self::KEY_OBJECT, 'classes', $listObject);
                }

                if ($key == 'document') {
                    $document = $value;
                    $listDocument = ['email', 'link', 'hardlink', 'snippet', 'folder', 'page'];

                    $options[] = FieldController::getRelationType($document, self::KEY_DOCUMENT, 'documentTypes', $listDocument);
                }

                if ($key == 'asset') {
                    $asset = $value;
                    $listAsset = ['archive', 'image', 'audio', 'document', 'text', 'folder', 'video', 'unknown'];

                    $options[] = FieldController::getRelationType($asset, self::KEY_ASSET, 'assetTypes', $listAsset);
                }
            }

        }

        return $options;
    }


    static public function setDataBlock($v, $blockSave, $notSave)
    {
        if ($v->type == "link" && $v->value) {
            $dataInternal = [
                "internalType" => null,
                "linktype" => "direct",
                "internal" => false,
                "internalId" => null,
            ];
            if (property_exists($v->value, 'path')) {
                if (\Pimcore\Model\Document::getByPath($v->value->path)) {
                    $id = \Pimcore\Model\Document::getByPath($v->value->path)->getId();
                    $dataInternal = [
                        "internalType" => "document",
                        "linktype" => "internal",
                        "text" => $v->value->text,
                        "path" => $v->value->path,
                        "internal" => true,
                        "internalId" => $id,
                    ];
                } elseif (\Pimcore\Model\Asset::getByPath($v->value->path)) {
                    $id = \Pimcore\Model\Asset::getByPath($v->value->path)->getId();
                    $dataInternal = [
                        "internalType" => "asset",
                        "linktype" => "internal",
                        "text" => $v->value->text,
                        "path" => $v->value->path,
                        "internal" => true,
                        "internalId" => $id,
                    ];
                } elseif (\Pimcore\Model\DataObject::getByPath($v->value->path)) {
                    $id = \Pimcore\Model\DataObject::getByPath($v->value->path)->getId();
                    $dataInternal = [
                        "internalType" => "object",
                        "linktype" => "internal",
                        "text" => $v->value->text,
                        "path" => $v->value->path,
                        "internal" => true,
                        "internalId" => $id,
                    ];
                }
            }
            $blockSave->setDataFromEditmode($dataInternal);
        }
        if ($v->type == 'relation') {
            if ($v->value) {
                if (is_array($v->value)) {
                    $dataSave = [
                        'id' => (int) $v->value[2],
                        'type' => strtolower($v->value[0]) == "dataobject" ? 'object' : strtolower($v->value[0]),
                        'subtype' => $v->value[1],
                    ];
                }
                // else {
                //     $dataSave = [
                //         'id' => (int) $v->value->id,
                //         'type' => strtolower($v->value->type) == "dataobject" ? 'object' : strtolower($v->value->type),
                //         'subtype' => $v->value->subtype,
                //     ];
                // }
                $blockSave->setDataFromEditmode($dataSave);
            }
        }
        if ($v->type == 'pdf') {
            $asset = Asset::getByPath($v->value);
            if ($asset) {
                $idPdf = $blockSave?->setDataFromEditmode([
                    'id' => (int) $asset->getId(),
                ]);
            }
        }
        if ($v->type == 'video') {
            $asset = Asset::getByPath($v->value->path);
            $asset ? $assetId = $asset->getId() : $assetId = '';
            $dataVideo = [
                'id' => $assetId,
                'type' => $v->value->type,
                'allowedTypes' => ['asset', 'youtube', 'vimeo', 'dailymotion'],
                'title' => $v->value->title,
                'description' => $v->value->description,
                'path' => $v->value->path,
                'poster' => $v->value->poster,
            ];
            $infoVideo = $blockSave?->setDataFromEditmode($dataVideo);
        }
        if ($v->type == 'image') {
            $path = $v->value;
            if (!empty($path)) {
                if (substr($path, 0, 4) == "http") {
                    $prefix = \Pimcore::getContainer()->getParameter('pimcore.config')['assets']['frontend_prefixes']['source'];
                    if ($prefix) {
                        $path = substr($path, strlen($prefix));
                    }
                }
                $asset = Asset::getByPath($path);
                if ($asset) {
                    $idImage = $blockSave?->setId($asset->getId());
                }
            }
        }

        if (!in_array($v->type, $notSave)) {
            $blockSave?->setDataFromResource($v->value);
        }

        return $blockSave;

    }
}

<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Model\Document;
use Pimcore\Model\Document\Editable;

use App\Helper\ObjectJson;
use App\Helper\Text\PrettyText;

class DocumentHelper
{
    public static function getPageUrl(Document $page)
    {
        try {
            return $page->getPrettyUrl() ?? '/' . strtolower(PrettyText::getPretty($page->getTitle() ?? 'page')) . '~' . $page->getId();
        } catch (\Throwable $e) {
        }

        return null;
    }

    public static function getDataByDocument($document)
    {
        $data = [];
        $blockEditables = [];

        try {
            if ($document instanceof Document\Page || $document instanceof Document\Snippet) {
                $editables = $document->getEditables();

                foreach ($editables as $field => $editable) {
                    if ($editable instanceof Editable\Block) {
                        $blockEditables[] = $field;
                    }

                    $data[$field] = self::getValueByType($editable);
                }

                if (!empty($blockEditables)) {
                    foreach ($blockEditables as $field) {
                        $totalLoop = $data[$field];

                        unset($data[$field]); // xóa dữ liệu block ban đầu

                        foreach ($totalLoop as $loop) {
                            $loopData = []; // dữ liệu mới cho block

                            foreach ($data as $name => $value) {
                                $find = $field . ":" . $loop . ".";
                                if (strpos($name, $find) !== false) {
                                    $elmentName = substr($name, strlen($find));

                                    $loopData[$elmentName] = $value;

                                    unset($data[$name]);
                                }
                            }

                            $data[$field][] = $loopData;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $data;
    }

    public static function getDataByPath($path)
    {
        $data = [];
        try {
            $document = Document::getByPath($path);
            if ($document instanceof Document\Link) {
                $document = Document::getByPath($document->getHref());
            }
            return self::getDataByDocument($document);
        } catch (\Throwable $e) {
        }

        return $data;
    }

    public static function getValueByType($editable)
    {
        if (
            $editable instanceof Editable\Input
            || $editable instanceof Editable\Textarea
            || $editable instanceof Editable\Checkbox
            || $editable instanceof Editable\Select
            || $editable instanceof Editable\Multiselect
            || $editable instanceof Editable\Block
        ) {
            return $editable->getData();
        }

        if ($editable instanceof Editable\Wysiwyg) {
            return PrettyText::formatWysiwyg($editable->getData());
        }

        if ($editable instanceof Editable\Date) {
            return $editable->getData() ? $editable->getData()->format('d-m-Y') : null;
        }

        if ($editable instanceof Editable\Image) {
            return $editable->isEmpty() ?  null : AssetHelper::getLink($editable->getImage());
        }

        if ($editable instanceof Editable\Relation) {
            return $editable->isEmpty() ? null : ObjectJson::getJson($editable->getElement());
        }

        if ($editable instanceof Editable\Relations) {
            $elements = [];

            if (!$editable->isEmpty()) {
                foreach ($editable->getElements() as $element) {
                    if (method_exists($element, "getJson")) {
                        $elements[] = $element->getJson();
                    } else {
                        $elements[] = ObjectJson::getJson($element);
                    }
                }
            }

            return $elements;
        }

        if ($editable instanceof Editable\Link) {
            $link = [
                'isPage' => false,
                'urlOrSlug' => '',
                'nameOrTitle' => ''
            ];

            if (!$editable->isEmpty()) {
                $internal = $editable->getData()['internal'];

                $link['urlOrSlug'] = $editable->getHref();
                $link['nameOrTitle'] = $editable->getText();
                if ($internal) {
                    $internalType = $editable->getData()['internalType'];

                    if ($internalType == 'document') {
                        $internalId = $editable->getData()['internalId'];

                        $page = Document::getById($internalId);

                        if ($page) {
                            $link['isPage'] = true;
                            $link['urlOrSlug'] = self::getPageUrl($page);
                        }
                    }
                }
            }

            return $link;
        }

        if ($editable instanceof Editable\Renderlet) {
            if ($editable->getSubType() == "folder") {
                $folder = $editable->getO();

                if ($folder instanceof \Pimcore\Model\Asset\Folder) {
                    $imageFolders = [];
                    $images = $folder->getChildren();

                    foreach ($images as $image) {
                        if ($image instanceof \Pimcore\Model\Asset\Image) {

                            $imageLink = AssetHelper::getLink($image);

                            if ($imageLink) {
                                $imageFolders[] = $imageLink;
                            }
                        }
                    }

                    return $imageFolders;
                }
            }
        }

        if ($editable instanceof Editable\Video) {
            $editableData = $editable->getData();

            $type = $editableData['type'];
            $id = $editableData['id'];

            $data = [
                'type' => $type,
                'data' => '',
                'link' => $id,
                'image' => ''
            ];
            if ($type == 'asset') {
                $link = AssetHelper::getLink(\Pimcore\Model\Asset::getById($id));

                $data['data'] = $link;
            }

            if ($type == 'youtube') {
                $videoID = explode("?v=", $id);
                if (count($videoID) == 1) {
                    $videoID = $videoID[0];
                } else {
                    $videoID = $videoID[1];
                    $videoID = explode("&", $videoID)[0];
                }

                $data['data'] = $videoID;
                $data['image'] = "https://img.youtube.com/vi/" . $videoID . "/0.jpg";
            }

            return $data;
        }

        return $editable->getData();
    }
}

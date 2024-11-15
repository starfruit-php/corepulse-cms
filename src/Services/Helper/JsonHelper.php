<?php

namespace CorepulseBundle\Services\Helper;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Tool;
use Pimcore\Model\DataObject\Events;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject;
use CorepulseBundle\Services\Helper\BlockJson;
use CorepulseBundle\Controller\Cms\FieldController;
use CorepulseBundle\Services\AssetServices;
use Pimcore\Model\Asset\Image\Thumbnail;

// use HelperBundle\Helper\LogHelper;
// use HelperBundle\Helper\Text\PrettyText;

class JsonHelper
{
    const LOG_FILE_NAME = 'helper_json';
    const KEY_DOCUMENT = 'Document';
    const KEY_OBJECT = 'DataObject';
    const KEY_ASSET = 'Asset';

    public static function getBaseJson(object $dataObject, array $fieldDefinitions, array $hiddenFields = [], bool $allowInherit = false, $lang = 'vi')
    {
        $json = [];
        // try {
        foreach ($fieldDefinitions as $name => $type) {
            if ($type instanceof Data\Localizedfields) {
                $childs = $type->getChildren();

                foreach ($childs as $childType) {
                    $childName = $childType->getName();

                    if (!$childType->invisible && !in_array($childName, $hiddenFields)) {
                        $json[$childName] = self::getValueByType($dataObject, $childType, $lang);
                    }
                }
            } else {
                if (!$type->invisible && !in_array($name, $hiddenFields)) {
                    $json[$type->getName()] = self::getValueByType($dataObject, $type, $lang);
                }
            }
        }
        // } catch (\Throwable $e) {
        //     LogHelper::logError(self::LOG_FILE_NAME, (string) ($e ."\n \n"));
        // }
        return $json;
    }

    public static function getValueByType($dataObject, $type, $lang)
    {
        $getFunction = 'get' . ucfirst($type->getName());
        $value = $dataObject->$getFunction();

        if ($type instanceof Data\Geopoint) {

            $data = [];

            $value = $dataObject->$getFunction();

            $data['longitude'] = $value ? $value->getLongitude() : '';
            $data['latitude'] = $value ? $value->getLatitude() : '';

            return $data;
        }

        if ($type instanceof Data\Date) {

            $value = $dataObject->$getFunction();

            return $value ? $value->format('Y/m/d') : null;
        }

        if ($type instanceof Data\Datetime) {
            $value = $dataObject->$getFunction();

            return $value ? $value->format('Y/m/d H:i') : null;
        }

        if ($type instanceof Data\DateRange) {
            $value = $dataObject->$getFunction();
            // dd($value->getStartDate()->format('Y/m/d'));
            return $value ? [$value->getStartDate()->format('Y/m/d'), $value->getEndDate()->format('Y/m/d')] : null;
        }

        if ($type instanceof Data\Checkbox) {
            return $dataObject->$getFunction() ?? false;
        }

        if ($type instanceof Data\Fieldcollections) {
            $data = [];

            $items = $dataObject->$getFunction() ? $dataObject->$getFunction()->getItems() : [];

            foreach ($items as $field) {

                $itemField = [];

                $textListing = "Pimcore\\Model\\DataObject\\Fieldcollection\\Data\\" . $field->getType();
                $listing = new $textListing();
                foreach ($listing->getDefinition()->getLayoutDefinitions()->getChildren() as $k => $item) {
                    $itemField[$field->getType()] = FieldController::extractStructure($item, $field, true, $lang);
                }
                $data[] = $itemField;
            }

            return $data;
        }

        if ($type instanceof Data\Objectbricks) {
            $data = [];

            $items = $dataObject->$getFunction()->getItems();

            if (count($items) > 0) {
                foreach ($items as $item) {
                    if (method_exists($item, 'getJson')) {
                        $data[] = $item->getJson();
                    } else {
                        $data[] = BrickJson::getJson($item);
                    }
                }
            }

            return $data;
        }

        if ($type instanceof Data\Image) {
            $image = $dataObject->$getFunction();
            $publicURL = '';

            if ($image) {
                $publicURL = AssetServices::getThumbnailPath($image);
            }

            return $publicURL;
            // return $image ? Tool::getHostUrl() . $image->getFullPath() : null;
        }

        if ($type instanceof Data\ImageGallery) {
            $images = [];
            $items = $dataObject->$getFunction() ? $dataObject->$getFunction()->getItems() : [];

            if (count($items) > 0) {
                foreach ($items as $item) {
                    if ($item) {
                        $hotpot = $item->getImage();

                        if ($hotpot) {
                            $publicURL = AssetServices::getThumbnailPath($hotpot);

                            $images[] = [
                                'fullPath' => $publicURL,
                                'path' => $hotpot->getFullPath(),
                                'id' => $hotpot->getId(),
                            ];
                            // $images[] = $hotpot->getFullPath();
                            // $images[] = Tool::getHostUrl() . $hotpot->getFullPath();
                        }
                    }
                }
            }

            return $images;
        }

        if ($type instanceof Data\Video) {
            $video = $dataObject->$getFunction();
            $data = '';
            $poster = '';
            $title = '';
            $videoType = '';
            $description = '';

            if ($video) {
                $videoType = $video->getType();

                if (in_array($videoType, ['youtube', 'vimeo', 'dailymotion'])) {
                    $data = $video->getData();
                } else if ($videoType == 'asset') {
                    if ($video->getData()) $data = $video->getData()->getFullPath();
                }

                $title = $video->getTitle();
                $description = $video->getDescription();
                $poster = $video->getPoster() ? $video->getPoster()->getFullPath() : '';
            }

            $datas = [
                'type' => $videoType,
                'data' => $data,
                'title' => $title,
                'description' => $description,
                'poster' => $poster,
            ];

            return $datas;
        }

        if (
            $type instanceof Data\ManyToManyObjectRelation ||
            $type instanceof Data\ManyToManyRelation
        ) {

            $data = [];
            $items = $dataObject->$getFunction();

            if (count($items) > 0) {
                foreach ($items as $item) {
                    // if (method_exists($item, 'getJson')) {
                    //     $data[] = $item->getJson();
                    // } else {
                    //     $data[] = ObjectJson::getJson($item);
                    // }

                    $data[] = BlockJson::getValueRelation($item, 3);
                }
            }

            return $data;
        }

        if ($type instanceof Data\ManyToOneRelation) {
            $item = $dataObject->$getFunction();

            if ($item) {
                // if (method_exists($item, 'getJson')) {
                //     return $item->getJson();
                // } else {
                //     return ObjectJson::getJson($item);
                // }

                return BlockJson::getValueRelation($item, 3);
            }

            return null;
        }

        if ($type instanceof Data\Block) {
            $items = $dataObject->$getFunction();
            $blockType = [];
            foreach ($type->getFieldDefinitions() as $key => $item) {
                // dd();
                if ($item->getFieldType() == 'localizedfields') {
                    $blockType[$key] = self::getItem($item);
                    foreach ($item->getChildren() as $keyLoca => $type) {
                        $blockType[$key]['children'][$type->getName()] = self::getItem($type);
                    }
                } else {
                    $blockType[$key] = self::getItem($item);
                }
            }
            $data = [];

            if (count($items) > 0) {
                foreach ($items as $key => $item) {
                    $data[] = BlockJson::getJson($item, $blockType, $lang);
                }
            }
            return $data;
        }

        if ($type instanceof Data\Link) {
            $data = [];
            $value = $dataObject->$getFunction();

            if ($value) {
                $data['internalType'] = $value->getInternalType();
                $data['internal'] = $value->getInternal();
                $data['direct'] = $value->getDirect();
                $data['linktype'] = $value->getLinktype();
                $data['path'] = $value->getPath();
                $data['text'] = $value->getText();
                $data['title'] = $value->getTitle();
                $data['target'] = $value->getTarget();
                $data['parameters'] = $value->getParameters();
                $data['anchorLink'] = $value->getAnchor();
                $data['attributes'] = $value->getAttributes();
                $data['accessKey'] = $value->getAccesskey();
                $data['rel'] = $value->getRel();
                $data['tabIndex'] = $value->getTabindex();
                $data['class'] = $value->getClass();
            }

            return $data;
        }

        if ($type instanceof Data\Wysiwyg) {
            $value = $dataObject->$getFunction($lang);

            return $value;
        }

        if ($type instanceof Data\RgbaColor) {
            $value = $dataObject->$getFunction();

            return $value ? $value->getHex() : null;
        }
        if ($type instanceof Data\urlSlug) {

            $value = $dataObject->$getFunction($lang);
            return $value ? $value[0]->getSlug() : null;
        }

        if (
            $type instanceof Data\Input
            || $type instanceof Data\Textarea
            || $type instanceof Data\Numeric
            || $type instanceof Data\Select
        ) {
            return $dataObject->$getFunction($lang);
        }

        if (
            $type instanceof Data\AdvancedManyToManyObjectRelation ||
            $type instanceof Data\AdvancedManyToManyRelation
        ) {
            return null;
        }

        if ($type instanceof Data\UrlSlug) {
            $value = $dataObject->$getFunction();

            if (isset($value[0])) {
                $data = $value[0]->getSlug();

                return $data;
            }

            return null;
        }

        if ($type instanceof Data\Multiselect) {
            $value = $dataObject->$getFunction();

            if (isset($value)) {

                return $value;
            }

            return null;
        }

        return $dataObject->$getFunction();
    }

    public static function getItem($items)
    {
        $data = [];

        $data['name'] = $items->getName();
        $data['type'] = $items->getFieldtype();
        $data['title'] = $items->title;

        if (method_exists($items, 'getInvisible')) {
            $data['invisible'] = $items->getInvisible();
        }

        if (method_exists($items, 'getNoteditable')) {
            $data['noteditable'] = $items->getNoteditable();
        }

        if (method_exists($items, 'getMandatory')) {
            $data['mandatory'] = $items->getMandatory();
        }

        if (method_exists($items, 'getIndex')) {
            $data['index'] = $items->getIndex();
        }

        if (method_exists($items, 'getLocked')) {
            $data['locked'] = $items->getLocked();
        }

        $options = FieldController::getOptions($items->getFieldtype(), $items);

        if (count($options)) {
            $data['options'] = $options;
        }

        return $data;
    }
}

<?php

namespace CorepulseBundle\Services;

use Pimcore\Model\DataObject;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject\ClassDefinition;
use Firebase\JWT\JWT;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Pimcore\Model\Asset\Service as AssetService;
use Pimcore\Model\DataObject\Service as DataObjectService;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\BlockElement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Pimcore\Model\DataObject\LocalizedField;
use Pimcore\Model\DataObject\Data\UrlSlug;
use Pimcore\Db;

class ObjectServices
{
    const normalTypeArr = [
        'input', 'lastname', 'wysiwyg', 'password', 'time', 'textarea', 'slider',
        'select', 'multiselect', 'numeric', 'email'
    ];


    static public function checkType($object, $value, $lang)
    {
        $fieldName = $value['name'];
        $fieldType = $value['type'];
        $fieldValue = $value['value'];

        // Sử dụng hàm riêng cho từng kiểu dữ liệu
        if ($fieldType != 'text') {
            if (in_array($fieldType, self::normalTypeArr)) {
                self::saveNormalType($object, $fieldType, $fieldName, $fieldValue, $lang);
            } else {
                $hidden = ['objectbricks', 'calculatedValue'];

                if (!in_array($fieldType, $hidden)) {
                    self::$fieldType($object, $fieldType, $fieldName, $fieldValue, $lang);
                }
            }
        }

        return $object;
    }

    static public function create($params, $object)
    {
        $objectName = $object->getClassId();

        // $object->setKey(\Pimcore\Model\Element\Service::getValidKey($params['objectKey'], 'object'));

        // DataObjectService::createFolderByPath("/" . $objectName);
        // $object->setParent(\Pimcore\Model\DataObject::getByPath("/" . $objectName));

        self::processArrayRecursively($params['children'], $object, $params['language']);
        if ($params['status'] == 'Publish') {
            $object->setPublished(true);
        }


        $object->save();
        return $object;
    }

    static public function processArrayRecursively($params, $object, $lang)
    {

        foreach ($params as $item) {

            if (isset($item['value'])) {
                self::checkType($object, $item, $lang);
            }

            if (isset($item['children']) && is_array($item['children'])) {
                self::processArrayRecursively($item['children'], $object, $lang);
            }
        }
    }

    static public function edit($params, $object)
    {
        self::processArrayRecursively($params['children'], $object, $params['language']);

        if ($params['status'] === 'Publish') {
            $object->setPublished(true);
        } elseif ($params['status'] === 'UnPublish') {
            $object->setPublished(false);
        }

        $object->save();

        if ($params['status'] == 'Draft') {
            $object->deleteAutoSaveVersions(0);
            $object->saveVersion();
        }

        return $object;
    }

    static public function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    static private function geopoint($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue['latitude'] || $fieldValue['longitude']) {

            $point = new \Pimcore\Model\DataObject\Data\GeoCoordinates($fieldValue['latitude'], $fieldValue['longitude']);

            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($point);
            }
        }
        return $object;
    }

    static public function saveNormalType($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($fieldValue, $lang);
            }
        }
        return $object;
    }

    static private function checkbox($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        $function = 'set' . ucfirst($fieldName);
        if ($fieldValue) {
            if (method_exists($object, $function)) {
                $object->$function($fieldValue);
            }
        } else {
            if (method_exists($object, $function)) {
                $object->$function(false);
            }
        }
        return $object;
    }

    static private function urlSlug($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        $queryBuilder = Db::get()->createQueryBuilder();

        $queryBuilder
            ->select('objectId')
            ->from('object_url_slugs')
            ->where('position = :position')
            ->where('slug LIKE :slug')
            ->setParameter('slug', '%' . $fieldValue . '%')
            ->setParameter('position', $lang);

        $result = $queryBuilder->execute()->fetchAll();

        if (strpos($fieldValue, '/') !== 0) {
            $fieldValue = '/' . $fieldValue;
        }

        if (!$result) {
            $function = 'set' . ucfirst($fieldName);
            if ($fieldValue) {
                $urlslug = new \Pimcore\Model\DataObject\Data\UrlSlug($fieldValue);
                $object->$function([$urlslug], $lang);
            }

            return $object;
        }
    }

    static private function rgbaColor($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        $function = 'set' . ucfirst($fieldName);

        if ($fieldValue) {
            $hex = str_replace('#', '', $fieldValue);

            // Tách giá trị của từng màu (red, green, blue) từ chuỗi hex
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = hexdec(substr($hex, 6, 2));

            $object->$function(new DataObject\Data\RgbaColor($r, $g, $b, $a));
        }

        return $object;
    }

    static private function link($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        $link = new DataObject\Data\Link();
        if (isset($fieldValue['path'])) $link->setPath($fieldValue['path']);
        if (isset($fieldValue['text'])) $link->setText($fieldValue['text']);
        if (isset($fieldValue['title'])) $link->setTitle($fieldValue['title']);
        if (isset($fieldValue['target'])) $link->setTarget($fieldValue['target']);
        if (isset($fieldValue['parameters'])) $link->setParameters($fieldValue['parameters']);
        if (isset($fieldValue['anchorLink'])) $link->setAnchor($fieldValue['anchorLink']);
        if (isset($fieldValue['accessKey'])) $link->setAccessKey($fieldValue['accessKey']);
        if (isset($fieldValue['rel'])) $link->setRel($fieldValue['rel']);
        if (isset($fieldValue['tabIndex'])) $link->setTabIndex($fieldValue['tabIndex']);
        if (isset($fieldValue['class'])) $link->setClass($fieldValue['class']);

        $function = 'set' . ucfirst($fieldName);
        if (method_exists($object, $function)) {
            $object->$function($link);
        }

        return $object;
    }

    static private function video($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $videoData = new DataObject\Data\Video();
            $videoData->setType($fieldValue['type']);
            if ($fieldValue['type'] == 'asset') {
                if (isset($fieldValue['title'])) $videoData->setTitle($fieldValue['title']);
                if (isset($fieldValue['description'])) $videoData->setDescription($fieldValue['description']);
                if (isset($fieldValue['data'])) $videoData->setData(Asset::getByPath($fieldValue['data']));
                if (isset($fieldValue['poster'])) $videoData->setPoster(Asset\Image::getByPath($fieldValue['poster']));
            } else {
                if (isset($fieldValue['data'])) $videoData->setData($fieldValue['data']);
            }

            // dd($fieldValue,  $videoData);

            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($videoData);
            }
        }
        return $object;
    }

    static public function saveImage($object, $image, $fieldName)
    {

        $path = "/Image/" . $object->getClassName() . "/" . $fieldName;

        $valueFolder = Asset::getByPath($path) ?? Asset\Service::createFolderByPath($path);

        $assetService = new \Pimcore\Model\Asset\Service();
        $imageCopy = $assetService->copyAsChild($valueFolder, $image);

        return $imageCopy;
    }

    static private function image($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            if (substr($fieldValue, 0, 4) == "http") {
                $prefix = \Pimcore::getContainer()->getParameter('pimcore.config')['assets']['frontend_prefixes']['source'];
                if ($prefix) {
                    $fieldValue = substr($fieldValue, strlen($prefix));
                }
            }

            $image = Asset\Image::getByPath($fieldValue);
            if ($image) {
                $function = 'set' . ucfirst($fieldName);

                if (method_exists($object, $function)) {
                    $object->$function($image);
                }
            }
        } else {
            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function(null);
            }
        }
        return $object;
    }

    static private function externalImage($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $image = new \Pimcore\Model\DataObject\Data\ExternalImage();
            $image->setUrl($fieldValue);

            if ($image) {
                $function = 'set' . ucfirst($fieldName);

                if (method_exists($object, $function)) {
                    $object->$function($image);
                }
            }
        }
        return $object;
    }

    static private function imageGallery($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            foreach ($fieldValue as $item) {
                $image = Asset\Image::getById($item['id']);
                if ($image) {
                    $advancedImage = new \Pimcore\Model\DataObject\Data\Hotspotimage();
                    $advancedImage->setImage($image);
                    $items[] = $advancedImage;

                    if ($items) {
                        $function = 'set' . ucfirst($fieldName);

                        if (method_exists($object, $function)) {
                            $object->$function(new \Pimcore\Model\DataObject\Data\ImageGallery($items));
                        }
                    }
                }
            }
        } else {
            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function(new \Pimcore\Model\DataObject\Data\ImageGallery([]));
            }
        }
        return $object;
    }

    static private function date($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $date = Carbon::createFromFormat('Y/m/d', $fieldValue);

            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($date);
            }
        }
        return $object;
    }

    static private function datetime($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $date = Carbon::parse($fieldValue);
            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($date);
            }
        }

        return $object;
    }

    static private function daterange($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            try {
                if (is_array($fieldValue)) {
                    $parts = $fieldValue;
                } else {
                    $parts = explode(" - ", $fieldValue);
                }

                $dateStart = Carbon::createFromFormat('Y/m/d', $parts[0]);
                $dateEnd = Carbon::createFromFormat('Y/m/d', $parts[1]);

                $date = CarbonPeriod::create($dateStart, $dateEnd);
                $function = 'set' . ucfirst($fieldName);

                if (method_exists($object, $function)) {
                    $object->$function($date);
                }
            } catch (\Throwable $th) {
                return $object;
            }
        }
        return $object;
    }

    static private function manyToManyObjectRelation($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $relations = [];
            foreach ($fieldValue as $value) {
                $relation = self::saveRelation($value);
                $relations[] = $relation;
            }

            $function = 'set' . ucfirst($fieldName);
            if (method_exists($object, $function)) {
                $object->$function($relations);
            }
        }
        return $object;
    }

    static private function manyToManyRelation($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {

            $relations = [];
            foreach ($fieldValue as $value) {
                $relation = self::saveRelation($value);
                $relations[] = $relation;
            }

            $function = 'set' . ucfirst($fieldName);
            if (method_exists($object, $function)) {
                $object->$function($relations);
            }
        }
        return $object;
    }

    static private function manyToOneRelation($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $relation = self::saveRelation($fieldValue);

            $function = 'set' . ucfirst($fieldName);
            if (method_exists($object, $function)) {
                $object->$function($relation);
            }
        }
        return $object;
    }

    static private function numericRange($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
    }

    static private function block($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        $getDataField = 'get' . ucfirst($fieldName);
        if ($fieldValue) {
            $blockCondition = [];
            foreach ($fieldValue as $keyFields => $item) {

                $localizedFieldsData = [];
                foreach ($item as $key => $value) {
                    if (isset($value['type']) && $value['type'] == 'localizedfields') {
                        $localizedFieldValue = [];
                        $localizedFieldValue[$lang] = [];
                        foreach ($value['children'] as $localizedField) {

                            if (isset($localizedField['value'])) {
                                if ($localizedField['type'] == 'manyToManyObjectRelation' || $localizedField['type'] == 'manyToManyRelation') {
                                    $relations = [];

                                    foreach ($localizedField['value'] as $itemValue) {
                                        $relation = self::saveRelation($itemValue);
                                        $relations[] = $relation;
                                    }

                                    $localizedFieldValue[$lang][$localizedField['name']] = $relations;
                                } elseif ($localizedField['type'] == 'manyToOneRelation') {
                                    if (count($localizedField['value']) == 3) {
                                        $relation = self::saveRelation($localizedField['value']);

                                        $localizedFieldValue[$lang][$localizedField['name']] = $relation;
                                    }
                                } else {
                                    $localizedFieldValue[$lang][$localizedField['name']] = $localizedField['value'];
                                }
                            }
                        }

                        if ($object->$getDataField()) {
                            if ($object->$getDataField()[$keyFields]['localizedfields']->getData()) {
                                $dataOld = $object->$getDataField()[$keyFields]['localizedfields']->getData()->getItems();
                                unset($dataOld[$lang]);
                                $localizedFieldValue = array_merge($localizedFieldValue, $dataOld);
                            }
                        }


                        $localizedFieldsData["localizedfields"] = new BlockElement('localizedfields', 'localizedfields', new Localizedfield($localizedFieldValue));
                    } elseif (isset($value['type']) && $value['type'] == 'imageGallery') {
                        $imageGallery = [];
                        if (isset($value['value']) && $value['value']) {
                            foreach ($value['value'] as $path) {
                                $image = Asset\Image::getById($path['id']);
                                if ($image) {
                                    $advancedImage = new \Pimcore\Model\DataObject\Data\Hotspotimage();
                                    $advancedImage->setImage($image);
                                    $imageGallery[] = $advancedImage;
                                }
                            }
                        }
                        $localizedFieldsData[$value['name']] = new BlockElement($value['name'], $value['type'], new \Pimcore\Model\DataObject\Data\ImageGallery($imageGallery));
                    } elseif (isset($value['type']) && $value['type'] == 'image') {
                        if (isset($value['value']) && $value['value'] !== "") {
                            $image = Asset::getByPath($value['value']);

                            $localizedFieldsData[$value['name']] = new BlockElement($value['name'], $value['type'], $image);
                        }
                    } elseif (isset($value['type']) && ($value['type'] == 'manyToManyObjectRelation' || $value['type'] == 'manyToManyRelation')) {
                        $relations = [];

                        foreach ($value['value'] as $itemValue) {
                            $relation = self::saveRelation($itemValue);
                            $relations[] = $relation;
                        }

                        $localizedFieldsData[$value['name']] = new BlockElement($value['name'], $value['type'], $relations);
                    } elseif (isset($value['type']) && $value['type'] == 'manyToOneRelation') {
                        if (count($value['value']) == 3) {
                            $relation = self::saveRelation($value['value']);

                            $localizedFieldsData[$value['name']] = new BlockElement($value['name'], $value['type'], $relation);
                        }
                    } else {
                        try {
                            $localizedFieldsData[$value['name']] = new BlockElement($value['name'], $value['type'], $value['value']);
                        } catch (\Throwable $th) {
                        }
                    }
                }
                array_push($blockCondition, $localizedFieldsData);
            }

            $function = 'set' . ucfirst($fieldName);

            if (method_exists($object, $function)) {
                $object->$function($blockCondition);
            }
        }
        return $object;
    }

    static private function fieldcollections($object, $fieldType, $fieldName, $fieldValue, $lang)
    {
        if ($fieldValue) {
            $items = new DataObject\Fieldcollection();

            foreach ($fieldValue as  $type) {
                foreach ($type as $key => $item) {

                    $textFieldCollection = "Pimcore\\Model\\DataObject\\Fieldcollection\\Data\\" . ucfirst($key);
                    $fieldCollection = new $textFieldCollection();

                    self::processArrayRecursively($item['children'], $fieldCollection, $lang);

                    $items->add($fieldCollection);
                }
            }

            $function = 'set' . ucfirst($fieldName);
            if (method_exists($object, $function)) {
                $object->$function($items);
            }
        }
        return $object;
    }

    //xuất excel
    static public function getExcel($data)
    {
        ob_start();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Duyệt qua dữ liệu và ghi vào các ô tương ứng
        foreach ($data as $rowIndex => $row) {
            $i = 0;

            foreach ($row as $colIndex => $value) {
                $i++;
                $sheet->setCellValueByColumnAndRow($i, (int)$rowIndex + 1, $value);
            }
        }

        return $spreadsheet;
    }

    static public function saveRelation($value)
    {
        if (!is_array($value)) {
            $value = explode(",", $value);
        }

        if (count($value) == 3) {
            $model = $value[0];
            $type = $value[1];
            $id = $value[2];

            $objectName = "Pimcore\\Model\\" . $model;
            $relation = $objectName::getById($id);

            return $relation;
        }

        return null;
    }
}

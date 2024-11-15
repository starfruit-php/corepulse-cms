<?php

namespace CorepulseBundle\Services;

use Google\Service\AIPlatformNotebooks\Status;
use Pimcore\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use DateTime;
use Pimcore\Model\Document;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document\Editable\Input;
use Pimcore\Model\Document\Editable\Wysiwyg;
use Pimcore\Model\Document\Editable\Image;
use Pimcore\Model\Document\Editable\Relation;
use Pimcore\Model\Document\Editable\Relations;
use Pimcore\Model\Document\Editable\Select;
use Pimcore\Model\Document\Editable\Multiselect;
use Pimcore\Model\Document\Editable\Date;
use Pimcore\Model\Document\Editable\Textarea;
use Pimcore\Model\Document\Editable\Numeric;
use Pimcore\Model\Document\Editable\Checkbox;

use function PHPSTORM_META\type;

class FieldServices
{
    public static function getCheckbox ($document, $value) 
    {
        $data = $value ? $value->getValue() : '';
        return $data;
    }
    public static function setCheckbox ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {           
                    $getData = new Checkbox;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                    $getData->setDataFromResource($decode->value);

                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Checkbox'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getInput($document, $value) 
    {
        $data = $value ? $value->getText() : '';
        return $data;
    }
    public static function setInput ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {           
                    $getData = new Input;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                $getData->setDataFromResource($decode->value);

                $array = $document->getEditables();
                array_push($array, $getData);
                $document->setEditables($array);

                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Input'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getWysiwyg ($document, $value) 
    {
        $data = $value ? $value->getText() : '';
        return $data;
    }
    public static function setWysiwyg ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Wysiwyg;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                    $getData->setDataFromResource($decode->value);

                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Wysiwyg'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getSelect ($document, $value) 
    {
        $data = $value ? $value->getText() : '';
        return $data;
    }
    public static function setSelect ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Select;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                $getData->setDataFromResource($decode->value);

                $array = $document->getEditables();
                array_push($array, $getData);
                $document->setEditables($array);

                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Select'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getTextarea ($document, $value) 
    {
        $data = $value ? $value->getText() : '';
        return $data;
    }
    public static function setTextarea ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Textarea;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                    $getData->setDataFromResource(html_entity_decode($decode->value));

                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];

            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Textarea'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getNumeric ($document, $value) 
    {   
        try {
            $data = $value ? $value->getNumber() : '';
        } catch(\Throwable $th) {
            $data = '';
        }
        return $data;
    }
    public static function setNumeric ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Numeric;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }

                    $getData->setDataFromResource($decode->value);

                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Numeric'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getMultiselect ($document, $value) 
    {
        $data = $value ? $value->getValues() : '';
        return $data;
    }
    public static function setMultiselect ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Multiselect;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }
                    $getData->setDataFromEditmode($decode->value);

                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Multiselect'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getDate ($document, $value) 
    {
        $data = $value ? $value->getDate()?->format('Y-m-d') : '';
        return $data;
    }
    public static function setDate ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if (!$getData) {
                    $getData = new Date;
                    $getData->setDocument($document);
                    $getData->setName($decode->name);
                }

                    $getData->setDataFromEditmode($decode->value);
                    
                    $array = $document->getEditables();
                    array_push($array, $getData);
                    $document->setEditables($array);

                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Date'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getLink ($document, $value) 
    {
        $data = $value ? $value->getData() : '';
        return $data;
    }
    public static function setLink ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($getData) {
                $valueArray = json_decode($value, true);
        
                $dataInternal = [
                    "internalType" => null,
                    "linktype" => "direct",
                    "internal" => false,
                    "internalId" => null,
                ];

                if (\Pimcore\Model\Document::getByPath($valueArray['value']['path'])) {
                    $id = \Pimcore\Model\Document::getByPath($valueArray['value']['path'])->getId();
                    $dataInternal = [
                        "internalType" => "document",
                        "linktype" => "internal",
                        "internal" => true,
                        "internalId" => $id,
                    ];
                } elseif (\Pimcore\Model\Asset::getByPath($valueArray['value']['path'])) {
                    $id = \Pimcore\Model\Asset::getByPath($valueArray['value']['path'])->getId();
                    $dataInternal = [
                        "internalType" => "asset",
                        "linktype" => "internal",
                        "internal" => true,
                        "internalId" => $id,
                    ];
                } elseif (\Pimcore\Model\DataObject::getByPath($valueArray['value']['path'])) {
                    $id = \Pimcore\Model\DataObject::getByPath($valueArray['value']['path'])->getId();
                    $dataInternal = [
                        "internalType" => "object",
                        "linktype" => "internal",
                        "internal" => true,
                        "internalId" => $id,
                    ];
                }

                $mergedArray = array_merge($dataInternal, $valueArray['value']);

                $getData->setDataFromEditmode($mergedArray);
                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Link'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getTable ($document, $value) 
    {
        $data = $value ? $value->getData() : '';
        return $data;
    }
    public static function setTable ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                $getData = $document->getEditable($decode->name);
                if ($getData) {
                    $jsonValueRelation = json_decode($decode->value);
                    $getData->setDataFromEditmode($jsonValueRelation);
                    $document->save();
                    return ['status' => 200, 'messsage' => 'Success'];
                }
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Table'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getImage ($document, $value) 
    {
        $data = [
            'id' => '',
            'linkImage' => '',
            'thumbPath' => '/bundles/corepulse/image/image-default.png',
        ];
        if ($value->getData()['id'] != null) {
            $image = Asset::getById($value->getData()['id']);
            $thumbPath = AssetServices::getThumbnailPath($image);

            if ($image) {
                $data = [
                    'id' => $value->getData()['id'],
                    'thumbPath' => $thumbPath,
                    'linkImage' => $image->getPath() . $image->getFilename(),
                ];
            }
        }
        return $data;
    }
    public static function setImage ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($getData) {
                $jsonValueRelation = $decode->value;
                if (property_exists($jsonValueRelation, 'src')) {
                    $path = $jsonValueRelation->src;
                    
                    if (!empty($path)) {
                        if (substr($path, 0, 4) == "http") {
                            $prefix = \Pimcore::getContainer()->getParameter('pimcore.config')['assets']['frontend_prefixes']['source'];
                            if ($prefix) {
                                $path = substr($path, strlen($prefix)); 
                            }
                        }
                        
                        $asset = Asset::getByPath($path);
                        if ($asset) {
                            $getData->setDataFromEditmode([
                                'id' => (int) $asset->getId(),
                                "alt" => "",
                                "cropPercent" => false,
                                "cropWidth" => 0.0,
                                "cropHeight" => 0.0,
                                "cropTop" => 0.0,
                                "cropLeft" => 0.0,
                                "hotspots" => [],
                                "marker" => [],
                                "thumbnail" => null
                            ]);
                        }
                    } 
                }
                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Image'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getPdf ($document, $value) 
    {
        $data = [
            'id' => '',
            'linkPDF' => '',
        ];
        if ($value->getData()['id'] != null) {
            $image = Asset::getById($value->getData()['id']);

            $data = [
                'id' => $value->getData()['id'],
                'linkPDF' => $image->getFilename(),
            ];
        }
        return $data;
    }
    public static function setPdf ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            // dd($getData);
            if ($getData) {
                $jsonValueRelation = $decode->value;
                if (property_exists($jsonValueRelation, 'src')) {
                    $path = $jsonValueRelation->src;
                    $asset = Asset::getByPath($path);
                    if ($getData && $asset) {
                        $getData->setDataFromEditmode([
                            'id' => (int) $asset->getId(),
                        ]);
                    }
                }
                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Pdf'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getVideo($document, $value) 
    {
        $data = $value ? $value->getData() : '';
        return $data;
    }
    public static function setVideo($document, $decode, $value) 
    {
        if ($document) {
            if ($decode->name) {
                $getData = $document->getEditable($decode->name);
                if ($getData) {
                    $jsonValueRelation = $decode->value;
                    $asset = Asset::getByPath($jsonValueRelation->path);
                    $asset ? $assetId = $asset->getId() : $assetId = '';
                    $allowedTypes = ['asset', 'youtube', 'vimeo', 'dailymotion'];
                    $dataVideo = [
                        'id' => $assetId,
                        'type' => $jsonValueRelation->type,
                        'allowedTypes' => $allowedTypes,
                        'title' => $jsonValueRelation->title,
                        'description' => $jsonValueRelation->description,
                        'path' => $jsonValueRelation->path,
                        'poster' => $jsonValueRelation->poster,
                    ];
                    $getData->setDataFromEditmode($dataVideo);
                }
                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Video'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getRenderlet ($document, $value) 
    {
        $data = [
            'id' => '',
            'type' => '',
            'subtype' => '',
            'link' => '',
        ];
        if ($value->getData()["type"] == 'asset') {
            $data = [
                'id' => $value->getData()['id'],
                'type' => $value->getData()['type'],
                'subtype' => $value->getData()['subtype'],
                'link' => $value->getO()?->getPath() . $value->getO()?->getFileName(),
            ];
        }
        if ($value->getData()["type"] == 'object') {
            $data = [
                'id' => $value->getData()['id'],
                'type' => $value->getData()['type'],
                'subtype' => $value->getData()['subtype'],
                // 'dataObject'
            ];
        }
        return $data;
    }
    public static function setRenderlet ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($getData) {
                $jsonValueRelation = $decode->value;
                if (property_exists($jsonValueRelation, 'id')) {
                    $getData->setDataFromEditmode([
                        'id' => property_exists($jsonValueRelation, 'id') ? (int) $jsonValueRelation->id : 0,
                        'type' => $jsonValueRelation->type,
                        'subtype' => property_exists($jsonValueRelation, 'subtype') ? $jsonValueRelation->subtype : '',
                    ]);
                }
                $document->save();

                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Renderlet'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getScheduledblock ($document, $value) 
    {
        $data = [];
        return $data;
    }
    public static function setScheduledblock ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($decode->value && $getData) {
                $notType = ['snippet', "renderlet", "block", "scheduledblock"];
                $i = 0;
                $dataSchedule = [];

                foreach ($decode->value as $key => $value) {
                    ++$i;
                    $keySche = (string)($i - 1);
                    $dataSchedule[] = [
                        'key' => $keySche,
                        'date' => (string)strtotime($key),
                    ];

                    foreach ($value as $keyBlockT=> $v) {
                        if (!in_array($v->type, $notType)) {
                            $blockSave = $document->getEditable($keyBlockT);
                            if ($blockSave) {
                                if ($v->type == 'image') {
                                    $asset = Asset::getByPath($v->value);
                                    if ($asset) {
                                        $idImage = $blockSave?->setId($asset->getId());
                                    }
                                } else {
                                    $blockSave?->setDataFromResource($v->value);
                                }
                            } else {
                                $function = ucwords($v->type);
                                $newBlock = new $function($keyBlockT);
                                $newBlock->setDocument($document);
                                $newBlock->setName($keyBlockT);
                                $newBlock->setDataFromResource($v->value);
                                $newBlock->save();

                                array_merge($document->getEditables(), $newBlock);
                            }
                        }
                    }
                }
                $getData->setDataFromEditmode($dataSchedule);
                $document->save();
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Scheduledblock'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getSnippet ($document, $value) 
    {
        $data = [];
    
        if ($value->getSnippet() != null) {
            $snippeted = Document::getById((int)$value->getSnippet()->getId());
            $fullPath = DocumentServices::getThumbnailPath($snippeted);

            $data = [
                'id' => $value->getSnippet()->getId(),
                'name' => $value->getSnippet()->getKey(),
                'subtype' => $value->getSnippet()->getType(),
                'type' => 'documment',
                'fullPath' => $fullPath,
            ];
        }
        return $data;
    }
    public static function setSnippet ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode->name){
                $getData = $document->getEditable($decode->name);
                if ($getData) {
                    $snippet = null;
                    if ($decode->value && isset($decode->value[2])) {
                        $snippet = Document::getById((int)$decode->value[2]);
                        if ($snippet) {
                            $getData->setSnippet($snippet);
                        }
                    } else {
                        $getData->setDataFromEditmode($snippet);
                    }
                    $document->save();
    
                    return ['status' => 200, 'messsage' => 'Success'];
                }
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Snippet'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getBlock ($document, $value) 
    {
        $data = [];
        $blocksItem = [];
        if ($value->getElements() != null) {
            $arrTypeChillBlock = DocumentServices::getEditableBlock($document, $value->getName());

            $notType = ['snippet', "renderlet", "block", "video", "pdf", "relation"];
            foreach ($value->getData() as $item) {
                $data = DocumentServices::getDataBlock($document, $arrTypeChillBlock);
                // foreach ($arrTypeChillBlock as $keyBlockT => $type) {
                //     $titleBlock = '';
                //     if (!in_array($type, $notType)) {
                //         $titleBlock = $document->getEditable($keyBlockT)?->getText();
                //     }

                //     $contentField = $titleBlock;
                //     if ($type == 'image' || $type == 'video' || $type == 'pdf') {
                //         $idImage = $document->getEditable($keyBlockT)?->getId();
                //         $asset = Asset::getById((int)$idImage);
                //         if ($asset) {
                //             $contentField = $asset->getFullPath();
                //         }
                //     }

                //     $data[$keyBlockT] = [
                //         'type' => $type,
                //         'value' => $contentField
                //     ];
                // }
            }
            $adc = [];
            $arrName = explode(':', $value->getName());
            $nameCheck = is_array($arrName) ? $arrName[0] : $arrName;
            foreach ($value->getData() as $item) {
                $keyCheck = $nameCheck . ":" . $item . ".";
                $adc[] = $keyCheck;
            }
            
            foreach ($adc as $val) {
                foreach ($data as $key => $item) {
                    if (strpos($key, $val) !== false) {
                        $blocksItem[$val][$key] = $item;
                    }
                }
            }
        }
        return $blocksItem;
    }
    public static function setBlock ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($getData) {
                if ($decode->value) {
                    $notType = ['snippet', "renderlet", "block"];
                    $notSave = ['snippet', "renderlet", "block", "video", "pdf", "relation", "relations", "image"];
    
                    foreach ($decode->value as $key => $value) {
                        $i = 0;
                        $dataBlocks = [];

                        $getData = $document->getEditable($key);
                        // dd($value);
                        foreach ($value as $l => $val) {
                            $i++;
                            $dataBlocks[] = $i;
                            foreach ($val as $keyBlockT=> $v) {
                                if (!in_array($v->type, $notType)) {
                                    $blockSave = $document->getEditable($keyBlockT);

                                    if ($blockSave) {
                                        $blockSave = DocumentServices::setDataBlock($v, $blockSave, $notSave);
                                    } else {
                                        $oldArr = $document->getEditables();
                                        $function = "Pimcore\\Model\\Document\\Editable\\" . ucwords($v->type);
                                       
                                        $newBlock = new $function($keyBlockT);
                                        $newBlock->setDocument($document);
                                        $newBlock->setName($keyBlockT);

                                        $newBlock = DocumentServices::setDataBlock($v, $newBlock, $notSave);

                                        $newBlock->save();
                                        // $newArr[] =  $newBlock;
                                        $array = $document->getEditables();
                                        array_push($array, $getData);
                                        $document->setEditables($array);
                                        // array_push($document->getEditables(), $newBlock);
                                    }
                                }
                            }
                        }
                        if ($dataBlocks) {
                            $getData->setDataFromEditmode($dataBlocks);
                        }
                        $document->save();
                    } 
                }
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Block'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getRelation ($document, $value) 
    {
        $data = [
            'id' => '',
            'name' => '',
            'subtype' => '',
            'type' => ''
        ];
        if ($value->getElement() != null) {
            if ($value->getElement()->getType() == 'object') {
                $data = [
                    'id' => $value->getElement()->getId(),
                    'name' => $value->getElement()->getKey(),
                    'subtype' => $value->getElement()->getType(),
                    'type' => 'object'
                ];
            } elseif (
                $value->getElement()->getType() == 'image' ||
                $value->getElement()->getType() == 'video' ||
                $value->getElement()->getType() == 'document' ||
                $value->getElement()->getType() == 'docx' ||
                $value->getElement()->getType() == 'xlsx' ||
                $value->getElement()->getType() == 'text'
            ) {
                $data = [
                    'id' => $value->getElement()->getId(),
                    'name' => $value->getElement()->getFilename(),
                    'subtype' => $value->getElement()->getType(),
                    'type' => 'asset'
                ];
            } else {
                $data = [
                    'id' => $value->getElement()->getId(),
                    'name' => $value->getElement()->getKey(),
                    'subtype' => $value->getElement()->getType(),
                    'type' => 'document'
                ];
            }
        }
        return $data;
    }
    public static function setRelation ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode->value) {
                $getData = $document->getEditable($decode->name);
                if ($getData) {
                    if (property_exists($decode->value, 'id')) {
                        $getData->setDataFromEditmode([
                            'id' => (int) $decode->value->id,
                            'type' => strtolower($decode->value->type),
                            'subtype' => $decode->value->subtype,
                        ]);
                    }
                }
                $document->save();

                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Relation'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getRelations ($document, $value) 
    {
        $data = [];
    
        if ($value->getElements() != null) {
            foreach ($value->getElements() as $item) {
                if ($item->getType() == 'object') {
                    $data[] = [
                        'id' => $item->getId(),
                        'name' => $item->getKey(),
                        'type' => 'object',
                        'subType' => $item->getType(),
                    ];
                } elseif (
                    $item->getType() == "image" ||
                    $item->getType() == "video" ||
                    $item->getType() == "document" ||
                    $item->getType() == "docx" ||
                    $item->getType() == "xlsx" ||
                    $item->getType() == "text"
                ) {
                    $data[] = [
                        'id' => $item->getId(),
                        'name' => $item->getFilename(),
                        'type' => 'asset',
                        'subType' => $item->getType(),
                    ];
                } else {
                    $data[] = [
                        'id' => $item->getId(),
                        'name' => $item->getKey(),
                        'type' => 'document',
                        'subType' => $item->getType(),
                    ];
                }
            }
        }
        return $data;
    }
    public static function setRelations ($document, $decode, $value) 
    {
        if ($document) {
            if ($decode) {
                if ($decode->value) {
                    foreach ($decode->value as $key => $val) {
                        $relations = [];
                        $getData = $document->getEditable($key);
                        if ($getData) {
                            foreach ($val as $item) {
                                $relations[] = [
                                    'id' => $item[2],
                                    'type' => strtolower($item[0]) == "dataobject" ? 'object' : strtolower($item[0]),
                                    'subtype' => $item[1],
                                ];
                            }
                            $getData->setDataFromEditmode($relations);
                            $getData->setElements($relations);
                            $document->save();
                        }
                    }
                }
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Relations'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function getAreablock ($document, $value) 
    {
        $data = [];
        return $data;
    }
    public static function setAreablock ($document, $decode, $value) 
    {
        if ($document) {
            $getData = $document->getEditable($decode->name);
            if ($decode->value && $getData) {
                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Areablock'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    public static function setHref ($document, $path) 
    {
        if ($document) {
            if ($path) {
                $target = null;
                if ($target = Document::getByPath($path)) {
                    $data['linktype'] = 'internal';
                    $data['internalType'] = 'document';
                    $data['internal'] = $target->getId();
                } elseif ($target = Asset::getByPath($path)) {
                    $data['linktype'] = 'internal';
                    $data['internalType'] = 'asset';
                    $data['internal'] = $target->getId();
                } elseif ($target = Concrete::getByPath($path)) {
                    $data['linktype'] = 'internal';
                    $data['internalType'] = 'object';
                    $data['internal'] = $target->getId();
                } else {
                    $data['linktype'] = 'direct';
                    $data['internalType'] = null;
                    $data['direct'] = $path;
                }
    
                $document->setValues($data);
                $document->save();

                return ['status' => 200, 'messsage' => 'Success'];
            } else {
                return ['status' => 500, 'messsage' => 'Error occurs when saved field Path'];
            }
        } else {
            return ['status' => 500, 'messsage' => 'Error'];
        }
    }

    // getType search
    const listField = [
        "input", "textarea", "wysiwyg", "password",
        "number", "numericRange", "slider", "numeric",
        "date", "datetime", "dateRange", "time", "manyToOneRelation",
        "select", 'multiselect', 'image', 'manyToManyRelation',
        'manyToManyObjectRelation', 'imageGallery', 'urlSlug'
    ];

    const chipField = [
        "select", "multiselect", "manyToOneRelation", "manyToManyObjectRelation",
        "manyToManyRelation", "advancedManyToManyRelation", "advancedmanyToManyObjectRelation"
    ];

    const multiField = [
        "multiselect", 'manyToManyRelation', 'manyToManyObjectRelation'
    ];

    const relationField = [
        "manyToOneRelation"
    ];

    const relationsField = ["manyToManyObjectRelation", "manyToManyRelation", "advancedManyToManyRelation", "advancedmanyToManyObjectRelation"];

    const noSearch = ["image", "imageGallery", "urlSlug"];

    public static function getType($fieldType) 
    {
        $searchType = 'Input';
        if (in_array($fieldType, self::listField)) {
            $searchType = 'Input';

            if (in_array($fieldType, self::chipField)) {
                $searchType = 'Select';

                $searchType = 'Select';

                if (in_array($fieldType, self::multiField)) {
                    $searchType = 'Select';
                }

                if (in_array($fieldType, self::relationField)) {
                    $searchType = 'Relation';
                }

                if (in_array($fieldType, self::relationsField)) {
                    $searchType = 'Relation';
                }
            } elseif ($fieldType == 'dateRange') {
                $searchType = 'DateRange';
            } elseif ($fieldType == 'date') {
                $searchType = 'DatePicker';
            }
        }

        if (in_array($fieldType, self::noSearch)) {
            $searchType = 'nosearch';
        }

        return $searchType;
    }

}
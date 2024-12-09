<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\Data\Link as DataLink;
use Pimcore\Model\Document\Editable\Link as DocumentLink;

class Link extends Input
{
    public function format($value)
    {
        $data = [];
        $params = [
            'property' => [
                'target', 'parameters', 'title', 'anchor'
            ],
            'advanced' => [
                'accessKey', 'rel', 'tabIndex', 'class'
            ]
        ];

        foreach ($params as $paramKey => $paramValue) {
            foreach ($paramValue as $param) {
                $getFunc = 'get' . ucfirst($param);
                $data[$paramKey][$param] = $value?->$getFunc();
            }
        }

        $data['path'] = $value?->getPath();
        $data['text'] = $value?->getText();
        // $data['linktype'] = $value?->getLinktype();
        // if ($data['linktype'] == 'internal') {
        //     $data['data']['internal'] = $value?->getInternal();
        //     $data['data']['internalType'] = $value?->getInternalType();
        // } elseif ($data['linktype'] == 'direct') {
        //     $data['data']['direct'] = $value?->getDirect();
        // }

        return $data;
    }

    public function formatDocument($value)
    {
        $valueData = $value->getData();
        $data = [];
        $params = [
            'property' => [
                'target', 'parameters', 'title', 'anchor'
            ],
            'advanced' => [
                'accessKey', 'rel', 'tabIndex', 'class'
            ]
        ];

        foreach ($params as $paramKey => $paramValue) {
            foreach ($paramValue as $param) {
                $data[$paramKey][$param] = is_array($valueData) && isset($valueData[$param]) ? $valueData[$param] : null;
            }
        }

        $data['path'] = is_array($valueData) && isset($valueData['path']) ? $valueData['path'] : null;
        $data['text'] = is_array($valueData) && isset($valueData['text']) ? $valueData['text'] : null;

        return $data;
    }

    public function formatDataSave($value)
    {
        $link = new DataLink();

        $convertValue = $this->convertValue($value);
        $fields = ['path', 'text', 'target', 'parameters', 'title', 'anchor', 'accessKey', 'rel', 'tabIndex', 'class'];
        foreach ($fields as $key) {
            if (isset($convertValue[$key])) {
                $func = 'set' . ucfirst( $key);
                $link->{$func}($convertValue[$key]);
            }
        }

        return $link;
    }

    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) { 
            $editable = new DocumentLink();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }

        $dataInternal = [
            "internalType" => null,
            "linktype" => "direct",
            "internal" => false,
            "internalId" => null,
        ];
        $convertValue = $this->convertValue($value);
                
        $mergedArray = array_merge($dataInternal, $convertValue);

        $editable->setDataFromEditmode($mergedArray);

        return $editable;
    }

    public function convertValue($value)
    {
        $convertValue = $value;

        $keysToMerge = ['property', 'advanced'];

        foreach ($keysToMerge as $key) {
            if (isset($value[$key])) {
                $convertValue = array_merge($convertValue, $value[$key]);
                unset($convertValue[$key]);
            }
        }
    
        return $convertValue;
    }
}

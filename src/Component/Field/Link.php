<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\DataObject\Data\Link as DataLink;

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

    public function formatDataSave($value)
    {
        $link = new DataLink();

        $fields = ['path', 'text', 'title', 'target', 'parameters', 'anchorLink', 'accessKey', 'rel', 'tabIndex', 'class', 'attributes'];
        foreach ($fields as $key) {
            if (isset($value[$key])) {
                $func = 'set' . ucfirst( $key);
                $link->{$func}($value[$key]);
            }
        }

        return $link;
    }
}

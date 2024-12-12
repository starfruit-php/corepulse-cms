<?php

namespace CorepulseBundle\Component\Field;

use Pimcore\Model\Asset;
use CorepulseBundle\Component\Field\Image;
use Pimcore\Model\DataObject\Data\Video as DataVideo;
use Pimcore\Model\Document\Editable\Video as DocumentVideo;

class Video extends Image
{
    public function format($value)
    {
        $data = [
            'type' => $value?->getType() ?? null,
            'path' => $value?->getData() instanceof Asset ? $value->getData()?->getFrontendPath() : $value?->getData(),
            'title' => $value?->getTitle() ?? null,
            'description' => $value?->getDescription() ?? null,
            'poster' => $value?->getPoster() ? $value->getPoster()?->getFrontendPath() : null,
        ];

        if ($value?->getData() instanceof Asset) {
            $data['dataId'] = $value->getData()?->getId();
            $data['posterId'] = $value?->getPoster() ? $value->getPoster()?->getId() : null;
        }

        return $data;
    }

    public function formatDocument($value) 
    {
        $data = $value?->getData();

        $data['posterId'] = $value?->getPoster();
        $data['dataId'] = $data['id'];

        if ($data['type'] == 'asset') {
            if (is_numeric($data['id'])) {
                $asset = Asset::getById($data['id']);
                if ($asset) {
                    $data['path'] = $asset->getFrontendPath();
                }

            }
            if (is_numeric($data['posterId'])) {
                $poster = Asset::getById($data['posterId']);
                if ($poster) {
                    $data['poster'] = $poster->getFrontendPath();
                }
            }
        }

        return $data;
    }

    public function getFrontEndType()
    {
        return 'video';
    }

    public function formatDataSave($value)
    {
        $video = new DataVideo();

        if (is_array($value) && isset($value['type'])) {
            $video->setType($value['type']);
            if ($value['type'] == 'asset') {
                $container = \Pimcore::getContainer();
                $config = $container->getParameter('pimcore.config');

                $prefix = '';
                if(isset($config['assets']) && isset($config['assets']['frontend_prefixes']) && isset($config['assets']['frontend_prefixes']['source']))
                $prefix = $config['assets']['frontend_prefixes']['source'];

                if (isset($value['title'])) $video->setTitle($value['title']);
                if (isset($value['description'])) $video->setDescription($value['description']);

                if (isset($value['dataId'])) $video->setData(Asset::getById($value['dataId']));
                if (isset($value['path']) && !$video->getData()) {
                    $dataPrefix =  str_replace($prefix, '', $value['data']);
                    $video->setData(Asset::getByPath($dataPrefix));
                }

                if (isset($value['posterId'])) $video->setPoster( Asset\Image::getById($value['posterId']));
                if (isset($value['poster']) && !$video->getPoster()) {
                    $posterPrefix =  str_replace($prefix, '', $value['poster']);
                    $video->setPoster( Asset\Image::getByPath($posterPrefix));
                }
            } else {
                if (isset($value['path'])) $video->setData($value['path']);
            }
        }

        return $video;
    }

    public function formatDocumentSave($value)
    {
        $editable = $this->getObjectOrDocument()->getEditable($this->getLayout()->name);
        
        if (!$editable) {
            $editable = new DocumentVideo();
            $editable->setDocument($this->getObjectOrDocument());
            $editable->setName($this->getLayout()->name);
            $editable->setRealName($this->getLayout()->realName);
        }
        // $allowedTypes = ['asset', 'youtube', 'vimeo', 'dailymotion'];
        $dataVideo = [
            'id' => $value['dataId'],
            'type' => $value['type'],
            'allowedTypes' => $value['allowedTypes'],
            'title' => $value['title'],
            'description' => $value['description'],
            'path' => $value['path'],
            'poster' => $value['poster'],
        ];
        $editable->setDataFromEditmode($dataVideo);
       
        return $editable;
    }
}

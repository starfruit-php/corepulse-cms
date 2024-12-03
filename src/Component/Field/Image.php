<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;
use Pimcore\Model\Asset;
use Pimcore\Model\Document\Editable\Image as DocumentImage;

class Image extends AbstractField
{
    public function formatDocument($value) 
    {
        return $this->format($value);
    }

    public function format($value)
    {
        $data = [
            [
                'path' => $value?->getFrontendPath(),
                'id' => $value?->getId()
            ]
        ];

        return $data;
    }

    public function formatBlock($value)
    {
        return  $this->format($value);
    }

    public function formatDataSave($value)
    {
        $image = null;
        if ($value && $format = reset($value)) {
            $image = Asset::getById((int)$format['id']);
        }

        return $image;
    }

    public function formatDocumentSave($value)
    {
        $editable = new DocumentImage();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);

        $id = 0;
        if ($value && isset($value['id'])) {
            $id = (int)$value['id'];
        } elseif ($value && $format = reset($value)) {
            $id = (int)$format['id'];
        }

        if ($id) {
            $image = Asset::getById($id);
            if ($image) {
                $editable->setDataFromEditmode([
                    'id' => (int) $image->getId(),
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
        

        return $editable;
    }

    public function getFrontEndType()
    {
        return 'image';
    }
}

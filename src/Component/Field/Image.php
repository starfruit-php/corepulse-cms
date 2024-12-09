<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;
use Pimcore\Model\Asset;
use Pimcore\Model\Document\Editable\Image as DocumentImage;

class Image extends AbstractField
{
    public function formatDocument($value) 
    {
        $data = [
            [
                'type' => $value?->getImage()?->getType(),
                'mimetype' => $value?->getImage()?->getMimetype(),
                'filename' => $value?->getImage()?->getFileName(),
                'parentId' => $value?->getImage()?->getParentId(),
                'checked' => true,
                'path' => $value?->getImage()?->getFullPath(),
                'fullPath' => $value?->getImage()?->getFrontendPath(),
                'id' => $value?->getId(),
            ]
        ];

        return $data;
    }

    public function format($value)
    {
        $data = [
            [
                'type' => $value?->getType(),
                'mimetype' => $value?->getMimetype(),
                'filename' => $value?->getFileName(),
                'parentId' => $value?->getParentId(),
                'checked' => true,
                'path' => $value?->getFullPath(),
                'fullPath' => $value?->getFrontendPath(),
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
        if (is_array($value) && $format = reset($value)) {
            $image = Asset::getById((int)$format['id']);
        }

        return $image;
    }

    public function formatDocumentSave($value)
    {
        $editable = new DocumentImage();
        $editable->setDocument($this->getObjectOrDocument());
        $editable->setName($this->getLayout()->name);
        $editable->setRealName($this->getLayout()->realName);

        $id = 0;
        if (is_array($value) && $format = reset($value)) {
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

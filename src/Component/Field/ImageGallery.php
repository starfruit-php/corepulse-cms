<?php

namespace CorepulseBundle\Component\Field;

use Starfruit\BuilderBundle\Tool\AssetTool;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\ImageGallery as Images;

class ImageGallery extends Image
{
    public function format($value)
    {
        $datas = [];
        foreach ($value->getItems() as $item) {
            if ($item) {
                $image = $item->getImage();
                if($image) {
                    $data = [
                        'path' => $image->getFrontendPath(),
                        'id' => $image->getId()
                    ];

                    $datas[] = $data;
                }
            }
        }

        return $datas;
    }

    public function formatDataSave($value)
    {
        $data = [];
        foreach ($value as $item) {
            $image = Asset\Image::getById((int)$item);
            if ($image) {
                $advancedImage = new \Pimcore\Model\DataObject\Data\Hotspotimage();
                $advancedImage->setImage($image);

                $data[] = $advancedImage;
            }
        }

        $format = new Images($data);
        return $format;
    }

    public function getFrontEndType()
    {
        return 'imageGallery';
    }
}

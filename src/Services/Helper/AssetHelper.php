<?php

/**
 * 
 * Get link with domain of asset
 * Create image, gallery from file(s) or url(s)
 *
 */

namespace CorepulseBundle\Services\Helper;

use Pimcore\Tool;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\Hotspotimage;

use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image\Thumbnail;


class AssetHelper
{
    const LOG_FILE_NAME = 'helper_asset';

    public static function getLink($asset)
    {
        if ($asset instanceof Asset) {
            return Tool::getHostUrl() . $asset->getFullPath();
        }

        return null;
    }

    public static function getThumbnailLink($asset, $thumbnailName)
    {
        if ($asset instanceof Asset) {

            $thumbnail = Thumbnail\Config::getByName($thumbnailName);
            if ($thumbnail) {

                return Tool::getHostUrl() . $asset->getThumbnail($thumbnail)->getPath();
            }

            return self::getLink($asset);
        }

        return null;
    }

    public static function createImageFromFile($file, $name, $folderPath)
    {
        try {
            $name = self::formatName($name, $file->guessExtension());
            $url = $file->getRealPath();

            return self::createImage($url, $name, $folderPath);
        } catch (\Throwable $e) {

            LogHelper::logError(self::LOG_FILE_NAME, (string) ($e . "\n \n"));
        }

        return null;
    }

    public static function createImageFromUrl(string $url, $name, $folderPath)
    {
        try {
            if (@exif_imagetype($url)) {
                $url = str_replace(' ', '', $url);
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

                if ($extension) {
                    $name = self::formatName($name, $extension);

                    return self::createImage($url, $name, $folderPath);
                }
            }
        } catch (\Throwable $e) {

            LogHelper::logError(self::LOG_FILE_NAME, (string) ($e . "\n \n"));
        }

        return null;
    }

    public static function createGalleryFromFiles(array $files, $name, $folderPath)
    {
        $gallery = [];

        foreach ($files as $key => $file) {
            $image = self::createImageFromFile($file, $name . '-' . $key, $folderPath);

            if ($image) {
                $hotspot = new Hotspotimage();
                $hotspot->setImage($image);

                $gallery[] = $hotspot;
            }
        }

        return new ImageGallery($gallery);
    }

    public static function createGalleryFromUrls(array $urls, $name, $folderPath)
    {
        $gallery = [];

        foreach ($urls as $key => $url) {
            $image = self::createImageFromUrl($url, $name . '-' . $key, $folderPath);

            if ($image) {
                $hotspot = new Hotspotimage();
                $hotspot->setImage($image);

                $gallery[] = $hotspot;
            }
        }

        return new ImageGallery($gallery);
    }

    private static function createImage($dataPath, $name, $folderPath)
    {
        try {
            $folder = Asset::getByPath($folderPath) ?? Asset\Service::createFolderByPath($folderPath);

            $image = new Asset\Image();
            $image->setFileName($name);
            $image->setData(@file_get_contents($dataPath));
            $image->setParent($folder);

            if ($image->save()) {
                return $image;
            }
        } catch (\Throwable $e) {

            LogHelper::logError(self::LOG_FILE_NAME, (string) ($e . "\n \n"));
        }

        return null;
    }

    private static function formatName($name, $extension = null)
    {
        $name = str_replace([' ', '/'], ['_', '+'], ltrim($name)) . '(' . time() . ')';

        if ($extension) {
            $name .= '.' . $extension;
        }

        return $name;
    }
}

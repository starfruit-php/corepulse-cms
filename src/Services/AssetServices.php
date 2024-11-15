<?php

namespace CorepulseBundle\Services;

use Google\Service\AIPlatformNotebooks\Status;
use Pimcore\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Pimcore\Model\Asset;

class AssetServices
{
    public static function createFolder($folderName, $folderId = '')
    {
        if ($folderId) {
            $parentId = Asset::getById($folderId);
            if ($parentId) {
                $folderName = str_replace($folderId == 1 ? 'null' : $folderId, $parentId->getFilename(), $folderName);
            }
        }

        $folder = Asset::getByPath($folderName) ?? Asset\Service::createFolderByPath($folderName);

        return $folder;
    }

    public static function createFile($file, $folder)
    {
        try {
            $asset = new Asset();
            $filename = time() . '-' . $file->getClientOriginalName();

            // convent filename
            $filename = preg_replace('/[^a-zA-Z0-9.]/', '-', $filename);
            $filename = preg_replace('/-+/', '-', $filename);
            $filename = trim($filename, '-');

            $asset->setFilename($filename);
            $asset->setData(file_get_contents($file));
            $asset->setParent($folder);

            $asset->save();

            return $asset;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function getThumbnailPath($asset)
    {
        $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/unknown.svg';

        if ($asset) {
            if ($asset->getType() == "folder") {
                $publicURL = '/bundles/pimcoreadmin/img/flat-color-icons/folder.svg';
            } elseif ($asset->getType() == "image") {
                if ($asset instanceof Asset\Image) {
                    $arr = [
                        "id" => $asset->getId(),
                        "treepreview" => "1",
                        "_dc" => $asset->getCreationDate(),
                    ];
                    $thumbnailConfig = $asset->getThumbnail($arr)->getConfig();
                    $format = strtolower($thumbnailConfig->getFormat());
                    if ($format == 'source' || $format == 'print') {
                        $thumbnailConfig->setFormat('PNG');
                        $thumbnailConfig->setRasterizeSVG(true);
                    }
                    $thumbnailConfig = Asset\Image\Thumbnail\Config::getPreviewConfig();
                    $thumbnail = $asset->getThumbnail($thumbnailConfig);
    
                    if ($thumbnail) {
                        $thumbnail = $thumbnail->getPathReference();
                        $publicURL = $thumbnail['src'];
                    } else {
                        $publicURL = "/admin/asset/get-image-thumbnail?id=" . $asset->getId() . "&treepreview=1&_dc=" . $asset->getCreationDate();
                    }
                }
            } elseif ($asset->getType() == "video") {
                $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/video_file.svg';
            } elseif ($asset->getType() == "document") {
                $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/pdf.svg';
                if (strpos($asset->getFileName(), 'docx')) {
                    $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/word.svg';
                } 
                if (strpos($asset->getFileName(), 'xlsx')) {
                    $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/excel.svg';
                } 
            } elseif ($asset->getType() == "text") {
                $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/text.svg';
            } else {
                $publicURL ='/bundles/pimcoreadmin/img/flat-color-icons/unknown.svg';
            }
        }

        return $publicURL;
    }
}

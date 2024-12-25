<?php

namespace CorepulseBundle\Controller\Admin;

use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use CorepulseBundle\Services\AssetServices;
use Pimcore\Model\Asset;
use DateTime;
use Pimcore\Model\Asset\Service as AssetService;

/**
 * @Route("/asset")
 */
class AssetController extends BaseController
{
    /**
     * @Route("/listing", name="api_asset_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listing(
        Request $request,
        PaginatorInterface $paginator): JsonResponse
    {
        try {
            $this->setLocaleRequest();

            $orderByOptions = ['mimetype'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'folderId' => '',
                'type' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $id = $request->get('folderId') ? $request->get('folderId') : 1;
            if ($id) {
                $conditionQuery .= ' AND parentId = :parentId';
                $conditionParams['parentId'] = $id;
            }

            $type = $request->get('type') ? $request->get('type') : '';
            if ($type) {
                $conditionQuery .= ' AND type = :type';
                $conditionParams['type'] = $type;
            }

            $search = $request->get('search') ? $request->get('search') : '';
            if ($search) {
                $conditionQuery .= " AND LOWER(`filename`)" . " LIKE LOWER('%" . $search . "%')";
            }

            $list = new Asset\Listing();
            $list->setOrderKey($request->get('order_by', 'mimetype'));
            $list->setOrder($request->get('order', 'asc'));
            $list->setCondition($conditionQuery, $conditionParams);
            $list->load();

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'data' => []
                ],
                $paginationData,
            );

            foreach($list as $item)
            {
                $data['data'][] = self::listingResponse($item);
            }

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail", name="api_asset_detail", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function detail(
        Request $request,
        PaginatorInterface $paginator): JsonResponse
    {
        try {
            $this->setLocaleRequest();
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('id');
            $item = Asset::getById($id);
            if ($item) {
                if ($item->getType() != 'folder') {
                    $data['data'] = self::detailResponse($item);
                    return $this->sendResponse($data);
                }
                return $this->sendError('Type asset invalid');
            }
            return $this->sendError('Asset not found');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/upload-folder", name="api_asset_upload_folder", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function uploadFolder(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'nameFolder' => 'required',
                'parentId' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $nameFolder = $request->get('nameFolder');
            if ($nameFolder) {
                $parentId = $request->get('parentId');
                if ($parentId) {
                    $folders = Asset::getById($parentId);
                    if ($folders) {
                        $path = $folders->getPath() . $folders->getFileName();
                        AssetService::createFolderByPath($path . "/" . $nameFolder);

                    } else {
                        AssetService::createFolderByPath("/" . $nameFolder);
                    }
                } else {
                    AssetService::createFolderByPath("/" . $nameFolder);
                }
                return $this->sendResponse('folder.create.success');
            } else {
                return $this->sendError('folder.create.error');
            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/upload-file", name="api_asset_upload_file", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @throws \Exception
     */
    public function uploadFile()
    {
        try {
            $condition = [
                'file' => 'required|file',
                'parentId' => 'numeric',
                'path' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $file = $this->request->files->get("file");

            $parentId = $this->request->get('parentId') ;
            if (!$parentId) {
                $parent = Asset::getByPath('/_default_upload_bucket');
                if (!$parent) {
                    $parentId = Asset\Service::createFolderByPath('/_default_upload_bucket')->getId();
                } else {
                    $parentId = $parent->getId();
                }
            }
            $folder = Asset::getById($parentId);

            $path = $this->request->get('path') ?? '';
            if ($path) {
                $parentPath = $folder->getFullPath();
                $fullPathNew = $parentPath . dirname($path);

                $folder = Asset::getByPath($fullPathNew) ?? Asset\Service::createFolderByPath($fullPathNew);
            }

            $upload = AssetServices::createFile($file, $folder);

            if(!$upload) return $this->sendError(['success' => false, 'message' => 'Upload file error']);

            return $this->sendResponse([ 'success' => true, 'message' => 'Upload file success', 'id' =>  $upload->getId(), 'parentId' =>  $upload->getParentId() ]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="api_asset_delete", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function delete(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $itemId = $request->get('id');
            if (is_array($itemId)) {
                foreach ($itemId as $item) {
                    $asset_detail = Asset::getById((int)$item);
                    if ($asset_detail) {
                        $asset_detail->delete();
                    } else {
                        return $this->sendError('Can not find photos or folders to be deleted');
                    }
                }
            } else {
                if ($itemId) {
                    $asset_detail = Asset::getById((int)$itemId);
                    if ($asset_detail) {
                        $asset_detail->delete();
                    } else {
                        return $this->sendError('Can not find photos or folders to be deleted');
                    }
                }
            }

            return $this->sendResponse("Delete photos or folders success");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/replace-image", name="api_asset_replace_image", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function replaceImage(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'id' => 'required',
                'file' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('id');
            $file = $request->files->get("file");

            if ($file) {
                $asset = Asset::getById($id);
                $asset->setData(file_get_contents($file));
                $asset->save();

                return $this->sendResponse('replace.image.success');
            }
            return $this->sendError('replace.image.error');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add-attribute", name="api_asset_add_attribute", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function addAttribute(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'id' => 'required',
                'alt' => '',
                'caption' => '',
                'description' => '',
                'language' => '',
                'fileName' => '',
                'videoMov' => '',
                'videoWebm' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $itemId = $request->get('id');
            $alt = $request->get('alt');
            $caption = $request->get('caption');
            $description = $request->get('description');
            $language = $request->get('language');
            $fileName = $request->get('fileName');

            $videoMov = $request->get('videoMov');
            $videoWebm = $request->get('videoWebm');

            $asset_detail = Asset::getById((int)$itemId);

            if ($alt) {
                $asset_detail->addMetadata("alt", "input", $alt, $language);
            }
            if ($caption) {
                $asset_detail->addMetadata("caption", "input", $caption, $language);
            }
            if ($description) {
                $asset_detail->addMetadata("description", "textarea", $description, $language);
            }
            if ($fileName && $fileName !=  $asset_detail->getFileName()) {
                $asset_detail->setFileName($fileName);
            }

            $mov = Asset::getByPath($videoMov);
            $asset_detail->addMetadata("mov", "asset", $mov, $language);

            $webm = Asset::getByPath($videoWebm);
            $asset_detail->addMetadata("webm", "asset", $webm, $language);


            $asset_detail->save();
            $data = [
                'id' => $itemId
            ];

            if ($language != 'null') {
                $data['language'] = $language;
            }

            return $this->sendResponse('add.attribute.success');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }


     /**
     * @Route("/get-meta-data", name="api_asset_get_meta_data", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getMetaData(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'id' => 'required',
                'lang' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('id');
            $language = $request->get('lang');

            $item = Asset::getById((int)$id);
            if ($item) {
                $alt = '';
                $caption = '';
                $description = '';
                $videoMov = '';
                $videoWebm = '';

                $metaData = $item->getMetaData();
                foreach ($metaData as $item) {
                    if (($item['name'] == 'alt') && ($item['language'] == $language)) {
                        $alt = $item['data'];
                    }
                    if (($item['name'] == 'caption') && ($item['language'] == $language)) {
                        $caption = $item['data'];
                    }
                    if (($item['name'] == 'description') && ($item['language'] == $language)) {
                        $description = $item['data'];
                    }
                    if (($item['name'] == 'mov') && ($item['language'] == $language)) {
                        $videoMov = $item['data']->getPath() . $item['data']->getFileName();
                    }
                    if (($item['name'] == 'webm') && ($item['language'] == $language)) {
                        $videoWebm = $item['data']->getPath() . $item['data']->getFileName();
                    }
                }
                $data['data'] = [
                    'language' => $language,
                    'attribute' => [
                        'alt' => $alt,
                        'caption' => $caption,
                        'description' => $description,
                        'videoMov' => $videoMov,
                        'videoWebm' => $videoWebm,
                    ],
                ];

                return $this->sendResponse($data);
            }
            return $this->sendError('Media not found');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // Trả ra dữ liệu
    public function listingResponse($item)
    {
        $fomat = '';
        if ($item->getMimeType()) {
            $fomat = explode('/', $item->getMimeType());
            $fomat =  $fomat[1];
        }

        $publicURL = '';

        $json = [
            'id' => $item->getId(),
            'file' => $item->getFileName(),
            'fileName' =>  $item->getFileName(),
            'thumbnail' => $publicURL,
            'creationDate' => ($item->getType() != "folder") ? self::getTimeAgo($item->getCreationDate()) : '',
            'size' => ($item->getType() != "folder") ? round((int)$item->getFileSize() / (1024 * 1024), 2) . "MB" : '',
            'parenId' => $item->getParent()?->getId(),
            'type' => ($item->getType() == "folder") ? 'folder' : $item->getType(),
            'fomat' => $fomat,
            'publicURL' => $publicURL,
            'urlDownload' => $item->getPath() . $item->getFileName(),
            'showPreview' =>  false,
            'previewURL' => $publicURL
        ];

        return $json;
    }

    public function detailResponse($item)
    {
        $languages = \Pimcore\Tool::getValidLanguages();
        $domain = $_SERVER['HTTP_HOST'];
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $protocol . $_SERVER['HTTP_HOST'];

        $fomat = '';
        if ($item->getMimeType()) {
            $fomat = explode('/', $item->getMimeType());
            $fomat =  $fomat[1];
        }

        $width = 0;
        $height = 0;
        if ($item->getType() == 'image') {
            $width = $item->getWidth();
            $height = $item->getHeight();
        }

        $language = $languages[0];

        $alt = '';
        $caption = '';
        $description = '';
        $videoMov = '';
        $videoWebm = '';

        $metaData = $item->getMetaData();
        foreach ($metaData as $item) {
            if (($item['name'] == 'alt') && ($item['language'] == $language)) {
                $alt = $item['data'];
            }
            if (($item['name'] == 'caption') && ($item['language'] == $language)) {
                $caption = $item['data'];
            }
            if (($item['name'] == 'description') && ($item['language'] == $language)) {
                $description = $item['data'];
            }
            if (($item['name'] == 'mov') && ($item['language'] == $language)) {
                $videoMov = $item['data']->getPath() . $item['data']->getFileName();
            }
            if (($item['name'] == 'webm') && ($item['language'] == $language)) {
                $videoWebm = $item['data']->getPath() . $item['data']->getFileName();
            }
        }

        $json = [
            'id' => $item->getId(),
            'filename' => $item->getFileName(),
            'publicURL' => $domain . $item->getPath() . $item->getFileName(),
            'path' =>  $item->getPath() . $item->getFileName(),
            'size' => round((int)$item->getFileSize() / (1024 * 1024), 3) . " MB",
            'fomat' => $fomat,
            'type' => $item->getType(),
            'mimetype' => $item->getMimetype(),
            'width' => $width,
            'dimensions' => $width . " x " . $height,
            'uploadOn' => date("M j, Y  H:i", $item->getModificationDate()),

            'data' => ($item->getType() == 'text') ? $item->getData() : '',
            'languages' => $languages,
            'attribute' => [
                'alt' => $alt,
                'caption' => $caption,
                'description' => $description,
                'videoMov' => $videoMov,
                'videoWebm' => $videoWebm,
            ],

            'parentId' => $item->getParentId(),
        ];

        return $json;
    }
}

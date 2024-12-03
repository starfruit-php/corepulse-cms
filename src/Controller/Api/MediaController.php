<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\Asset;
use CorepulseBundle\Services\Helper\SearchHelper;

/**
 * @Route("/media")
 */
class MediaController extends BaseController
{
    /**
     * @Route("/get-asset", name="api_get_asset", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getAsset(): JsonResponse
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'id' => '',
                'filterRule' => '',
                'filter' => '',
                'search' => '',
                'type' => '',
                'checked' => '',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            $parentId = $this->request->get('id');
            $checked = $this->request->get('checked', false);
            if (empty($orderKey)) $orderKey = 'creationDate';
            if (empty($order)) $order = 'desc';
            if (empty($parentId)) $parentId = 1;

            $search = $this->request->get('search');
            $type = $this->request->get('type');

            $conditionQuery = 'id != 1  AND parentId = :parentId';
            $conditionParams['parentId'] = $parentId;

            if (!$checked) $conditionQuery .= ' AND type != "folder" ';
            if ($search) $conditionQuery .= ' AND ' . "LOWER(`filename`)" . " LIKE LOWER('%" . $search . "%')";

            if ($type) {
                $conditionQuery .= ' AND type = :type';
                $conditionParams['type'] = $type;
            }

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $listingAsset = new \Pimcore\Model\Asset\Listing();
            $listingAsset->setCondition($conditionQuery, $conditionParams);
            $listingAsset->setOrderKey($orderKey);
            $listingAsset->setOrder($order);

            $paginationData = $this->paginator($listingAsset, $page, $limit);
            $data = [
                'data' => [],
                'paginationData' => $paginationData->getPaginationData(),
            ];

            foreach ($paginationData as $item) {
                if ($item->getType() != "folder") {
                    // $publicURL = AssetServices::getThumbnailPath($item);
                    $publicURL = $item?->getFrontendPath();
                    $data['data'][] = [
                        'id' => $item->getId(),
                        'type' => $item->getType(),
                        'mimetype' => $item->getMimetype(),
                        'filename' => $item->getFileName(),
                        'fullPath' => $publicURL,
                        'parentId' => $item->getParentId(),
                        'checked' => false,
                        'path' => $item->getFullPath(),
                    ];
                } else {
                    $publicURL = $item?->getFrontendPath();
                    $data['data'][] = [
                        'id' => $item->getId(),
                        'type' => $item->getType(),
                        'mimetype' => $item->getType(),
                        'filename' => $item->getFileName(),
                        'fullPath' => $publicURL,
                        'parentId' => $item->getParentId(),
                        'checked' => false,
                        'path' => $item->getFullPath(),
                    ];
                }
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/tree-listing-asset", name="api_tree_listing_asset", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function treeListing(
    ): JsonResponse {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'filterRule' => '',
                'filter' => '',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $orderBy = $this->request->get('order_by', 'mimetype');
            $order = $this->request->get('order', 'asc');
            if ($orderBy == 'key') {
                $orderBy = 'filename';
            }

            $conditionQuery = '`parentId` = 0 OR `parentId` = 1 AND type = "folder"';
            $conditionParams = [];

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $datas['data'] = [];

            $listing = new Asset\Listing();
            $listing->setCondition($conditionQuery, $conditionParams);
            $listing->setOrderKey($orderBy);
            $listing->setOrder($order);

            foreach ($listing as $item) {
                $data = [];
                foreach ($item->getChildren() as $children) {
                    if ($children->getType() == "folder") {
                        $data[] = (string)$children->getId();
                    }
                }

                // $publicURL = AssetServices::getThumbnailPath($item);
                $publicURL = $item?->getFrontendPath();
                $datas['data'][] = [
                    'id' => $item->getId(),
                    'filename' => $item->getFileName() ? $item->getFileName() : "Home",
                    'type' => $item->getType(),
                    'children' => $data,
                    'icon' => SearchHelper::getIcon($item->getType()),
                    'image' => $publicURL,
                    'publish' => true,
                ];
            }

            return $this->sendResponse($datas);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

     /**
     * @Route("/get-folder", name="api_get_folder", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getFolder(
    ): JsonResponse {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'types' => '',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id != 1 AND parentId = :parentId';
            $conditionParams = [
                'parentId' => 1,
            ];

            $checkType = $this->request->get('types');
            if (!$checkType) $checkType = 'image';

            $list = new \Pimcore\Model\Asset\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey('creationDate');
            $list->setOrder('ASC');
            $list->load();

            $paginationData = $this->paginator($list, $page, $limit);
            $data = [
                'data' => [],
                'paginationData' => $paginationData->getPaginationData(),
            ];

            $data['data']['folders'][] = [
                'id' => 1,
                'name' => 'Home',
                'icon' => '/bundles/pimcoreadmin/img/flat-color-icons/home-gray.svg',
            ];

            foreach ($list as $item) {
                if ($item->getType() == "folder") {
                    $data['folders'][] = [
                        'id' => $item->getId(),
                        'name' => $item->getFilename(),
                        'icon' => "/bundles/pimcoreadmin/img/flat-color-icons/folder.svg",
                    ];
                } else {
                    // $publicURL = AssetServices::getThumbnailPath($item);
                    $publicURL = $item?->getFrontendPath();
                    $data['data']['images'][] = [
                        'id' => $item->getId(),
                        'type' => $item->getType(),
                        'mimetype' => $item->getMimetype(),
                        'name' => $item->getFileName(),
                        'fullPath' =>  $publicURL,
                        'parentId' => $item->getParentId(),
                        'path' => $item->getFullPath(),
                    ];
                }
            }

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/tree-children-asset", name="api_tree_children_asset", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function treeChildrenDoc(
    ): JsonResponse {
        try {
            $orderByOptions = ['mimetype'];
            $conditions = $this->getPaginationConditions($this->request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'id' => 'required',
                'config' => '',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $id = $this->request->get('id');
            $config = $this->request->get('config');

            $datas['data'] = [];

            $conditions = '`parentId` = ? AND type = "folder"';
            $params = [ $id ];

            if($config) {
                $conditions .= ' AND (';

                foreach($config as $key) {
                    $conditions .= ' `classId` = ? OR ';
                    $params[] = $key;
                }

                $conditions .= ' `classId` IS NULL)';
            }

            $listing = new Asset\Listing();
            $listing->setCondition($conditions, $params);
            $listing->setOrderKey('mimetype');
            $listing->setOrder('ASC');

            foreach ($listing as $item) {
                $data = [];
                foreach ($item->getChildren() as $children) {
                    if ($children->getType() == 'folder') {
                        $data[] = (string)$children->getId();
                    }
                }
                // $publicURL = AssetServices::getThumbnailPath($item);
                $publicURL = $item?->getFrontendPath();
                $datas['data'][] = [
                    'id' => $item->getId(),
                    'filename' => $item->getFileName(),
                    'type' => $item->getType(),
                    'children' => $data,
                    'icon' => SearchHelper::getIcon($item->getType()),
                    'publish' => true,
                    'image' => $publicURL,
                ];
            }
            return $this->sendResponse($datas);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\Asset;
use CorepulseBundle\Services\Helper\SearchHelper;
use CorepulseBundle\Services\PermissionServices;

/**
 * @Route("/media")
 */
class MediaController extends BaseController
{
    /**
     * @Route("/get-asset", name="corepulse_admin_get_asset", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getAsset(
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $conditions = $this->getPaginationConditions($request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'order_by' => '',
                'order' => '',
                'id' => '',
                'filterRule' => '',
                'filter' => '',
                'search' => '',
                'type' => '',
            ]);

            $messageError = $this->validator->validate($condition, $request);
            if ($messageError) return $this->sendError($messageError);

            $order_by = $request->get('order_by') ? $request->get('order_by') : 'creationDate';
            $order = $request->get('order') ? $request->get('order') : 'DESC';
            $parentId = $request->get('id') ? $request->get('id') : '1';
            $search = $request->get('search');
            $type = $request->get('type');

            $this->validPermissionOrFail(PermissionServices::TYPE_ASSETS, $parentId, PermissionServices::ACTION_LISTING);

            $conditionQuery = 'id != 1 AND type != "folder" AND parentId = :parentId';
            $conditionParams['parentId'] = $parentId;

            $filterRule = $request->get('filterRule');
            $filter = $request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            if ($search) {
                $conditionQuery .= ' AND ' . "LOWER(`filename`)" . " LIKE LOWER('%" . $search . "%')";
            }

            if ($type) {
                $conditionQuery .= ' AND type = :type';
                $conditionParams['type'] = $type;
            }

            $listingAsset = new \Pimcore\Model\Asset\Listing();
            $listingAsset->setCondition($conditionQuery, $conditionParams);
            $listingAsset->setOrderKey($order_by);
            $listingAsset->setOrder($order);

            $user = $this->getUser();
            $permissionData = PermissionServices::getPermissionData($user);
            // $listPermission = array_filter($listingAsset->getData(), function($item) use ($user, $permissionData) {
            //     return $user->getDefaultAdmin() || PermissionServices::isValid($permissionData, PermissionServices::TYPE_ASSETS, $item->getId(), PermissionServices::ACTION_LISTING);
            // });

            $paginationData = $this->paginator( $listingAsset->getData(), $page, $limit);

            $data = [
                'data' => array_map(function($item) use ($permissionData) {
                    return self::listingResponse($item, array_column($permissionData[PermissionServices::TYPE_ASSETS], null, 'path'));
                }, $paginationData->getItems()),
                'paginationData' => $paginationData->getPaginationData(),
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/tree-listing-asset", name="corepulse_admin_tree_listing_asset", methods={"GET"})
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
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $conditions = $this->getPaginationConditions($request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'order_by' => '',
                'order' => '',
                'filterRule' => '',
                'filter' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if($messageError) return $this->sendError($messageError);

            $orderBy = $request->get('order_by', 'mimetype');
            $order = $request->get('order', 'asc');
            if ($orderBy == 'key') {
                $orderBy = 'filename';
            }

            $conditionQuery = '`parentId` = 0 OR `parentId` = 1 AND type = "folder"';
            $conditionParams = [];

            $filterRule = $request->get('filterRule');
            $filter = $request->get('filter');

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
     * @Route("/get-folder", name="corepulse_admin_get_folder", methods={"GET"})
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
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $orderByOptions = ['creationDate'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'types' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id != 1 AND parentId = :parentId';
            $conditionParams = [
                'parentId' => 1,
            ];

            $checkType = $request->get('types');
            if (!$checkType) $checkType = 'image';

            $list = new \Pimcore\Model\Asset\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey('creationDate');
            $list->setOrder('ASC');
            $list->load();

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'data' => []
                ],
                $paginationData,
            );

            $data['data']['folders'][] = [
                'id' => 1,
                'name' => 'Home',
                'icon' => '/bundles/pimcoreadmin/img/flat-color-icons/home-gray.svg',
            ];
            $images = [];
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
     * @Route("/tree-children-asset", name="corepulse_admin_tree_children_asset", methods={"GET"})
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
            $condition = [
                'id' => 'required',
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $id = $this->request->get('id');

            $this->validPermissionOrFail(PermissionServices::TYPE_ASSETS, $id, PermissionServices::ACTION_LISTING);

            $conditions = '`parentId` = ? AND type = "folder"';
            $params = [ $id ];
            $listingAsset = new Asset\Listing();
            $listingAsset->setCondition($conditions, $params);
            $listingAsset->setOrderKey('mimetype');
            $listingAsset->setOrder('ASC');

            $user = $this->getUser();
            $permissionData = PermissionServices::getPermissionData($user);
            $listPermission = array_filter($listingAsset->getData(), function($item) use ($user, $permissionData) {
                return $user->getDefaultAdmin() || PermissionServices::isValid($permissionData, PermissionServices::TYPE_ASSETS, $item->getId(), PermissionServices::ACTION_LISTING);
            });

            $datas = [
                'data' => array_map(function($item) use ($permissionData) {
                    return self::listingResponse($item, array_column($permissionData[PermissionServices::TYPE_ASSETS], null, 'path'), true);
                }, $listPermission),
            ];

            return $this->sendResponse($datas);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function listingResponse($item, $permissionData = [], $treeFolder = false)
    {
        $id = $item->getId();
        $data = [
            'permissions' => [],
        ];

        if (!$treeFolder) {
            $publicURL = $item?->getFrontendPath();
            $data = array_merge($data, [
                'id' => $id,
                'type' => $item->getType(),
                'mimetype' => $item->getMimetype(),
                'filename' => $item->getFileName(),
                'fullPath' => $publicURL,
                'parentId' => $item->getParentId(),
                'checked' => false,
                'path' => $item->getFullPath(),
            ]);
        } elseif ($treeFolder) {
            $childs = [];
            foreach ($item->getChildren() as $children) {
                if ($children->getType() == 'folder') {
                    $childs[] = (string)$children->getId();
                }
            }

            $publicURL = $item?->getFrontendPath();
            $data = array_merge($data, [
                'id' => $item->getId(),
                'filename' => $item->getFileName(),
                'type' => $item->getType(),
                'children' => $childs,
                'publish' => true,
                'image' => $publicURL,
            ]);
        }

        //fill all permission
        if (!empty($permissionData)) {
            $data['permissions'] = PermissionServices::getPermissionsRecursively($permissionData, PermissionServices::TYPE_ASSETS, $id);
        } elseif ($this->getUser()?->getDefaultAdmin()) {
            $data['permissions'] = $this->getDefaultPermission();
        }

        return $data;
    }
}

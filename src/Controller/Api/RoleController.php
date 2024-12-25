<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Model\Role;
use CorepulseBundle\Services\PermissionServices;
use CorepulseBundle\Services\RoleServices;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/role")
 */
class RoleController extends BaseController
{
    const TYPE_PERMISSION = 'role';

    /**
     * @Route("/listing", name="corepulse_api_role_listing", methods={"GET","POST"})
     */
    public function listing()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_LISTING);
          
            $this->setLocaleRequest();
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'filterRule' => '',
                'filter' => '',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $conditionQuery = "";
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

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) {
                $orderKey = 'id';
            }

            if (empty($order)) {
                $order = 'desc';
            }

            $list = new Role\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey($orderKey);
            $list->setOrder($order);

            $paginationData = $this->paginator($list, $page, $limit);
            $data = [
                'paginationData' => $paginationData->getPaginationData(),
                'data' => [],
            ];

            foreach ($paginationData as $item) {
                $data['data'][] = RoleServices::getJson($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="corepulse_api_role_add", methods={"POST"})
     */
    public function add()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_CREATE);
            $condition = [
                'name' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $params = [
                'name' => $this->request->get('name'),
            ];

            $role = RoleServices::create($params);
            if ($role) {
                $data = [
                    'success' => true,
                    'message' => 'Role create success.',
                    'trans' => 'role.success.create_success',
                    'data' => RoleServices::getJson($role),
                ];
                return $this->sendResponse($data);
            }

            return $this->sendError([
                'message' => 'Create failed', 
                'trans' => 'role.errors.detail.create_errors',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/detail/{id}", name="corepulse_api_role_detail", methods={"GET", "POST"})
     */
    public function detail(): JsonResponse
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_VIEW);
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $id = $this->request->get('id');
            $role = Role::getById($id);

            if (!$role) {
                return $this->sendError([
                    'message' => "Role not found",
                    'trans' => 'role.error.detail.not_found',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($this->request->isMethod(Request::METHOD_POST)) {
                $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SAVE);
                $condition = [
                    'setting' => 'json',
                    'assets' => 'json',
                    'documents' => 'json',
                    'objects' => 'json',
                    'others' => 'json',
                ];
    
                $errorMessages = $this->validator->validate($condition, $this->request);
                if ($errorMessages) return $this->sendError($errorMessages);

                try {
                    $params = RoleServices::handleParams($this->request->request->all());
                    $update = RoleServices::edit($params, $role);

                    return $this->sendResponse([
                        'success' => true, 
                        'message' => 'Role update success.',
                        'trans' => 'role.success.update_success',
                    ]);
                } catch (\Throwable $th) {
                    return $this->sendError([
                        'message' => $th->getMessage()
                    ]);
                }
            }

            $permission = $role->getPermission() ? json_decode($role->getPermission(), true) : [
                'documents' => [],
                'assets' => [],
                'objects' => [],
                'others' => [],
            ];

            $data['role'] = [
                'id' => $role->getId(),
                'name' => $role->getName(),
                'permission' => $permission,
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_role_delete", methods={"GET"})
     */
    public function delete(): JsonResponse
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_DELETE);
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $ids = $this->request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $role = Role::getById((int) $id);
                    if ($role) {
                        $role->delete();
                    } else {
                        return $this->sendError([
                            'message' => "Can not find role to be deleted",
                            'trans' => 'role.error.detail.not_found',
                        ], Response::HTTP_FORBIDDEN);
                    }
                }
            } else {
                $role = Role::getById((int) $ids);
                if ($role) {
                    $role->delete();
                } else {
                    return $this->sendError([
                        'message' => "Can not find role to be deleted",
                        'trans' => 'role.error.detail.not_found',
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            return $this->sendResponse([
                'success' => true, 
                'message' => "Delete page success",
                'trans' => 'role.success.delete_success',
            ]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

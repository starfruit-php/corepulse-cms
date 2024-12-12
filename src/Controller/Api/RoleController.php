<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Model\Role;
use CorepulseBundle\Services\RoleServices;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/role")
 */
class RoleController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_role_listing", methods={"GET","POST"})
     */
    public function listing()
    {
        try {
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
                    'data' => RoleServices::getJson($role),
                ];
                return $this->sendResponse($data);
            }

            return $this->sendError('Create failed');
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
                return $this->sendError(['message' => "Role not found"]);
            }

            if ($this->request->isMethod(Request::METHOD_POST)) {
                try {
                    $params = RoleServices::handleParams($this->request->request->all());
                    $update = RoleServices::edit($params, $role);

                    return $this->sendResponse(['success' => true, 'message' => 'Role update success.']);
                } catch (\Throwable $th) {
                    return $this->sendError(['message' => $th->getMessage()]);
                }
            }

            $permission = $role->getPermission() ? json_decode($role->getPermission(), true) : [
                'documents' => [],
                'assets' => [],
                'objects' => [],
                'other' => [],
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
                        return $this->sendError(['message' => "Can not find role to be deleted"]);
                    }
                }
            } else {
                $role = Role::getById((int) $ids);
                if ($role) {
                    $role->delete();
                } else {
                    return $this->sendError(['message' => "Can not find role to be deleted"]);
                }
            }

            return $this->sendResponse(['success' => true, 'message' => "Delete page success"]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

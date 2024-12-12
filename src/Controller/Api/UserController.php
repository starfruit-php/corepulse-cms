<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Services\UserServices;
use CorepulseBundle\Model\User;
use CorepulseBundle\Model\Role;
use \Pimcore\Model\Asset;
use CorepulseBundle\Security\Hasher\CorepulseUserPasswordHasher;

/**
 * @Route("/user")
 */
class UserController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_user_listing", methods={"GET"})
     */
    public function listing(): JsonResponse {
        try {
            $this->setLocaleRequest();
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'filterRule' => '',
                'filter' => '',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $conditionQuery = "defaultAdmin is NULL OR defaultAdmin = ''";
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
            if (empty($orderKey)) $orderKey = 'id';
            if (empty($order)) $order = 'desc';

            $list = new User\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey($orderKey);
            $list->setOrder($order);

            $paginationData = $this->paginator($list, $page, $limit);
            $data = [
                'data' => [],
                'paginationData' => $paginationData->getPaginationData(),
            ];

            foreach ($paginationData as $item) {
                $data['data'][] = UserServices::getJson($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="corepulse_api_user_add", methods={"POST"})
     */
    public function add()
    {
        try {
            $condition = [
                'username' => 'required|whitespace|regex:^[a-zA-Z0-9]+$',
                'password' => 'required|length:min,8,max,70',
            ];
    
            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);
    
            $params = [
                'username' => $this->request->get('username'),
                'password' => $this->request->get('password')
            ];
            
            $user = UserServices::create($params);
            
            if ($user){
                $data = [
                    'success' => true, 
                    'message' => 'User create success.',
                    'data' => UserServices::getJson($user),
                ];
                return $this->sendResponse($data);
            }

            return $this->sendError('Create failed');
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_user_delete", methods={"GET"})
     */
    public function delete(): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $ids = $this->request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $user = User::getById((int) $id);
                    if ($user) {
                        $user->delete();
                    } else {
                        return $this->sendError(['message' => "Can not find user to be deleted"]);
                    }
                }
            } else {
                $user = User::getById((int) $ids);
                if ($user) {
                    $user->delete();
                } else {
                    return $this->sendError(['message' => "Can not find user to be deleted"]);
                }
            }

            return $this->sendResponse([ 'success' => true, 'message' => "Delete page success"]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail/{id}", name="corepulse_api_user_detail", methods={"GET", "POST"})
     */
    public function detail(): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $this->request->get('id');
            $user = User::getById($id);

            if (!$user) return $this->sendResponse([ 'success' => false, 'message' => "User not found"]);

            if ($this->request->isMethod(Request::METHOD_POST)) {
                $condition = [
                    'setting' => 'json',
                    'assets' => 'json',
                    'documents' => 'json',
                    'objects' => 'json',
                ];
    
                $errorMessages = $this->validator->validate($condition, $this->request);
                if ($errorMessages) return $this->sendError($errorMessages);
    
                try {
                    $params = UserServices::handleParams($this->request->request->all());
                    $update = UserServices::edit($params, $user);
    
                    return $this->sendResponse(['success' => true, 'message' => 'User update success.']);
                } catch (\Throwable $th) {
                    return $this->sendError(['success' => false, 'message' => $th->getMessage()]);
                }
            }

            $listRole = new Role\Listing();
            $roles = [];
            foreach ($listRole as $key => $role) {
                $roles[] = [
                    'id' => $role->getId(),
                    'name' => $role->getName(),
                ];
            }

            $permission = $user->getPermission() ? json_decode($user->getPermission(), true) : [
                'documents' => [], 
                'assets' => [], 
                'objects' => [], 
                'other' => [],
            ];

            $data['user'] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'role' => $user->getRole(),
                'active' => $user->getActive(),
                'password' => '',
                'permission' => $permission,
            ];

            $data['roles'] = $roles;

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/profile", name="api_user_profile", methods={"GET"})
     */
    public function getProfile(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $data['data'] = [];

            if ($user) {
                $data['data'] = self::infoUser($user);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/edit-profile", name="api_user_edit_profile", methods={"GET"})
     */
    public function editProfile(Request $request): JsonResponse
    {
        try {
            $condition = [
                'avatar' => '',
                'name' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $user = $this->getUser();
            if ($user) {
                $avatar = $request->get('avatar');
                if ($avatar) {
                    $image = Asset::getByPath($avatar);
                    if ($image) {
                        $user->setAvatar($image);
                    }
                }

                $name = $request->get('name');
                if ($name) {
                    $user->setName($name);
                }
                $user->save();

                return $this->sendResponse('edit.profile.success');
            }
            return $this->sendError('edit.profile.error');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/edit-password", name="api_user_edit_password", methods={"GET"})
     */
    public function editPassword(Request $request): JsonResponse
    {
        try {
            $condition = [
                'oldPassword' => '',
                'password' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $user = $this->getUser();
            if ($user) {
                $oldPassword =  $request->get('oldPassword');
                $password =  $request->get('password');
                $oldPassword = md5($user->getUsername() . ':corepulse:' . $oldPassword);

                if (!password_verify($oldPassword, $user->getPassword())) {
                    return $this->sendError('Your old password is incorrect');
                } else {
                    $user->setPassword(CorepulseUserPasswordHasher::getPasswordHash($user->getUsername(), $password));
                    $user->save();

                    return $this->sendResponse('edit.password.success');
                }
            }
            return $this->sendError('edit.password.error');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    public function infoUser($user)
    {
        $json[] = [
            'username' => $user->getUsername(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatar(),
        ];

        return $json;
    }
}

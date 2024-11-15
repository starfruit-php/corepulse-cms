<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use CorepulseBundle\Model\User;
use CorepulseBundle\Model\Role;
use \Pimcore\Model\Asset;
use CorepulseBundle\Security\Hasher\CorepulseUserPasswordHasher;
use CorepulseBundle\Services\RoleServices;

/**
 * @Route("/user")
 */
class UserController extends BaseController
{
    /**
     * @Route("/listing", name="api_user_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listingAction(
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $this->setLocaleRequest();

            $orderByOptions = ['name'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'order_by' => '',
                'order' => '',
                'search' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if ($messageError) return $this->sendError($messageError);

            $conditionQuery = "defaultAdmin is Null Or defaultAdmin = ''";
            $conditionParams = [];

            $search = $request->get('search');
            if ($search) {
                foreach ($search as $key => $value) {
                    $conditionName = $key . " LIKE '%" . $value . "%'";
                    $conditionQuery .= ' AND ' . $conditionName;
                }
            }

            $order_by = $request->get('order_by', 'name');
            $order = $request->get('order', 'desc');

            $list = new User\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey($order_by);
            $list->setOrder($order);

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'totalItems' => $list->count(),
                    'data' => [],
                ],
                $paginationData,
            );

            foreach ($list as $item) {
                $data['data'][] = self::listingResponse($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail", name="api_user_detail", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function detailAction( Request $request ): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('id');

            $listRole = new Role\Listing();
            $roles = [];
            foreach ($listRole as $key => $role) {
                $roles[] = [
                    'id' => $role->getId(),
                    'name' => $role->getName(),
                ];
            }

            $user = User::getById($id);

            // Lấy quyền của role và quyền của user
            $rolePermission = $user->getRole() ? Role::getById($user->getRole()) ? json_decode(Role::getById($user->getRole())->getPermission(), true) : [] : [];
            $userPermission = $user->getPermission() ? json_decode($user->getPermission(), true) : [];
            if ($rolePermission == null) {
                $rolePermission = [];
            }
            // xử lý gộp quyền
            $mergedArray = array_merge($rolePermission, $userPermission);
            $uniqueRole = array_unique($mergedArray);
            $permission = array_values($uniqueRole);
            $splitArrPermission = RoleServices::splitPermission($permission);

            $data['data']['user'] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'role' => $user->getRole(),
                'active' => $user->getActive(),
                // 'accessibleData' => $user->getAccessibleData(),
                'permission' => $permission,
                'splitArrPermission' => $splitArrPermission,
                'rolePermission' => $rolePermission,
            ];

            $data['data']['roles'] = $roles;

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/profile", name="api_user_profile", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
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
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
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
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
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

    // Trả ra dữ liệu
    public function listingResponse($item)
    {
        $activeValue = $item->getActive() ? "Active" : "Inactive";
        $json[] = [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'username' => $item->getUsername(),
            'email' => $item->getEmail(),
            'active' => $activeValue,
            // 'permission' => json_decode($item->getPermission()),
            // 'role' => $item->getRole()?->getName()
        ];

        return $json;
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

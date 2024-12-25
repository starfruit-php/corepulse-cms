<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Model\Role;
use CorepulseBundle\Services\SettingServices;
use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Services\PermissionServices;

/**
 * @Route("/app")
 */
class AppController extends BaseController
{
    /**
     * @Route("/get-option", name="corepulse_api_app_get_option", methods={"GET"})
     */
    public function getOption()
    {
        try {
            $data = SettingServices::getOptionData();

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/get-data-permission", name="corepulse_api_app_get_data_permission", methods={"GET"})
     */
    public function getDataPermission()
    {
        try {
            $data = ['defaultAdmin' => false, 'permissions' => PermissionServices::DEFAULT_DATA];
            $user = $this->getUser();

            if (!$user->getDefaultAdmin()) {
                $data['permissions'] = PermissionServices::getPermissionData($user);
            } else {
                $data['defaultAdmin'] = true;
            }

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }
}

<?php

namespace CorepulseBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\ClassDefinition;
use CorepulseBundle\Services\SettingServices;
use CorepulseBundle\Model\User;
use CorepulseBundle\Services\UserServices;
use CorepulseBundle\Services\ClassServices;
use Pimcore\Db;

/**
 * @Route("/setting")
 */
class SettingController extends BaseController
{
    /**
     * @Route("/object", name="corepulse_setting_object", methods={"GET","POST"})
     */
    public function objectLogin()
    {
        try {
            if ($this->request->isMethod(Request::METHOD_POST)) {
                $publish = $this->request->get('publish');
                $params = $this->request->get('params');

                $settingOld = SettingServices::getData('object');

                $settingNew = SettingServices::handleSettingNew($params, $publish, $settingOld);

                $update = SettingServices::updateConfig('object', $settingNew);

                self::save($settingNew);

                return $this->sendResponse(['success' => true, 'message' => 'Setting success']);
            }

            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $objectSetting = SettingServices::getData('object');
            $blackList = ["user", "role"];

            $data = SettingServices::getObjectSetting($blackList, $objectSetting['config']);

            $pagination = $this->paginator($data, $page, $limit);

            $result = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $classDefinition = ClassDefinition::getById($item['id']);
                $result['data'][] =  [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'title' => $classDefinition?->getTitle() ? $classDefinition->getTitle() : $item['name'],
                    'checked' => $item['checked'] == true ? 'publish' : 'unpublish',
                ];
            }

            return $this->sendResponse($result);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/appearance", name="corepulse_setting_appearance", methods={"GET","POST"})
     */
    public function settingAppearance()
    {
        try {
            if ($this->request->isMethod(Request::METHOD_POST)) {
                $condition = [
                    'params' => 'required',
                ];
                $messageError = $this->validator->validate($condition, $this->request);
                if($messageError) return $this->sendError($messageError);

                $params = json_decode($this->request->get('params'), true);

                $update = SettingServices::updateConfig('appearance', $params);

                return $this->sendResponse(['success' => true, 'message' => 'Setting success']);
            }

            $result = SettingServices::getData('appearance');

            return $this->sendResponse($result);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/user", name="corepulse_setting_user", methods={"GET","POST"})
     */
    public function settingUser()
    {
        try {
            if ($this->request->isMethod(Request::METHOD_POST)) {
                $condition = [
                    'id' => '',
                    'username' => 'required',
                    'password' => 'required',
                ];
                $messageError = $this->validator->validate($condition, $this->request);
                if($messageError) return $this->sendError($messageError);

                $params = [
                    'username' => $this->request->get('username'),
                    'password' => $this->request->get('password'),
                ];

                if ($id = $this->request->get('id')) {
                $user = User::getById($id);
                } else {
                    $user = new User;
                    $user->setDefaultAdmin(1);
                }

                $update = UserServices::settingEdit($params, $user);

                return $this->sendResponse(['success' => true, 'message' => 'Setting success']);
            }

            $user = new User\Listing();
            $user->addConditionParam('defaultAdmin = 1');
            $user = $user->current();

            $result = [
                'id' => $user?->getId(),
                'username' => $user?->getUserName(),
                'password' => '',
            ];

            return $this->sendResponse($result);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/edit-object", name="corepulse_setting_edit_object", methods={"POST"})
     */
    public function editObject(Request $request)
    {
        try {
            $condition = [
                'id' => 'required',
                'checked' => 'required',
                'name' => '',
                'title' => '',
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $title = $this->request->get('title');
            $settingOld = SettingServices::getData('object');
            $publish = $this->request->get('checked');
            $id = $this->request->get('id');
            $params = [$id];

            $settingNew = SettingServices::handleSettingNew($params, $publish, $settingOld);

            $update = SettingServices::updateConfig('object', $settingNew);

            $classDefinition = ClassDefinition::getById($id);
            if ($classDefinition && $title) {
                $classDefinition->setTitle($this->request->get('title'));
                $classDefinition->save();
            }

            return $this->sendResponse(['success' => true, 'message' => 'Setting success']);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    public static function save($dataSave)
    {
        foreach ($dataSave as $classId) {
            $params = ClassServices::examplesAction($classId);
            $update = ClassServices::updateTable($classId, $params);
        }

        return $dataSave;
    }
}

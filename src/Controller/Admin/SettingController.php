<?php

namespace CorepulseBundle\Controller\Admin;

use Pimcore\Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\ClassDefinition;
use CorepulseBundle\Services\SettingServices;
use CorepulseBundle\Model\User;

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
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $publish = $this->request->get('publish');
            $params = $this->request->get('params');

            $settingOld = SettingServices::getData('object');
            $settingNew = [];

            if (!empty($settingOld['config'])) {
                if ($publish == 'unpublish') {
                    $settingNew = array_diff($settingOld['config'], $params);
                } else if ($publish == 'publish') {
                    $convert = array_diff($params, $settingOld['config']);
                    $settingNew = array_merge($settingOld['config'], $convert);
                }

                $settingNew = array_values($settingNew);
            } else if ($publish == 'publish') {
                $settingNew = $params;
            }

            $update = SettingServices::updateConfig('object', $settingNew);

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
    }

    /**
     * @Route("/appearance", name="corepulse_setting_appearance", methods={"GET","POST"})
     */
    public function settingAppearance()
    {
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
    }

    /**
     * @Route("/user", name="corepulse_setting_user", methods={"GET","POST"})
     */
    public function settingUser()
    {
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $condition = [
                'params' => 'required',
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);



            return $this->sendResponse(['success' => true, 'message' => 'Setting success']);
        }

        $user = new User\Listing();
        $user->addConditionParam('defaultAdmin = 1');
        $user = $user->current();

        $result = [
            'username' => $user?->getUserName(),
            'password' => $user?->getPassword(),
        ];

        return $this->sendResponse($result);
    }
}

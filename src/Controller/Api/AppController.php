<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Db;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject;

/**
 * @Route("/app")
 */
class AppController extends BaseController
{

    /**
     * @Route("/setting", name="api_app_setting", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function settingAction( Request $request ): JsonResponse
    {
        try {
            $loginSetting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "login"', []);

            $data['data'] = [];

            if (!$loginSetting) {
                Db::get()->insert('corepulse_settings', [
                    'type' => 'login',
                ]);
                $loginSetting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "login"', []);
            }
            if ($loginSetting['config']) {
                $loginSetting['config'] = json_decode($loginSetting['config'], true);
            } else {
                $loginSetting['config'] = [];
            }

            $setting = $loginSetting['config'];

            $configSidebar = \Pimcore::getContainer()->getParameter('corepulse_admin.sidebar');

            $data['data'] = [
                'logo' => isset($setting['logo']) ? $setting['logo'] : '/bundles/corepulse/image/corepulse.png',
                'background' => isset($setting['background']) ? $setting['background'] : '/bundles/pimcoreadmin/img/login/pc11.svg',
                'colorPrimary' => isset($setting['color']) ? $setting['color'] : '#6a1b9a',
                'colorLight' => isset($setting['colorLight']) ? $setting['colorLight'] : '#f3e5f5',
                'title' => isset($setting['title']) ? $setting['title'] : 'Corepluse',
                'footer' => isset($setting['footer']) ? $setting['footer'] : '<p>From Starfruit With Love</p>',
                'configSidebar' => $configSidebar,
            ];

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }


    /**
     * @Route("/get-breadcrumb", name="api_app_get_breadcrumb", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getBreadcrumb( Request $request ): JsonResponse
    {
        try {
            $condition = [
                'type' => 'required',
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $type = $request->get('type');
            $id = $request->get('id');

            $parentInfo = '';
            $name = '';
            if ($type == "media") {
                $parentInfo = Asset::getById($id);
                $name = $parentInfo->getFileName();
            }
            if ($type == "object") {
                $parentInfo = DataObject::getById($id);
                $name = $parentInfo->getKey();
            }
            if ($type == "document") {
                $parentInfo = Document::getById($id);
                $name = $parentInfo->getKey();
            }

            if ($parentInfo && $name) {
                $pathParent = $parentInfo->getPath() . $parentInfo->getKey();
                $nameParent = explode('/', $pathParent);
                $result = array_filter($nameParent, function ($nameParent) {
                    return !empty($nameParent);
                });
                $breadcrumbs[] = [
                    'id' => 1,
                    'name' => 'Home',
                    'end' =>  false,
                ];
                $previousSubstring = '';
                foreach ($result as $key => $val) {
                    $idChill = '';
                    $substring = '';

                    if (strpos($parentInfo->getPath(), $val) !== false) {
                        $substring = $previousSubstring . '/' . $val;

                        $doc = '';
                        if ($type == "media") {
                            $doc = Asset::getByPath($substring);
                        }
                        if ($type == "object") {
                            $doc = DataObject::getByPath($substring);
                        }
                        if ($type == "document") {
                            $doc = Document::getByPath($substring);
                        }
                        if ($doc) {
                            $idChill = $doc->getId();
                        }
                    }
                    $breadcrumbs[] = [
                        'id' => $idChill,
                        'name' => $val,
                        'end' =>  $key == array_key_last($result),
                    ];
                    $previousSubstring = $substring;
                }

                $data['data'] = $breadcrumbs;

                return $this->sendResponse($data);
            }
            return $this->sendError("Item not found");


        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }


}

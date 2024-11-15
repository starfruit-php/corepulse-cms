<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use Starfruit\BuilderBundle\Sitemap\Setting;
use CorepulseBundle\Services\GoogleServices;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/sitemap")
 */
class SitemapController extends BaseController
{
    /**
     * @Route("/setting", name="api_sitemap_setting", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function setting()
    {
        try {
            if ($this->request->getMethod() == Request::METHOD_POST) {
                $keys = $this->request->get('keys');
                Setting::setKeys($keys);

                $pages = $this->request->get('pages');
                Setting::setPages($pages);

                // $settingDomain = 'builder:option-setting --main-domain=' . $request->getSchemeAndHttpHost();
                // $this->runProcess($settingDomain);

                $comand = 'builder:sitemap:generate';
                $this->runProcess($comand);
            }

            $settingClass = Setting::getKeys();
            $settingDocument = Setting::getPages();

            $data = [
                'classes' => $settingClass,
                'documents' => $settingDocument,
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

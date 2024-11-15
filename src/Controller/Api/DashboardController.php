<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Db;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use CorepulseBundle\Services\APIService;
use DateTime;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\User;

/**
 * @Route("/dashboard")
 */
class DashboardController extends BaseController
{
    /**
     * @Route("/default", name="api_dashboard_default", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function defaultAction( Request $request ): JsonResponse
    {
        try {
            $data['data'] = [];
            // get total
            $conditionQuery = 'id != 1 AND type != "folder"';
            $conditionParams = [];

            $list = new Asset\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $totalAsset = $list->count();

            $document = new \Pimcore\Model\Document\Listing();
            $document->setCondition($conditionQuery, $conditionParams);
            $totalDoc = $document->count();

            $object = new \Pimcore\Model\DataObject\Listing();
            $object->setCondition($conditionQuery, $conditionParams);
            $totalObject = $object->count();

            $users = new \Pimcore\Model\User\Listing();
            $users->setCondition('id != 0');
            $totalUser = $users->count();

            $data['data'] = [
                'totalAsset' => $totalAsset,
                'totalDoc' => $totalDoc,
                'totalObject' => $totalObject,
                'totalUser' => $totalUser,
            ];

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

}

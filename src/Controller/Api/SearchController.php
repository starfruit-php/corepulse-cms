<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Services\Helper\SearchHelper;
use Symfony\Component\HttpFoundation\Request;
use CorepulseBundle\Services\PermissionServices;

/**
 * @Route("/search")
 */
class SearchController extends BaseController
{
    /**
     * @Route("/history", name="corepulse_api_search_history", methods={"GET", "POST"})
     */
    public function history()
    {
        try {
            if ($this->request->isMethod(Request::METHOD_POST)) {
                $condition = [
                    'params' => 'required',
                ];
                $messageError = $this->validator->validate($condition, $this->request);
                if($messageError) return $this->sendError($messageError);

                $params = $this->request->get('params');

                dd($params);
            }

            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $listing = [];
            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/type", name="corepulse_api_search_type", methods={"GET", "POST"})
     */
    public function type()
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'search' => '',
                'type' => 'required|choice:dataObject,document,asset',
                'subType' => '',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $search = $this->request->get('search');
            $type = $this->request->get('type');
            $subType = $this->request->get('subType', $type);

            $listing = SearchHelper::getTree($subType, $search);
            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] = SearchHelper::getData($item, $type);
            }

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/tree-cascader", name="corepulse_api_search_tree_cascader", methods={"GET", "POST"})
     */
    public function treeCascader()
    {
        try {
            $condition = [
                'type' => 'required|choice:object,document,asset',
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $type = $this->request->get('type');
            $subType = null;

            $user = $this->getUser();
            if (!$user->getDefaultAdmin()) {
                $permissionData = PermissionServices::getPermissionData($user);
                dd($permissionData);    
            }

            $data = SearchHelper::getTreeCascader($type, subType: $subType);

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }
}

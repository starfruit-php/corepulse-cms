<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Model\Indexing;
use CorepulseBundle\Services\Helper\ArrayHelper;
use CorepulseBundle\Services\GoogleServices;
use Symfony\Component\HttpFoundation\Request;
use CorepulseBundle\Services\PermissionServices;

/**
 * @Route("/indexing")
 */
class IndexingController extends BaseController
{
    CONST TYPE_PERMISSION = 'indexing';

    /**
     * @Route("/listing", name="corepulse_api_indexing_listing", methods={"GET"})
     *
     * {mô tả api}
     */
    public function listing()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_LISTING);

            $orderByOptions = ['id', 'time', 'url', 'type', 'response'];
            $conditions = $this->getPaginationConditions($this->request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id is not NULL';
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
            
            $listing = new Indexing\Listing();
            $listing->setCondition($conditionQuery, $conditionParams);

            $orderKey = $this->request->get('order_by', 'updateAt');
            $order = $this->request->get('order', 'desc');

            $filter = $order && $orderKey ? ArrayHelper::sortArrayByField($listing->getData(), $orderKey, $order) : $listing->getData();

            $pagination = $this->paginator($filter, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] = $item->getDataJson();
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/status", name="corepulse_api_indexing_status", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function status()
    {
        try {
            $listing = new Indexing\Listing();
            $listing->setCondition('`result` is not null');

            $data = GoogleServices::filterIndexingStatus($listing);

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/setting", name="corepulse_api_indexing_setting", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function setting()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SETTING);

            if ($this->request->getMethod() == Request::METHOD_POST) {
                $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SAVE);

                $condition = [
                    'type' => 'required',
                    'value' => '',
                    'file' => 'file:maxSize,5M,mimeTypes,application/json',
                    'classes' => 'required',
                    'documents' => 'required',
                ];

                $messageError = $this->validator->validate($condition, $this->request);
                if($messageError) return $this->sendError($messageError);

                $params = [
                    'type' => $this->request->get('type'),
                    'value' => $this->request->get('value'),
                    'classes' => $this->request->get('classes'),
                    'documents' => $this->request->get('documents'),
                ];

                if ($params['type'] == 'file') {
                    $params['value'] = $this->request->files->get('file');
                }

                $response = GoogleServices::convertParams($params);

                return $this->sendResponse($response);
            }

            $data =  GoogleServices::getConfig();

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
    
    /**
     * @Route("/add", name="corepulse_api_indexing_add", methods={"POST"})
     */
    public function add()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_CREATE);

            $condition = [
                'url' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $url = $this->request->get('url');

            $data = GoogleServices::submitIndex([ 'type' => 'create', 'url' => $url ]);

            if (isset($data['indexing'])) {
                $data['data'] = $data['indexing']->getDataJson();
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_indexing_delete", methods={"GET", "POST"})
     */
    public function delete()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_DELETE);
    
            $condition = [
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $id = $this->request->get('id');

            $data = GoogleServices::deleteAction($id);

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/submit-type", name="corepulse_api_indexing_submit_type", methods={"POST"})
     *
     * {
     *    type: update-submit | delete-submit | inspection,
     *    id: string | array,
     * }
     */
    public function submitType()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SAVE);
            $condition = [
                'type' => 'required',
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $type = $this->request->get('type');
            $idsOrId = $this->request->get('id');
            
            $data = GoogleServices::submitType($idsOrId, $type);

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

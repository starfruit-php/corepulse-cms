<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Model\Indexing;
use CorepulseBundle\Services\Helper\ArrayHelper;
use CorepulseBundle\Services\GoogleServices;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/indexing")
 */
class IndexingController extends BaseController
{
    /**
     * @Route("/listing", name="api_indexing_listing", methods={"GET"})
     *
     * {mô tả api}
     */
    public function listing()
    {
        try {
            $orderByOptions = ['id', 'time', 'url', 'type', 'response'];
            $conditions = $this->getPaginationConditions($this->request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $listing = new Indexing\Listing();
            $listing->setCondition($conditionQuery, $conditionParams);
            $listing->load();

            $order_by = $this->request->get('order_by', 'updateAt');
            $order = $this->request->get('order', 'desc');
            $filter = ArrayHelper::sortArrayByField($listing->getData(), $order_by, $order);

            $pagination = $this->paginator($filter, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] =  $item->getDataJson();
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/status", name="api_indexing_status", methods={"GET", "POST"})
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
     * @Route("/setting", name="api_indexing_setting", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function setting()
    {
        try {
            if ($this->request->getMethod() == Request::METHOD_POST) {
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

                $response = GoogleServices::setConfig($params);

                return $this->sendResponse($response);
            }

            $data =  GoogleServices::getConfig();

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/submit-type", name="api_indexing_submit_type", methods={"POST"})
     *
     * {mô tả api}
     */
    public function submitType()
    {
        try {
            $condition = [
                'type' => 'required',
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $type = $this->request->get('type');
            $idsOrId = $this->request->get('id');

            if($type == 'create') {
                $data = GoogleServices::submitIndex([ 'type' => $type, 'url' => $idsOrId ]);
            } else {
                $data = GoogleServices::submitType($idsOrId, $type);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/status/detail", name="api_indexing_status_detail", methods={"GET", "POST"})
     */
    public function statusDetail()
    {
        try {
            $condition = [
                'id' => 'required|numeric',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);


            $indexing = Indexing::getById($this->request->get('id'));
            if ($indexing) {
                $data = $indexing->getDataJson();
            } else {
                $data = [
                    'id' => 'Indexing not found.'
                ];
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

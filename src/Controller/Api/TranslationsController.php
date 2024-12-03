<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\TranslationsServices;
use Pimcore\Db;
use Pimcore\Model\Translation;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/translations")
 */
class TranslationsController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_trans_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listingAction(): JsonResponse
    {
        try {
            $this->setLocaleRequest();

            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $conditionQuery = "`type` = 'simple'";
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

            $translation = new Translation();
            $tableName = $translation->getDao()->getDatabaseTableName();

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) {
                $orderKey = 'creationDate';
            }

            if (empty($order)) {
                $order = 'desc';
            }

            Translation::clearDependentCache();
            $translations = new Translation\Listing();
            $translations->setCondition($conditionQuery, $conditionParams);

            $translations->setOrder($order);
            $translations->setOrderKey($tableName . '.' . $orderKey, false);

            $pagination = $this->paginator($translations->load(), $page, $limit);
            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => [],
                'column' => array_merge(['key'], Tool::getValidLanguages()),
            ];

            foreach ($pagination as $item) {
                $data['data'][] = TranslationsServices::getData($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/create", name="corepulse_api_trans_create", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function create(): JsonResponse
    {
        try {
            $condition = [
                'key' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $key = $this->request->get('key');

            $translation = Translation::getByKey($key);
            if (!$translation instanceof Translation) {
                $create = TranslationsServices::create($key);
                return $this->sendResponse(['success' => true, 'message' => 'Create success']);
            }

            return $this->sendError('Translation already exists');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/update", name="corepulse_api_trans_update", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function updateAction(): JsonResponse
    {
        try {
            $condition = [
                'key' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $params = $this->request->request->all();

            $translation = Translation::getByKey($params['key']);
            if ($translation instanceof Translation) {
                $create = TranslationsServices::update($params);
                return $this->sendResponse(['success' => true, 'message' => 'Create success']);
            }

            return $this->sendError('Translation not found');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_trans_delete", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function deleteAction(): JsonResponse
    {
        try {
            $condition = [
                'key' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $keyOrKeys = $this->request->get('key');

            if (is_array($keyOrKeys)) {
                try {
                    foreach ($keyOrKeys as $item) {
                        $result = TranslationsServices::delete($item);
                    }
                    return $this->sendResponse([ 'success' => true, 'message' => "Delete items success" ]);
                } catch (\Throwable $th) {
                    return $this->sendError($th->getMessage(), 500);
                }
            } else {
                $result = TranslationsServices::delete($keyOrKeys);

                return $result ? $this->sendResponse([ 'success' => true, 'message' => "Delete item success" ]) : $this->sendError('Translation not found');
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

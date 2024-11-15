<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Controller\Api\BaseController;
use CorepulseBundle\Services\CustomerServices;
use Pimcore\Model\DataObject;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\Customer;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;

/**
 * @Route("/customer")
 */
class CustomerController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_customer_listing")
     */
    public function listing(Factory $factory)
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            $conditionQuery = 'id is NOT NULL ';
            $conditionParams = [];

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) $orderKey = 'key';
            if (empty($order)) $order = 'asc';

            if ($limit == -1) {
                $limit = 10000;
            }

            $listing = new Customer\Listing();
            $listing->setCondition($conditionQuery, $conditionParams);
            $listing->setOrderKey($orderKey);
            $listing->setOrder($order);
            $listing->setUnpublished(true);

            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] =  CustomerServices::getData($item, $factory);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="corepulse_api_customer_add", methods={"POST"})
     *
     * @throws \Exception
     */
    public function add()
    {
        try {
            $condition = [
                'key' => 'required',
                'parentId' => 'numeric',
                'folderName' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $folderName = $this->request->get('folderName');
            $parentId = $this->request->get('parentId') ? (int)$this->request->get('parentId') : 1;
            $key = $this->request->get('key');

            $parentItem =  DataObject::getById($parentId);
            $pathItem = $parentItem->getPath() . $key;

            $item =  DataObject::getByPath($pathItem);
            if (!$item) {
                $parent = '';

                if ($folderName) {
                    $parent = DataObject::getByPath("/" . $folderName) ?? DataObject\Service::createFolderByPath("/" . $folderName);
                }

                if (!$parent) {
                    $parent = DataObject::getById($parentId);
                }

                $func = '\\Pimcore\\Model\\DataObject\\Customer';

                $object = new $func();
                $object->setKey($key);
                $object->setParent($parent);
                $object->save();

                $data['data'] =  CustomerServices::getData($object);

                return $this->sendResponse($data);
            }

            return $this->sendError( $key . " already exists");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}

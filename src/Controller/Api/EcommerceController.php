<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\OnlineShopOrder;
use Pimcore\Model\DataObject;
use CorepulseBundle\Services\CustomerServices;
use CorepulseBundle\Services\EcommerceServices;
use Pimcore\Bundle\EcommerceFrameworkBundle\Factory;
use Pimcore\Bundle\EcommerceFrameworkBundle\EnvironmentInterface;

/**
 * @Route("/ecommerce")
 */
class EcommerceController extends BaseController
{
    const PAGE_DEFAULT = 1;
    const PERPAGE_DEFAULT = 10;

    /**
     * @Route("/summary", name="corepulse_api_ecommerce_summary", methods={"GET"})
     */
    public function summary(Factory $factory, EnvironmentInterface $environment)
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'customer' => 'required',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $customer = DataObject\Customer::getById($this->request->get('customer'));
            if (!$customer) return $this->sendError([ 'success' => false, 'message' => 'Customer not found.' ]);

            $environment->setCurrentUserId($customer->getId());
            $environment->save();

            $cartManager = $factory->getCartManager();
            $carts = $cartManager->getCartByName(name: 'cart');

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) $orderKey = 'creationDate';
            if (empty($order)) $order = 'desc';

            $listing = $factory->getOrderManager()->buildOrderList();
            $listing->setCondition('customer__id = ?', [$customer->getId()]);
            $listing->setOrderKey($orderKey);
            $listing->setOrder($order);
            $listing->setUnpublished(true);

            $totalPrice = 0;
            $lastOrder = [];
            foreach ($listing as $key => $item) {
                if ($key < 10) {
                    $lastOrder[] = EcommerceServices::getOrderData($item, true, true);
                }
                $totalPrice += $item->getTotalPrice();
            }

            // Bổ sung dữ liệu ở đây
            $data = [
                'totalCart' => $carts ? $carts->getItemCount() : 0,
                'totalPrice' => number_format($totalPrice, 0, ".", "."),
                'totalOrder' => $listing->count(),
                'customer' => CustomerServices::getData($customer),
                'lastOrder' => $lastOrder,
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/order/listing", name="corepulse_api_ecommerce_order_listing", methods={"GET"})
     */
    public function order(Factory $factory, EnvironmentInterface $environment)
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'customer' => 'numeric',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = '';
            $conditionParams = [];

            $customerId = $this->request->get('customer');
            if ($customerId) {
                $customer = DataObject\Customer::getById($customerId);
                if (!$customer) return $this->sendError([ 'success' => false, 'message' => 'Customer not found.' ]);
                $conditionQuery = 'customer__id = ?';
                $conditionParams = [$customer->getId()];
            }

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) $orderKey = 'creationDate';
            if (empty($order)) $order = 'desc';

            $listing = $factory->getOrderManager()->buildOrderList();
            $listing->setCondition($conditionQuery, $conditionParams);
            $listing->setOrderKey($orderKey);
            $listing->setOrder($order);
            $listing->setUnpublished(true);

            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach ($listing as $item) {
                $data['data'][] = EcommerceServices::getOrderData($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/order/detail", name="corepulse_api_ecommerce_order_detail", methods={"GET", "POST"})
     */
    public function detail()
    {
        try {

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

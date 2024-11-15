<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use Pimcore\Model\DataObject\OnlineShopOrder;
use Pimcore\Model\DataObject;
use CorepulseBundle\Services\Helper\ObjectHelper;

class EcommerceServices
{
    public static function create($params)
    {
        $order = new OnlineShopOrder();

        foreach ($params as $key => $value) {
            $setValue = 'set' . ucfirst($key);
            if (method_exists($order, $setValue)) {
                $order->$setValue($value);
            }
        }
        $order->setUpdateAt(date('Y-m-d H:i:s'));
        $order->setCreateAt(date('Y-m-d H:i:s'));
        $order->save();

        return $order;
    }

    public static function edit($params, $order)
    {
        foreach ($params as $key => $value) {
            $setValue = 'set' . ucfirst($key);
            if (method_exists($order, $setValue)) {
                $order->$setValue($value);
            }
        }
        $order->setUpdateAt(date('Y-m-d H:i:s'));
        $order->save();

        return $order;
    }

    public static function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                $order = OnlineShopOrder::getById($i);
                $order->delete();
            }
        } else {
            $order = OnlineShopOrder::getById($id);
            $order->delete();
        }

        return true;
    }

    static public function getOrderData($order, $customerInfo = false, $deliveryInfo = false)
    {
        $data = [];

        $detailParams = ["id", "key", "creationDate", "published", "modificationDate", "orderState", "orderdate", "ordernumber", "items", "totalPrice", "phone", "email", "name"];
        $customerParams = ["customerEmail", "customerFirstname", "customerLastname", "customerCompany", "customerStreet", "customerZip", "customerCity", "customerCountry"];
        $deliveryParams = ["deliveryFirstname", "deliveryLastname", "deliveryCompany", "deliveryStreet", "deliveryZip", "deliveryCity", "deliveryCountry"];

        // $params = array_merge($detailParams, $customerParams, $deliveryParams);
        foreach ($detailParams as $key => $value) {
            $data[$value] = ObjectHelper::getMethodData($order, $value);
        }

        if($customerInfo) {
            foreach ($customerParams as $key => $value) {
                $data['customerInfo'][$value] = ObjectHelper::getMethodData($order, $value);
            }
        }

        if($deliveryInfo) {
            foreach ($deliveryParams as $key => $value) {
                $data['customerDelivery'][$value] = ObjectHelper::getMethodData($order, $value);
            }
        }

        if(isset($data['items']) && $data['items']) $data['items'] = self::getItems($data['items']);
        if(isset($data['customer']) && $data['customer']) $data['customer'] = CustomerServices::getData($data['customer']);

        return $data;
    }

    static public function getItems($items)
    {
        $data = [];
        foreach ($items as $item) {
            $data[] = self::getItemData($item);
        }

        return $data;
    }

    static public function getItemData($item)
    {
        $data = [];
        $params = ["id", "orderState", "productNumber", "productName", "amount", "totalPrice"];
        foreach ($params as $key => $value) {
            $data[$value] = ObjectHelper::getMethodData($item, $value);
        }

        return $data;
    }
}

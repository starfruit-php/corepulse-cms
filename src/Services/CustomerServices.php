<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use Pimcore\Model\DataObject\OnlineShopOrder;
use Pimcore\Model\DataObject;
use CorepulseBundle\Services\Helper\ObjectHelper;

class CustomerServices
{
    static public function getData($customer, $factory = null)
    {
        $data = [];
        $params = ["id", "key", "creationDate", "modificationDate", "email", "phone", "published", "gender", "active", "fullName", "username", "firstname", "lastname", "city", "street", "zip", "countryCode", "customerLanguage"];
        foreach ($params as $key => $value) {
            $data[$value] = ObjectHelper::getMethodData($customer, $value);
        }

        $orderTotal = 0;
        $priceTotal = 0;
        if ($factory) {
            $listing = $factory->getOrderManager()->buildOrderList();
            $listing->setCondition('customer__id = ?', [$customer->getId()]);
            $listing->setUnpublished(true);

            foreach ($listing as $key => $value) {
                $orderTotal++;
                $priceTotal += $value->getTotalPrice();
            }

            $data = array_merge($data, [
                "orderTotal" => $orderTotal,
                "priceTotal" => $priceTotal,
            ]);
        }

        return $data;
    }
}

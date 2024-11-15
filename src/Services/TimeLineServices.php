<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use \CorepulseBundle\Model\TimeLine;

class TimeLineServices
{
    public static function create($params)
    {
        $timeline = new TimeLine();

        foreach ($params as $key => $value) {
            $setValue = 'set' . ucfirst($key);
            if (method_exists($timeline, $setValue)) {
                $timeline->$setValue($value);
            }
        }
        $timeline->setUpdateAt(date('Y-m-d H:i:s'));
        $timeline->setCreateAt(date('Y-m-d H:i:s'));
        $timeline->save();

        return $timeline;
    }

    public static function edit($params, $timeline)
    {
        foreach ($params as $key => $value) {
            $setValue = 'set' . ucfirst($key);
            if (method_exists($timeline, $setValue)) {
                $timeline->$setValue($value);
            }
        }
        $timeline->setUpdateAt(date('Y-m-d H:i:s'));
        $timeline->save();

        return $timeline;
    }

    public static function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                $timeline = TimeLine::getById($i);
                $timeline->delete();
            }
        } else {
            $timeline = TimeLine::getById($id);
            $timeline->delete();
        }

        return true;
    }

    static public function getListingByOrder($idOrIds = null)
    {
        $conditions = null;
        $params = [];
        if (is_array($idOrIds)) {
            $conditionsArray = [];
            foreach ($idOrIds as $id) {
                $conditionsArray[] = 'idOrder = ?';
                $params[] = $id;
            }

            $conditions = implode(' OR ', $conditionsArray);
        } else {
            $conditions = 'idOrder = ?';
            $params = [$idOrIds];
        }

        $listing = new TimeLine\Listing;
        $listing->setCondition($conditions, $params);
        $listing->setOrderKey('updateAt');
        $listing->setOrder('desc');
        $listing->setUnpublished(true);

        return $listing;
    }

    static public function getData($item)
    {
        $data = [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'idOrder' => $item->getOrderId(),
            'updateAt' => $item->getUpdateAt(),
            'createAt'=> $item->getCreateAt(),
        ];

        return $data;
    }
}

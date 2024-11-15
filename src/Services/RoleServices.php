<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Model\Role;
use Pimcore\Model\DataObject\Service as DataObjectService;

class RoleServices
{
    public static function create($key)
    {
        $role = new Role;
        $role->setKey(\Pimcore\Model\Element\Service::getValidKey($key . '-' . time(), 'object'));
        DataObjectService::createFolderByPath("/Role");
        $role->setParent(\Pimcore\Model\DataObject::getByPath("/Role"));
        // dd($params);
        // foreach ($params as $key => $value) {
        //     if ($key != 'permission') {
        //         $setValue = 'set' . ucfirst($key);
        //         if (method_exists($role, $setValue)) {
        //             $role->$setValue($value);
        //         }
        //     } else {
        //         $role->setPermission(json_encode($value));
        //     }
        // }
        $role->setName($key);
        $role->setPublished(true);

        return $role;
    }

    public static function edit($params, $role)
    {
        foreach ($params as $key => $value) {
            if ($key != 'permission') {
                $setValue = 'set' . ucfirst($key);
                if (method_exists($role, $setValue)) {
                    $role->$setValue($value);
                }
            } else {
                $permission = explode(",", $value);
                $role->setPermission(json_encode($permission));
            }
        }
        $role->save();

        return $role;
    }

    public static function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                $role = Role::getById($i);
                $role->delete();
            }
        } else {
            $role = Role::getById($id);
            $role->delete();
        }

        return true;
    }

    public static function splitPermission($permission)
    {
        $doc = [];
        $obj = [];
        $assets = [];
        if ($permission) {
            foreach ($permission as $value) {
                if (strstr($value, 'homeDocument')) {
                    array_push($doc, $value);
                }
                if (strstr($value, 'assets')) {
                    array_push($assets, $value);
                }
                if (strstr($value, 'Object')) {
                    array_push($obj, $value);
                }
            }
        }
        $splitArrPermission['document'] = $doc;
        $splitArrPermission['object'] = $obj;
        $splitArrPermission['assets'] = $assets;

        return $splitArrPermission;
    }
}

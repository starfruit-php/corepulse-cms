<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Model\Role;

class RoleServices
{
    public static function create($params)
    {
        $role = new Role;
        foreach ($params as $key => $value) {
            if ($key != 'permission') {
                $setValue = 'set' . ucfirst($key);
                if (method_exists($role, $setValue)) {
                    $role->$setValue($value);
                }
            } else {
                $role->setPermission(json_encode($value));
            }
        }
        $role->save();

        return $role;
    }

    public static function edit($params, $role)
    {
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'permission':
                    $role->setPermission(json_encode($value));
                    break;
                default:
                    $setValue = 'set' . ucfirst($key);
                    if (method_exists($role, $setValue)) {
                        $role->$setValue($value);
                    }
                    break;
            }
        }
        
        $role->save();

        return $role;
    }

    public static function handleParams($params = [])
    {
        $data = [];
        if (isset($params['name'])) {
            $data = [
                'name' => $params['name'],
            ];
        } else {
            $dataPermissions = PermissionServices::convertPermission($params);
    
            $setting = isset($params['setting']) ? json_decode($params['setting'], true) : [];
    
            $data = array_merge($setting, ['permission' => $dataPermissions]);
        }

        return $data;
    }

    public static function getJson($item)
    {
        $data = [
            'id' => $item->getId(),
            'name' => $item->getName(),
        ];

        return $data;
    }
}

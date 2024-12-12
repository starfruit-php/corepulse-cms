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
            $configPermissions = ['documents', 'assets', 'objects', 'other'];
            $dataPermissions = [
                'documents' => [], 
                'assets' => [], 
                'objects' => [], 
                'other' => [],
            ];
    
            foreach ($configPermissions as $item) {
                if (isset($params[$item])) {
                    $valueItem = $params[$item];
                    $valueItem = json_decode($valueItem, true);
    
                    if ($valueItem) {
                        $valueItem = array_map(function ($convert, $index) {
                            if (is_array($convert)) {
                                $convert['id'] = (int)$index + 1;
                            }
                            
                            return $convert;
                        }, $valueItem, array_keys($valueItem));
                        $dataPermissions[$item] = $valueItem;
                    }
                }
            }
    
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

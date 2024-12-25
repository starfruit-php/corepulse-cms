<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Model\Role;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject;
use Pimcore\Db;

class PermissionServices
{
    const DEFAULT_DATA = ['documents' => [], 'assets' => [], 'objects' => [], 'others' => []];
    const TYPE_OTHERS = 'others';
    const TYPE_DOCUMENTS = 'documents';
    const TYPE_ASSETS = 'assets';
    const TYPE_OBJECTS = 'objects';
    const ACTION_LISTING = 'listing';
    const ACTION_VIEW = 'view';
    const ACTION_SAVE = 'save';
    const ACTION_PUBLISH = 'publish';
    const ACTION_UNPUBLISH = 'ubpublish';
    const ACTION_DELETE = 'delete';
    const ACTION_RENAME = 'rename';
    const ACTION_CREATE = 'create';
    const ACTION_SETTING = 'setting';
    const ACTION_VERSIONS = 'version';

    public static function convertPermission($params)
    {
        $configPermissions = ['documents', 'assets', 'objects', 'others'];
        $dataPermissions = [
            'documents' => [],
            'assets' => [],
            'objects' => [],
            'others' => [],
        ];

        foreach ($configPermissions as $item) {
            if (isset($params[$item])) {
                $valueItem = $params[$item];
                $valueItem = json_decode($valueItem, true);

                if ($valueItem) {
                    //fillter
                    $valueItem = array_filter($valueItem, function ($item) {
                        return isset($item['path']) && $item['path'] !== '' && $item['path'] !== null;
                    });

                    // Re-index lại mảng sau khi lọc
                    $valueItem = array_values($valueItem);

                    // Gán ID cho các phần tử còn lại
                    $valueItem = array_map(function ($convert, $index) {
                        if (is_array($convert)) {
                            $convert['id'] = (int) $index + 1;
                        }
                        return $convert;
                    }, $valueItem, array_keys($valueItem));
                    $dataPermissions[$item] = $valueItem;
                }
            }
        }

        return $dataPermissions;
    }

    //get all permission (type: documents, assets, objects)
    static public function getPermissionsRecursively($permissionData, $type, $key)
    {
        if (empty($permissionData) || !isset($permissionData[$key])) {
            return [];
        }

        if (isset($permissionData[$key])) {
            return $permissionData[$key];
        }

        // Nếu type là objects, trả về mặc định (không cần kiểm tra thêm)
        if ($type === self::TYPE_OBJECTS) {
            return [];
        }

        $parentData = self::getParentId($type, $key);

        // Kiểm tra children nếu tồn tại
        if (!empty($parentData['childrenId']) && $type !== self::TYPE_ASSETS) {
            foreach ($parentData['childrenId'] as $childKey) {
                if (isset($permissionData[$childKey])) {
                    return [];
                }
            }
        }

        if (!empty($parentData['parentId'])) {
            return self::getPermissionsRecursively($permissionData, $type, $parentData['parentId']);
        }

        return [];
    }

    static public function getParentId($type, $key)
    {
        $item = null;
        if ($type == self::TYPE_ASSETS) {
            $item = Asset::getById($key);
        } elseif ($type == self::TYPE_OBJECTS) {
            $item = DataObject::getById($key);
        } elseif ($type == self::TYPE_DOCUMENTS) {
            $item = Document::getById($key);
        }

        $parentId = null;
        if ($item) $parentId = $item?->getParentId() ?? null;

        // query children in parentId
        $query = "SELECT id FROM `" . $type . "` WHERE `parentId` = ?";
        $list = Db::get()->fetchAllAssociative($query, [$parentId]);
        $childrenId = array_column($list, 'id');
        
        return ['parentId' => $parentId, 'childrenId' => $childrenId];
    }

    public static function isValid($data, $type, $key, $action)
    {
        if (!isset($data[$type])) {
            return false;
        }

        // format column [path => permission]
        $permissionData = array_column($data[$type], null, 'path');
        if ($type && $key && $action) {
            if ($type === self::TYPE_OTHERS) {
                return self::findKeyPermission($permissionData, $key, $action);
            }

            $permission = self::getPermissionsRecursively($permissionData, $type, $key);

            return $permission && isset($permission[$action]) ? $permission[$action] : false;;
        }

        return false;
    }

    public static function findKeyPermission($permissions, $key, $action) {
        if (isset($permissions[$key])) {
            return $permissions[$key][$action] ?? false;
        }

        return false;
    }

    //get all permission roles and permission user
    public static function getPermissionData($user)
    {
        // Bắt đầu với dữ liệu mặc định
        $data = self::DEFAULT_DATA;

        // Nếu không phải admin mặc định, xử lý quyền
        if (!$user->getDefaultAdmin()) {
            $roles = $user->getRole() ? explode(',', $user->getRole()) : [];
            $rolesData = array_map(function ($roleId) {
                $role = Role::getById((int)$roleId);
                return $role && $role->getPermission()
                    ? json_decode($role->getPermission(), true)
                    : self::DEFAULT_DATA;
            }, $roles);

            // Lấy quyền từ user
            $userPermission = $user->getPermission() ? json_decode($user->getPermission(), true) : self::DEFAULT_DATA;

            // Hợp nhất dữ liệu roles và user
            $data = array_reduce($rolesData, function ($carry, $roleData) {
                foreach ($roleData as $category => $permissions) {
                    $carry[$category] = array_merge($carry[$category], $permissions);
                }
                return $carry;
            }, $data);

            // Hợp nhất quyền user vào dữ liệu
            foreach ($data as $category => $permissions) {
                $data[$category] = array_merge($permissions, $userPermission[$category] ?? []);
            }

            // Chuẩn hóa dữ liệu sau cùng
            $data = self::mergePermissions($data);
        }

        return $data;
    }


    // replace các giá trị trùng
    public static function mergePermissions($data)
    {
        $result = [];

        foreach ($data as $category => $items) {
            $result[$category] = array_values(array_reduce($items, function ($carry, $item) {
                $path = $item['path'];

                // Hợp nhất nếu đã tồn tại
                if (isset($carry[$path])) {
                    foreach ($item as $key => $value) {
                        $carry[$path][$key] = is_bool($value)
                            ? ($carry[$path][$key] || $value)
                            : $value;
                    }
                } else {
                    $carry[$path] = $item;
                }

                return $carry;
            }, []));
        }

        return $result;
    }
}

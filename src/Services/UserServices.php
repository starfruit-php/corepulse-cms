<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Model\Role;
use CorepulseBundle\Model\User;
use Pimcore\Model\DataObject\Service as DataObjectService;
use \Pimcore\Model\Asset;
use CorepulseBundle\Security\Hasher\CorepulseUserPasswordHasher;

class UserServices
{
    public static function create($params, $defaultAdmin = false)
    {
        $user = new User;
        foreach ($params as $key => $value) {
            if ($key != 'password') {
                $setValue = 'set' . ucfirst($key);
                if (method_exists($user, $setValue)) {
                    $user->$setValue($value);
                }
            } else {
                $user->setPassword(CorepulseUserPasswordHasher::getPasswordHash($params['username'], $params['password']));
            }
        }

        if ($defaultAdmin) {
            $user->setDefaultAdmin(1);
            $user->setActive(1);
        }

        $user->save();

        return $user;
    }

    public static function settingEdit($params, $user)
    {
        $user->setActive(1);
        $user->setPassword(CorepulseUserPasswordHasher::getPasswordHash($params['username'], $params['password']));
        $user->setUsername($params['username']);
        $user->save();

        return $user;
    }

    public static function edit($params, $user)
    {
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'password':
                    if ($value) {
                        $user->setPassword(CorepulseUserPasswordHasher::getPasswordHash(isset($params['username']) ? $params['username'] : $user->getUsername(), $params['password']));
                    }
                    break;
                case 'permission':
                    $user->setPermission(json_encode($value));
                    break;
                case 'role':
                    $roles = implode(',', $value);
                    $user->setRole($roles);
                    break;
                default:
                    $setValue = 'set' . ucfirst($key);
                    if (method_exists($user, $setValue)) {
                        $user->$setValue($value);
                    }
                    break;
            }
        }
        
        $user->save();

        return $user;
    }

    public static function getJson($item)
    {
        $activeValue = $item->getActive() ? "Active" : "Inactive";
        $data = [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'username' => $item->getUsername(),
            'email' => $item->getEmail(),
            'active' => $activeValue,
            // 'permission' => json_decode($item->getPermission()),
            // 'role' => $item->getRole()?->getName()
        ];

        return $data;
    }

    public static function handleParams($params = [])
    {
        $data = [];
        if (isset($params['username'])) {
            $data = [
                'username' => $params['username'],
            ];
        } else {
            $dataPermissions = PermissionServices::convertPermission($params);
    
            $setting = isset($params['setting']) ? json_decode($params['setting'], true) : [];
    
            $data = array_merge($setting, ['permission' => $dataPermissions]);
        }

        return $data;
    }

    public static function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                $user = User::getById($i);
                $user->delete();
            }
        } else {
            $user = User::getById($id);
            $user->delete();
        }
        return true;
    }

    public static function saveAvatar($avatar, $user)
    {
        $newAsset = new \Pimcore\Model\Asset();
        $filename = time() . '-' . $avatar->getClientOriginalName();

        // convent filename
        $filename = preg_replace('/[^a-zA-Z0-9.]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        $newAsset->setFilename($filename);

        $avatarFolder = Asset::getByPath("/Avatar_" . $user->getId()) ?? Asset\Service::createFolderByPath("/Avatar_" . $user->getId());
        $newAsset->setParent($avatarFolder);
        $newAsset->setData(file_get_contents($avatar));
        $newAsset->save();
        $image = Asset\Image::getById($newAsset->getId());

        return $image;
    }

    public static function editProfile($params, $request, $user)
    {
        $avatar = $request->get('avatar');
        if ($avatar) {
            $image = Asset::getByPath($avatar);
            if ($image) {
                $user->setAvatar($image);
            }
        }
        if (property_exists($params, 'name')) {
            $user->setName($params->name);
        }

        $oldPassword = '';
        if (property_exists($params, 'oldPassword')) {
            $oldPassword = $params->oldPassword;
            $oldPassword = md5($user->getUsername() . ':corepulse:' . $oldPassword);
        }
        if (property_exists($params, 'password') && password_verify($oldPassword, $user->getPassword())) {
            $user->setPassword(CorepulseUserPasswordHasher::getPasswordHash($user->getUsername(), $params->password));
        }

        $user->save();

        return $user;
    }
}

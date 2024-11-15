<?php

namespace CorepulseBundle\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CorepulseBundle\Model\User;

class Token
{
    const KEY_JWT = 'corepulse';

    public static function verifyToken($token)
    {
        try {
            $key = self::KEY_JWT;
            return (array) JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function generateToken($user)
    {
        if ($user instanceof User) {
            $token = $user->getAuthToken();

            if ($token) {
                $verify = self::verifyToken($token);
                if (!empty($verify)) {
                    if ($verify['sub'] == $user->getId() && $verify['exp'] > time()) {
                        return true;
                    }
                }
            }

            $key = self::KEY_JWT;
            $now = time();

            $payload = [
                'sub' => $user->getId(),
                'rol' => 'login',
                'iat' => $now,
                'exp' => $now + (60 * 60 * 24)
            ];

            $token = JWT::encode($payload, $key, 'HS256');

            $user->setAuthToken($token);
            $user->save();
            return true;
        }

        return false;
    }
}

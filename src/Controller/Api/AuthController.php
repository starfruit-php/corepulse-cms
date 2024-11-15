<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Db;
use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Model\User;
use CorepulseBundle\Security\Hasher\CorepulseUserPasswordHasher;
use CorepulseBundle\Security\Token;

/**
 * @Route("/auth")
 */
class AuthController extends BaseController
{
    /**
     * @Route("/login", name="api_auth_login", methods={"POST"})
     *
     * {mô tả api}
     */
    public function loginAction()
    {
        $messageError = $this->validator->validate([
            'username' => 'required',
            'password' => 'required'
        ], $this->request);

        if ($messageError) return $this->sendError($messageError);

        $username = $this->request->get('username');
        $password = $this->request->get('password');
        $user = User::getByUsername($this->request->get('username'));

        $valid = $user && $user->getActive() && password_verify(md5($username . ':corepulse:' . $password), $user->getPassword());
        
        if (!$valid) {
            return $this->sendError('Login failed (1)!');
        }

        $generate = Token::generateToken($user);

        if ($generate) {
            $authToken = $user->getAuthToken();
            return $this->sendResponse(compact('authToken'));
        }

        return $this->sendError('Login failed (2)!');
    }

    /**
     * @Route("/logout", name="api_auth_logout", methods={"POST"})
     *
     * {mô tả api}
     */
    public function logoutAction()
    {
        $this->getUser()->setAuthToken(null);
        $this->getUser()->save();
        return $this->sendResponse();
    }
}

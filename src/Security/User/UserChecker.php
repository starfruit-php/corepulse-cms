<?php

namespace CorepulseBundle\Security\User;

use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user->getActive()) {
            throw new CustomUserMessageAccountStatusException('Your user account has been locked.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user->getActive()) {
            throw new AccountExpiredException('Your user account has been locked.');
        }
    }
}

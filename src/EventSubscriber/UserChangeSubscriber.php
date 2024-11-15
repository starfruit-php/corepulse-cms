<?php
// UserChangeListener.php
namespace CorepulseBundle\EventSubscriber;

use CorepulseBundle\Model\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserChangeSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $token = $this->tokenStorage->getToken();

        if ($token && $token->getUser() instanceof User) {
            $currentUser = $token->getUser();

            if (!$currentUser->isEqualTo($this->getFreshUserFromDatabase($currentUser))) {
                $this->tokenStorage->setToken(null);
            }
        }
    }

    private function getFreshUserFromDatabase(User $user)
    {
        $user = User::getById($user->getId());

        return $user;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}

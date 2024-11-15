<?php

namespace CorepulseBundle\Services;

use Pimcore\Model;
use CorepulseBundle\Model\User;
use CorepulseBundle\Model\Notification;
use CorepulseBundle\Model\Role;

class NotificationServices
{
    private User $user;

    /**
     * NotificationService constructor.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    private static function isValid(User $user)
    {
        return true;
    }

    public function send(int $toUser, int $fromUser, string $title, string $message, ?Model\ModelInterface $action = null)
    {
        $sender = User::getById($fromUser);
        $user = User::getById($toUser);

        if (!self::isValid($sender)) {
            throw new \UnexpectedValueException(sprintf('Access denied.'));
        }

        if (!$user instanceof User) {
            throw new \UnexpectedValueException(sprintf('No user found with the ID %d', $toUser));
        }

        if (empty($title)) {
            throw new \UnexpectedValueException('Title of the Notification cannot be empty');
        }

        if (empty($message)) {
            throw new \UnexpectedValueException('Message text of the Notification cannot be empty');
        }

        $notification = new Notification();
        $notification->setUser($toUser);
        $notification->setSender($fromUser);
        $notification->setTitle($title);
        $notification->setMessage($message);

        if ($actionType = self::getElementType($action)) {
            $notification->setAction($action->getId());
            $notification->setActionType($actionType);
        }

        $notification->save();
    }

    private static function getElementType(Model\ModelInterface $element): ?string
    {
        return match (true) {
            $element instanceof Model\Asset => 'asset',
            $element instanceof Model\Document => 'document',
            $element instanceof Model\DataObject\AbstractObject => 'object',
            default => null,
        };
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function sendGroup(int $groupId, int $fromUser, string $title, string $message, ?Model\ModelInterface $action = null): void {
        $group = Role::getById($groupId);

        if (!$group instanceof Role) {
            throw new \UnexpectedValueException(sprintf('No group found with the ID %d', $groupId));
        }

        $listing = new User\Listing();
        $listing->setCondition(
            'id != ?
            AND active = ?
            AND (
                roles = ?
                OR roles LIKE ?
                OR roles LIKE ?
                OR roles LIKE ?
            )',
            [
                $fromUser,
                1,
                $groupId,
                '%,' . $groupId,
                $groupId . ',%',
                '%,' . $groupId . ',%',
            ]
        );
        $listing->setOrderKey('name');
        $listing->setOrder('ASC');
        $listing->load();

        $users = $listing->getUsers();

        foreach ($users as $user) {
            $this->send($user->getId(), $fromUser, $title, $message, $action);
        }
    }
}

<?php
// src/Model/User.php

namespace CorepulseBundle\Model;

use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;

/**
 * Our custom user class implementing Symfony's UserInterface.
 */
class Role extends AbstractModel
{

    public ?int $id = null;

    public ?string $name = null;

    public ?string $permission = null;

    public function getClass()
    {
        return 'User'; // You can customize this to return the actual class name or perform other logic.
    }

    /**
     * Trigger the hash calculation to remove the plain text password from the instance. This
     * is necessary to make sure no plain text passwords are serialized.
     *
     * {@inheritdoc}
     */
    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->username; // Replace with the actual property used as the username
    }

    public static function getById(int $id): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getById($id);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("User with id $id not found");
        }

        return null;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }


    public function setPermission(?string $permission): void
    {
        $this->permission = $permission;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }
}

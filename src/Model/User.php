<?php
// src/Model/User.php

namespace CorepulseBundle\Model;

use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Our custom user class implementing Symfony's UserInterface.
 */
class User extends AbstractModel implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public ?int $id = null;

    public ?string $username = null;

    public ?string $password = null;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $avatar = null;

    public ?string $permission = null;

    public ?int $defaultAdmin = null;

    public ?int $admin = null;

    public ?int $active = null;

    public ?string $role = null;

    public ?array $roles = [];

    public ?string $authToken = null;

    public function getClass()
    {
        return 'CorepulseUser'; // You can customize this to return the actual class name or perform other logic.
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->defaultAdmin !== $user->getDefaultAdmin()) {
            return false;
        }

        if (!empty(array_diff($this->roles, $user->getRoles())) || !empty(array_diff($user->getRoles(), $this->roles))) {
            return false;
        }

        return true;
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

    public function getRoles(): array
    {

        $role = ['ROLE_COREPULSE_USER'];

        $permissions = ['ROLE_PERMISSIONS_DASHBOARD'];
        // $permissions = [];

        $this->roles = array_merge($role, $permissions);

        return $this->roles;
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

    public static function getByUsername(string $username): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByUsername($username);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("User with username $username not found");
        }

        return null;
    }

    public static function getByAuthToken(string $authToken): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByAuthToken($authToken);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("User with authToken $authToken not found");
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

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setPassword(?string $password): void
    {
        // $this->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->password = $password;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setPermission(?string $permission): void
    {
        $this->permission = $permission;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function setDefaultAdmin(?int $defaultAdmin): void
    {
        $this->defaultAdmin = $defaultAdmin;
    }

    public function getDefaultAdmin(): ?int
    {
        return $this->defaultAdmin;
    }

    public function setAdmin(?int $admin): void
    {
        $this->admin = $admin;
    }

    public function getAdmin(): ?int
    {
        return $this->admin;
    }

    public function setActive(?int $active): void
    {
        $this->active = $active;
    }

    public function getActive(): ?int
    {
        return $this->active;
    }

    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setAuthToken(?string $authToken): void
    {
        $this->authToken = $authToken;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    public function getDataJson()
    {
    }
}

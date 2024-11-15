<?php

namespace CorepulseBundle\Model;

use Pimcore;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;
use Pimcore\Model\Element;

class Notification extends AbstractModel
{
    public ?int $id = null;

    public ?string $title = null;

    public ?string $description = null;

    public ?int $user = null;

    public ?int $sender = null;

    public ?string $action = null;

    public ?string $actionType = null;

    public ?int $active = null;

    public ?string $type = null;

    public ?string $createAt = null;

    public ?string $updateAt = null;

    public function getClass()
    {
        return 'CorepulseNotification';
    }

    /**
     * get score by id
     */
    public static function getById(int $id): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getById($id);
            return $obj;
        }
        catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("Notification with id $id not found");
        }

        return null;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setActionType(?string $actionType): void
    {
        $this->actionType =  $actionType;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setUser(?int $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?int
    {
        return $this->user;
    }

    public function setSender(?int $sender): void
    {
        $this->sender = $sender;
    }

    public function getSender(): ?int
    {
        return $this->sender;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCreateAt(string $createAt): void
    {
        $this->createAt = $createAt;
    }

    public function getCreateAt(): ?string
    {
        return $this->createAt;
    }

    public function setUpdateAt(string $updateAt): void
    {
        $this->updateAt = $updateAt;
    }

    public function getUpdateAt(): ?string
    {
        return $this->updateAt;
    }

    public function getDataJson(): array
    {
        $data = [
            'id' => $this->getId(),
            'active' => $this->getActive(),
            'action' => $this->getAction(),
            'actionType' => $this->getActionType(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'user' => $this->getUser(),
            'sender' => $this->getSender(),
            'createAt' => $this->getCreateAt(),
            'updateAt' => $this->getUpdateAt(),
        ];

        return $data;
    }
}

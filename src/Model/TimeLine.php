<?php

namespace CorepulseBundle\Model;

use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;

class TimeLine extends AbstractModel
{
    public ?int $id = null;

    public ?int $orderId = null;

    public ?string $title = null;

    public ?string $description = null;

    public ?string $updateAt = null;

    public ?string $createAt = null;

    public function getClass()
    {
        return 'corepulse_order_timeline';
    }

    public static function getById(int $id): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getById($id);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("Vote with id $id not found");
        }

        return null;
    }

    public function setOrderId(?int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
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

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }


    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}

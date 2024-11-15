<?php

namespace CorepulseBundle\Model;

use Pimcore;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;
use Pimcore\Model\Element;

class Indexing extends AbstractModel
{
    public ?int $id = null;

    public ?string $url = null;

    public ?string $type = null;

    public ?string $time = null;

    public ?string $response = null;

    public ?string $updateAt = null;

    public ?string $createAt = null;

    public ?string $internalType = null;

    public ?string $internalValue = null;

    public ?string $status = null;

    public ?string $result = null;

    public ?string $language = null;

    public function getClass()
    {
        return 'CorepulseIndexing';
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

    public static function getByUrl(string $url): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByUrl($url);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("indexing with url $url not found");
        }

        return null;
    }

    public static function getByType(string $type): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByType($type);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("indexing with type $type not found");
        }

        return null;
    }

    public static function getByResponse(string $response): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByType($response);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("indexing with response $response not found");
        }

        return null;
    }

    public static function getByInternalValue(string $internalValue): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByInternalValue($internalValue);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("indexing with type $internalValue not found");
        }

        return null;
    }

    public static function getByInternalType(string $internalType): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getByInternalType($internalType);
            return $obj;
        } catch (NotFoundException $ex) {
            \Pimcore\Logger::warn("indexing with response $internalType not found");
        }

        return null;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setTime(?string $time): void
    {
        $this->time = $time;
    }

    public function getTime(): ?string
    {
        return $this->time;
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

    public function setInternalType(string $internalType): void
    {
        $this->internalType = $internalType;
    }

    public function getInternalType(): ?string
    {
        return $this->internalType;
    }

    public function setInternalValue(string $internalValue): void
    {
        $this->internalValue = $internalValue;
    }

    public function getInternalValue(): ?string
    {
        return $this->internalValue;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getDataJson(): array
    {
        $data = [
            'id' => $this->getId(),
            'url' => $this->getUrl(),
            'time' => $this->getTime() ? $this->getTime() : $this->getUpdateAt(),
            'type' => $this->getType(),
            'response' => $this->getResponse(),
            'createAt' => $this->getCreateAt(),
            'updateAt' => $this->getUpdateAt(),
            'internalType' => $this->getInternalType(),
            'internalValue' => $this->getInternalValue(),
            'status' => $this->getStatus(),
            'result' => json_decode($this->getResult(), true),
        ];

        $indexStatusResult = [];
        if (isset($data["result"]["indexStatusResult"])) {
            $indexStatusResult = $data["result"]["indexStatusResult"];
        }

        $data = array_merge($data, $indexStatusResult);

        return $data;
    }
}

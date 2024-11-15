<?php

namespace CorepulseBundle\Model\Indexing;

use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Exception\NotFoundException;

class Dao extends AbstractDao
{
    protected string $tableName = 'corepulse_indexing';

    /**
     * get indexing by id
     *
     * @throws \Exception
     */
    public function getById(?int $id = null): void
    {
        if ($id !== null) {
            $this->model->setId($id);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE id = ?', [$this->model->getId()]);

        if (!$data) {
            throw new NotFoundException("Object with the ID " . $this->model->getId() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get indexing by url
     *
     * @throws \Exception
     */
    public function getByUrl(?string $url = null): void
    {
        if ($url !== null) {
            $this->model->setUrl($url);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE url LIKE ?', ['%' . $this->model->getUrl() . '%']);

        if (!$data) {
            throw new NotFoundException("Object with the Url " . $this->model->getUrl() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get indexing by type
     *
     * @throws \Exception
     */
    public function getByType(?string $type = null): void
    {
        if ($type !== null) {
            $this->model->setType($type);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE type LIKE ?', ['%' . $this->model->getType() . '%']);

        if (!$data) {
            throw new NotFoundException("Object with the type " . $this->model->getType() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get indexing by url
     *
     * @throws \Exception
     */
    public function getByResponse(?string $response = null): void
    {
        if ($response !== null) {
            $this->model->setResponse($response);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE response LIKE ?', ['%' . $this->model->getResponse() . '%']);

        if (!$data) {
            throw new NotFoundException("Object with the Response " . $this->model->getResponse() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get indexing by internalValue
     *
     * @throws \Exception
     */
    public function getByInternalValue(?string $internalValue = null): void
    {
        if ($internalValue !== null) {
            $this->model->setInternalValue($internalValue);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE internalValue = ?', [$this->model->getInternalValue()]);

        if (!$data) {
            throw new NotFoundException("Object with the InternalValue " . $this->model->getInternalValue() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get indexing by internalValue
     *
     * @throws \Exception
     */
    public function getByInternalType(?string $internalType = null): void
    {
        if ($internalType !== null) {
            $this->model->setInternalType($internalType);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE internalType = ?', [$this->model->getInternalType()]);

        if (!$data) {
            throw new NotFoundException("Object with the InternalType " . $this->model->getInternalType() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * save indexing
     */
    public function save(): void
    {
        $vars = get_object_vars($this->model);

        $buffer = [];

        $validColumns = $this->getValidTableColumns($this->tableName);

        if (count($vars)) {
            foreach ($vars as $k => $v) {
                if (!in_array($k, $validColumns)) {
                    continue;
                }

                $getter = "get" . ucfirst($k);

                if (!is_callable([$this->model, $getter])) {
                    continue;
                }

                $value = $this->model->$getter();

                if (is_bool($value)) {
                    $value = (int) $value;
                }

                $buffer[$k] = $value;
            }
        }

        if ($this->model->getId() !== null) {
            $this->db->update($this->tableName, $buffer, ["id" => $this->model->getId()]);
            return;
        }

        $this->db->insert($this->tableName, $buffer);
        $this->model->setId($this->db->lastInsertId());
    }

    /**
     * delete indexing
     */
    public function delete(): void
    {
        $this->db->delete($this->tableName, ["id" => $this->model->getId()]);
    }
}

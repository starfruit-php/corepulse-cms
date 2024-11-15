<?php

namespace CorepulseBundle\Model\Notification;

use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Exception\NotFoundException;

class Dao extends AbstractDao
{
    protected string $tableName = 'corepulse_notification';

    /**
     * get notification by id
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
     * get notification by title
     *
     * @throws \Exception
     */
    public function getByTitle(?string $title = null): void
    {
        if ($title !== null) {
            $this->model->setTitle($title);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE title LIKE ?', ['%' . $this->model->getTitle() . '%']);

        if (!$data) {
            throw new NotFoundException("Object with the Title " . $this->model->getTitle() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * save notification
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
     * delete notification
     */
    public function delete(): void
    {
        $this->db->delete($this->tableName, ["id" => $this->model->getId()]);
    }
}

<?php

namespace CorepulseBundle\Model\Role;

use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Exception\NotFoundException;

class Dao extends AbstractDao
{
    protected string $tableName = 'corepulse_role';

    /**
     * get user by id
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
     * get user by id
     *
     * @throws \Exception
     */
    public function getByUsername(?string $username = null): void
    {
        if ($username !== null) {
            $this->model->setUsername($username);
        }

        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE username = ?', [$this->model->getUsername()]);

        if (!$data) {
            throw new NotFoundException("Object with the Username " . $this->model->getUsername() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * save user
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
     * delete user
     */
    public function delete(): void
    {
        $this->db->delete($this->tableName, ["id" => $this->model->getId()]);
    }
}

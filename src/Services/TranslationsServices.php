<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use Pimcore\Tool;

class TranslationsServices
{
    static public function create($key)
    {
        $result = null;
        foreach (Tool::getValidLanguages() as $language) {
            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->insert('translations_messages')
                ->setValue('`type`', ':type')
                ->setValue('`key`', ':key')
                ->setValue('`text`', ':text')
                ->setValue('`language`', ':language')
                ->setValue('`creationDate`', ':creationDate')
                ->setValue('`modificationDate`', ':modificationDate')
                ->setValue('`userOwner`', ':userOwner')
                ->setValue('`userModification`', ':userModification')
                ->setParameter('type', 'simple')
                ->setParameter('key', $key)
                ->setParameter('text', ' ')
                ->setParameter('language', $language)
                ->setParameter('creationDate', time())
                ->setParameter('modificationDate', time())
                ->setParameter('userOwner', 0)
                ->setParameter('userModification', 0);

            $result = $queryBuilder->execute();
        }

        return $result;
    }

    static public function update($params)
    {
        $result = [];
        $key = $params['key'];
        unset($params['key']);

        foreach ($params as $lang => $text) {
            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->update('translations_messages')
                ->set('`text`', ':text')
                ->where('`key` = :key')
                ->andWhere('`language` = :language')
                ->setParameter('text', $text)
                ->setParameter('key', $key)
                ->setParameter('language', $lang);

            $result[] = $queryBuilder->execute();
        }

        return $result;
    }

    static public function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $i) {
                $queryBuilder = Db::getConnection()->createQueryBuilder();
                $queryBuilder
                    ->delete('translations_messages')
                    ->where('`key` = :key')
                    ->setParameter('key', $i);
                $result = $queryBuilder->execute();
            }
        } else {
            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->delete('translations_messages')
                ->where('`key` = :key')
                ->setParameter('key', $id);
            $result = $queryBuilder->execute();
        }
        return $result;
    }

    // Trả ra dữ liệu
    static public function getData($item)
    {
        $data = array_merge(['key' => $item->getKey()], $item->getTranslations());

        foreach (Tool::getValidLanguages() as $language) {
            if (!isset($data[$language])) {
                $data[$language] = '';
            }
        }

        return $data;
    }
}

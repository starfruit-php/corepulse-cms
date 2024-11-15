<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;

class TranslationsServices
{
    static public function create($data, $key)
    {
        foreach ($data->languages as $lang => $text) {
            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->insert('translations_messages')
                ->setValue('`type`', 'type')
                ->setValue('`key`', ':key')
                ->setValue('`text`', ':text')
                ->setValue('`language`', ':language')
                ->setValue('`creationDate`', ':creationDate')
                ->setValue('`modificationDate`', ':modificationDate')
                ->setValue('`userOwner`', ':userOwner')
                ->setValue('`userModification`', ':userModification')
                ->setParameter('type', 'simple')
                ->setParameter('key', $key)
                ->setParameter('text', $text)
                ->setParameter('language', $lang)
                ->setParameter('creationDate', time())
                ->setParameter('modificationDate', time())
                ->setParameter('userOwner', 0)
                ->setParameter('userModification', 0);

            $result = $queryBuilder->execute();
        }
        return $result;
    }

    static public function edit($data, $key)
    {
        unset($data->id);
        foreach ($data as $lang => $text) {
            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->update('translations_messages')
                ->set('`text`', ':text')
                ->where('`key` = :key')
                ->andWhere('`language` = :language')
                ->setParameter('text', $text)
                ->setParameter('key', $key)
                ->setParameter('language', $lang);

            $result = $queryBuilder->execute();
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
}

<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Model\Translation;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Db;
use CorepulseBundle\Services\TranslationsServices;
use Pimcore\Tool;

/**
 * @Route("/translations")
 */
class TranslationsController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_trans_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listingAction()
    {
        try {
            $this->setLocaleRequest();

            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery =  "`type` = 'simple'";
            $conditionParams = [];

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $translations = new Translation\Listing();
            $translations->setCondition($conditionQuery, $conditionParams);

            $pagination = $this->paginator($translations->load(), $page, $limit);
            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => [],
                'column' => array_merge(['key'], Tool::getValidLanguages()),
            ];

            foreach ($pagination as $item) {
                $data['data'][] = $this->getData($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/create", name="corepulse_api_trans_create", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function createAction( Request $request ): JsonResponse
    {
        try {
            // $condition = [
            //     'key' => 'required',
            // ];

            // $errorMessages = $this->validator->validate($condition, $request);
            // if ($errorMessages) return $this->sendError($errorMessages);

            $data = $request->getContent(); // Lấy dữ liệu JSON từ raw
            $data = json_decode($data, true);

            $key = $data['key'];
            if (!$key) {
                return $this->sendError('key.is.not.null');
            }

            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from('corepulse_translations')
                ->where('`key` = :key')
                ->setParameter('key', $key);

            $item = $queryBuilder->executeQuery()->fetchAllAssociative();

            if (!$item) {
                $result = '';

                foreach ($data['languages'] as $lang => $text) {
                    $queryBuilder = Db::getConnection()->createQueryBuilder();
                    $queryBuilder
                        ->insert('corepulse_translations')
                        ->setValue('`type`', 'type')
                        ->setValue('`key`', ':key')
                        ->setValue('`text`', ':text')
                        ->setValue('`language`', ':language')
                        ->setValue('`creationDate`', ':creationDate')
                        ->setValue('`modificationDate`', ':modificationDate')

                        ->setParameter('type', 'simple')
                        ->setParameter('key', $key)
                        ->setParameter('text', $text)
                        ->setParameter('language', $lang)
                        ->setParameter('creationDate', time())
                        ->setParameter('modificationDate', time());

                    $result = $queryBuilder->execute();
                }

                if ($result) {
                    return $this->sendResponse("create.trans.success");
                } else {
                    return $this->sendError('create.trans.error');
                }
            }

            return $this->sendError('trans.already.exist');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="corepulse_api_trans_add", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function addAction( Request $request ): JsonResponse
    {
        try {
            $condition = [
                'key' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $key = $request->get('key');

            $queryBuilder = Db::getConnection()->createQueryBuilder();
            $queryBuilder
                ->select('*')
                ->from('corepulse_translations')
                ->where('`key` = :key')
                ->setParameter('key', $key);

            $item = $queryBuilder->executeQuery()->fetchAllAssociative();

            if (!$item) {
                $languages = ['en', 'vi'];

                $result = '';
                foreach ($languages as $language) {
                    $queryBuilder = Db::getConnection()->createQueryBuilder();
                    $queryBuilder
                        ->insert('corepulse_translations')
                        ->setValue('`type`', ':type')
                        ->setValue('`key`', ':key')
                        ->setValue('`text`', ':text')
                        ->setValue('`language`', ':language')
                        ->setValue('`creationDate`', ':creationDate')
                        ->setValue('`modifictionDate`', ':modifictionDate')

                        ->setParameter('type', 'simple')
                        ->setParameter('key', $key)
                        ->setParameter('text', '')
                        ->setParameter('language', $language)
                        ->setParameter('creationDate', time())
                        ->setParameter('modifictionDate', time());

                    $result = $queryBuilder->execute();
                }

                if ($result) {
                    return $this->sendResponse("add.trans.success");
                } else {
                    return $this->sendError('add.trans.error');
                }
            }
            return $this->sendError("Key already exists");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

     /**
     * @Route("/update", name="corepulse_api_trans_update", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function updateAction( Request $request ): JsonResponse
    {
        try {
            $data = $request->getContent(); // Lấy dữ liệu JSON từ raw
            $data = json_decode($data, true);

            $key = $data['key'];
            if (!$key) {
                return $this->sendError('key.is.not.null');
            }
            // $queryBuilder = Db::getConnection()->createQueryBuilder();
            // $queryBuilder
            //     ->select('*')
            //     ->from('translations_messages')
            //     ->where('`key` = :key')
            //     ->setParameter('key', $key);
            // $item =  $queryBuilder->execute()->fetchAll();

            // if ($item) {
                $result = '';

                unset($data['key']);
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

                return $this->sendResponse("update.trans.success");

            // }

            return $this->sendError('trans.not.found');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_trans_delete", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function deleteAction( Request $request ): JsonResponse
    {
        try {
            $condition = [
                'key' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('key');

            if (is_array($id)) {
                try {
                    foreach ($id as $item) {
                        $result = TranslationsServices::delete($item);
                    }
                } catch (\Throwable $th) {
                    return $this->sendError($th->getMessage(), 500);
                }
            } else {
                $result = TranslationsServices::delete($id);

                if ($result) {
                } else {
                    return $this->sendError('Can not find item to be deleted');
                }
            }

            return $this->sendResponse("Delete item success");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // Trả ra dữ liệu
    public function getData($item)
    {
        $data = array_merge(['key' => $item->getKey()], $item->getTranslations());

        return $data;
    }
}

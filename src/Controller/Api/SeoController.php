<?php

namespace CorepulseBundle\Controller\Api;

use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Db;
use Symfony\Component\HttpFoundation\Request;
use CorepulseBundle\Services\SeoServices;
use Pimcore\Bundle\SeoBundle\Model\Redirect;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject;
use Starfruit\BuilderBundle\Model\Seo;
use CorepulseBundle\Services\PermissionServices;
use CorepulseBundle\Services\Helper\ArrayHelper;

/**
 * @Route("/seo")
 */
class SeoController extends BaseController
{
    CONST TYPE_PERMISSION = '404-301';

    /**
     * @Route("/404/listing", name="corepulse_api_seo_monitor_listing", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function monitorListing()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_LISTING);

            $orderByOptions = ['code', 'uri', 'date', 'count', 'id'];
            $conditions = $this->getPaginationConditions($this->request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');

            if (!$orderKey) $orderKey = 'date';
            if (!$order) $order = 'desc';
            
            $conditionQuery = '';
            $conditionParams = [];

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');
            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' WHERE (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $db = Db::get();
            $listData = $db->fetchAllAssociative("SELECT id, code, uri, count, FROM_UNIXTIME(date, '%Y-%m-%d %h:%i') AS 'date' FROM http_error_log $conditionQuery ORDER BY $orderKey $order;", $conditionParams);

            $pagination = $this->paginator($listData, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] =  $item;
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/404/detail", name="corepulse_api_seo_monitor_detail", methods={"GET"})
     */
    public function monitorDetail()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_VIEW);

            $condition = [
                'id' => 'required|numeric',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $db = Db::get();
            $data = $db->fetchAssociative('SELECT * FROM http_error_log WHERE id = ?', [$this->request->get('id')]);

            foreach ($data as $key => &$value) {
                if (in_array($key, ['parametersGet', 'parametersPost', 'serverVars', 'cookies'])) {
                    $value = unserialize($value);
                }
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/404/truncate", name="corepulse_api_seo_monitor_truncate", methods={"POST"})
     */
    public function monitorTruncate()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_DELETE);

            $db = Db::get();
            $db->executeQuery('TRUNCATE TABLE http_error_log');

            $data = [
                'success' => true,
                'message' => "TRUNCATE success",
                'trans' => 'seo_http.success.truncate_success',
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/404/delete", name="corepulse_api_seo_monitor_delete", methods={"POST"})
     */
    public function monitorDelete()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_DELETE);

            $condition = [
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $idsOrId = $this->request->get('id');
            $db = Db::get();

            if (is_array($idsOrId)) {
                $conditions = [];
                $placeholders = [];
                $params = [];

                foreach ($idsOrId as $id) {
                    $conditions[] = 'FIND_IN_SET(?, id)';
                    $placeholders[] = '?';
                    $params[] = $id;
                }

                $where = '(' . implode(' OR ', $conditions) . ')';
                $placeholders = implode(', ', $placeholders);

                $query = "DELETE FROM http_error_log WHERE $where";
                $db->executeQuery($query, $params);
            } else {
                $db->executeQuery('DELETE FROM http_error_log WHERE id = ?', [$idsOrId]);
            }

            $data = [
                'success' => true,
                'message' => "Delete success",
                'trans' => 'seo_http.success.delete_success',
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/301/listing", name="corepulse_api_seo_redirect_listing", methods={"GET", "POST"})
     *
     * {mô tả api}
     */
    public function redirectListing()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_LISTING);

            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $orderKey = $this->request->get('order_by' );
            $order = $this->request->get('order');
            if(!$orderKey) $orderKey = 'creationDate';
            if(!$order) $order = 'asc';

            $conditionQuery = 'id is not null';
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

            $listing = new Redirect\Listing();
            $listing->setCondition($conditionQuery, $conditionParams);

            $filter = ArrayHelper::sortArrayByField($listing->getRedirects(), $orderKey, $order);

            $pagination = $this->paginator($listing->getRedirects(), $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                if ($link = $item->getTarget()) {
                    if (is_numeric($link)) {
                        if ($doc = Document::getById((int)$link)) {
                            $item->setTarget($doc->getRealFullPath());
                        }
                    }
                }

                $data['data'][] = $item->getObjectVars();
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/301/add", name="corepulse_api_seo_redirect_detail", methods={"POST"})
     */
    public function redirectCreate()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_CREATE);

            $condition = [
                'type' => 'required',
                'source' => '',
                'target' => '',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $data = [
                'type' => $this->request->get('type'),
                'source' => $this->request->get('source'),
                'target' => $this->request->get('target'),
            ];

            $redirect = new Redirect();

            $redirect = SeoServices::updateRedirect($redirect, $data);

            $data = [
                'success' => true,
                'message' => "create success",
                'trans' => 'seo_http.success.create_success',
                'data' => $redirect->getObjectVars(),
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/301/update", name="corepulse_api_seo_redirect_update", methods={"POST"})
     */
    public function redirectUpdate()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SAVE);

            $condition = [
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $active = $this->request->get('active');
            $data = [
                'id' => $this->request->get('id'),
                'type' => $this->request->get('type'),
                'source' => $this->request->get('source'),
                'target' => $this->request->get('target'),
                'active' => filter_var($active, FILTER_VALIDATE_BOOLEAN),
                'statusCode' => $this->request->get('statusCode'),
            ];

            $redirect = Redirect::getById($data['id']);

            if (!$redirect) {
                return $this->sendError([
                    'success' => false,
                    'message' => 'Redirect not found',
                    'trans' => 'seo_http.errors.redirect.not_found',
                ]);
            }

            $redirect = SeoServices::updateRedirect($redirect, $data);

            $data = [
                'success' => true,
                'message' => 'Update Redirect Success',
                'trans' => 'seo_http.success.redirect.update_success',
                'data' => $redirect->getObjectVars(),
            ];

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/301/delete", name="corepulse_api_seo_redirect_delete", methods={"POST"})
     */
    public function redirectDelete()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_DELETE);

            $condition = [
                'id' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $idsOrId = $this->request->get('id');

            $data = [
                'success' => true,
                'message' => 'Redirect delete success',
                'trans' => 'seo_http.success.redirect.delete_success',
            ];

            if (is_array($idsOrId)) {
                foreach ($idsOrId as $id) {
                    $redirect = Redirect::getById($id);
                    if (!$redirect) {
                        $data['error'][] = $id;
                        $data['success'] = false;
                        $data['message'] = 'Redirect not found';
                        $data['trans'] = 'seo_http.errors.redirect.not_found';
                    } else {
                        $redirect->delete();
                    }
                }
            } else {
                $redirect = Redirect::getById($idsOrId);
                if (!$redirect) {
                    return $this->sendError([
                        'success' => false,
                        'message' => 'Redirect not found',
                        'trans' => 'seo_http.errors.redirect.not_found',
                    ]);
                }
                $redirect->delete();
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/301/redirect-type", name="corepulse_api_seo_redirect_type")
     */
    public function redirectType()
    {
        $data = [
            [
                'label' => '301 Permanent Move',
                'key' => '301 Permanent Move',
                'value' => 301
            ],
            [
                'label' => '302 Temporary Move',
                'key' => '302 Temporary Move',
                'value' => 302
            ],
            [
                'label' => '307 Temporary Redirect',
                'key' => '307 Temporary Redirect',
                'value' => 307
            ],
            [
                'label' => '401 Content Deleted',
                'key' => '401 Content Deleted',
                'value' => 401
            ],
            [
                'label' => '451 Content Unavailable',
                'key' => '451 Content Unavailable',
                'value' => 451
            ]
        ];

        return $this->sendResponse($data);
    }

    /**
     * @Route("/301/redirect-type-option", name="corepulse_api_seo_redirect_type_option")
     */
    public function redirectTypeOption()
    {
        $data = [
            [
                'label' => 'Path: /foo',
                'key' => 'Path: /foo',
                'value' => 'path'
            ],
            [
                'label' => 'Auto create',
                'key' => 'Auto create',
                'value' => 'auto_create'
            ],
            [
                'label' => 'Path and Query: /foo?key=value',
                'key' => 'Path and Query: /foo?key=value',
                'value' => 'path_query'
            ],
            [
                'label' => 'Entire URI: https://host.com/foo?key=value',
                'key' => 'Entire URI: https://host.com/foo?key=value',
                'value' => 'entire_uri'
            ],
        ];

        return $this->sendResponse($data);
    }

    /**
     * @Route("/object/detail", name="corepulse_api_seo_object_detail", methods={"GET", "POST"})
     */
    public function objectDetail()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_VIEW);

            $language = $this->request->get('_locale');
            $object = DataObject::getById($this->request->get('id'));

            $seo = Seo::getOrCreate($object, $language);

            if(!$seo) return $this->sendError(['success' => false, 'message' => 'Object or seo config not found.']);

            if ($this->request->isMethod('POST')) {
                // $condition = [ 'saveMetaData' => 'required' ];
                // $messageError = $this->validator->validate($condition, $this->request);
                // if($messageError) return $this->sendError($messageError);

                 //lưu dữ liệu vào bảng seo
                if ($this->request->get('update')) {
                    $params = $this->request->request->all();
                    $seo = SeoServices::saveData($seo, $params);
                }

                if ($this->request->get('saveMetaData')) {
                    $params = [
                        'ogMeta' => $this->request->get('ogMeta') ? json_decode($this->request->get('ogMeta'), true) : [],
                        'twitterMeta' => $this->request->get('twitterMeta') ? json_decode($this->request->get('twitterMeta'), true) : [],
                    ];
                    $seo = SeoServices::saveMetaData($seo, $params);
                }
            }

            $metaData = $seo->getMetaDatas();

            // lấy danh sách dữ liệu seoscoring
            $scoring = $seo->getScoring(true);
            $settingAi = ['settingAi' => SeoServices::getApiKey()];
            $data = array_merge($scoring, $metaData, $settingAi);

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/object/meta-type", name="corepulse_api_seo_meta_type", methods={"GET"})
     */
    public function metaType()
    {
        $data = [
            'ogMeta' => [
                [
                    'key' => 'Meta Title',
                    'value' => 'og:title',
                ],
                [
                    'key' => 'Meta Description',
                    'value' => 'og:description',
                ],
                [
                    'key' => 'Meta Type',
                    'value' => 'og:type',
                ],
                [
                    'key' => 'Meta Image',
                    'value' => 'og:image',
                ],
                [
                    'key' => 'Meta Url',
                    'value' => 'og:url',
                ],
                [
                    'key' => 'Meta Image Alt',
                    'value' => 'og:image:alt',
                ],
            ],
            'twitterMeta' => [
                [
                    'key' => 'Twitter Title',
                    'value' => 'twitter:title',
                ],
                [
                    'value' => 'twitter:description',
                    'key' => 'Twitter Description',
                ],
                [
                    'key' => 'Twitter Card',
                    'value' => 'twitter:card',
                ],
                [
                    'key' => 'Twitter Site',
                    'value' => 'twitter:site',
                ],
                [
                    'key' => 'Twitter Image',
                    'value' => 'twitter:image',
                ],
                [
                    'key' => 'Twitter Image Alt',
                    'value' => 'twitter:image:alt',
                ],
            ],
        ];

        return $this->sendResponse($data);
    }

    /**
     * @Route("/object/setting-ai", name="corepulse_api_seo_setting_ai", methods={"POST"})
     */
    public function settingAi()
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SETTING);

            $condition = [ 'setting' => 'required' ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $data = [
                'success' => SeoServices::checkApi($this->request->get('setting'))
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/object/send-keyword", name="corepulse_api_seo_send_keyword", methods={"GET"})
     */
    public function sendKeyword(Request $request)
    {
        try {
            $this->validPermissionOrFail(PermissionServices::TYPE_OTHERS, self::TYPE_PERMISSION, PermissionServices::ACTION_SAVE);

            $condition = [
                'keyword' => 'required',
                '_locale' => 'required',
                'type' => 'required|choice:sematic,outline'
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $keyword = $request->get('keyword');
            $language = $request->get('_locale');
            $type = $request->get('type');

            $content =  SeoServices::choicesContent($keyword, $type, $language);

            $response = SeoServices::sendCompletions(content: $content);

            return $this->sendResponse($response);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}

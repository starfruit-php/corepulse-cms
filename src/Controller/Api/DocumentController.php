<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\DocumentServices;
use CorepulseBundle\Services\FieldServices;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use DateTime;
use Pimcore\Model\Document\DocType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pimcore\Model\Tool;

/**
 * @Route("/document")
 */
class DocumentController extends BaseController
{
    /**
     * @Route("/listing", name="api_document_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listingAction(
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $this->setLocaleRequest();

            $orderByOptions = ['index'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'order_by' => '',
                'order' => '',
                'folderId' => '',
                'type' => '',
                'filterRule' => '',
                'filter' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if ($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $id = $request->get('folderId') ? $request->get('folderId') : 1;
            if ($id) {
                if ($id == 1) {
                    $conditionQuery .= ' AND (parentId = :parentId OR parentId = :parentId2)';
                    $conditionParams['parentId'] = $id;
                    $conditionParams['parentId2'] = 0;
                } else {
                    $conditionQuery .= ' AND parentId = :parentId';
                    $conditionParams['parentId'] = $id;
                }
            }

            $type = $request->get('type') ? $request->get('type') : '';
            if ($type) {
                $conditionQuery .= ' AND type = :type';
                $conditionParams['type'] = $type;
            }

            $filterRule = $request->get('filterRule');
            $filter = $request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $orderBy = $request->get('order_by', 'index');
            $order = $request->get('order', 'asc');

            if (!$orderBy) {
                $orderBy = 'index';
            }

            $list = new Document\Listing();
            $list->setOrderKey($orderBy);
            $list->setOrder($order);
            $list->setCondition($conditionQuery, $conditionParams);

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'data' => []
                ],
                $paginationData,
            );

            foreach ($list as $item) {
                $checkName = strpos($item->getKey(), 'email');
                if ($checkName === false) {
                    $data['data'][] = self::listingResponse($item);
                }
            }

            usort($data['data'], function($a, $b) use ($orderBy, $order) {
                if ($order == 'asc') {
                    return $a[$orderBy] <=> $b[$orderBy];
                } else {
                    return $b[$orderBy] <=> $a[$orderBy];
                }
            });

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail", name="api_document_detail", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function detailAction(
        Request $request,
        PaginatorInterface $paginator
    ): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $request->get('id');
            $document = Document::getById($id);
            if ($document) {
                // $res = $this->renderView($document->getTemplate(), ['editmode' => true, 'document' => $document]);

                $data['data'] = [];
                if ($document->getType() != 'folder') {

                    $data['data'] = self::detailResponse($document);

                    $editTables = $document->getEditables();
                    foreach ($editTables as $key => $value) {
                        if ($value->getType() == 'input') {

                            dd ($value->getData());
                        }
                        $function = 'get'. ucwords($value->getType());
                        $data['data']['editTables'][] = [
                            'name' => $value->getName(),
                            'type' => $value->getType(),
                            'value' => FieldServices::{$function}($document, $value),
                        ];
                    }
                }
                return $this->sendResponse($data);
            }
            return $this->sendError("page.not.found");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="api_document_delete", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function deleteAction( Request $request ): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $ids = $request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $document = Document::getById((int) $id);
                    if ($document) {
                        $document->delete();
                    } else {
                        return $this->sendError('Can not find document to be deleted');
                    }
                }
            } else {
                $document = Document::getById((int) $ids);
                if ($document) {
                    $document->delete();
                } else {
                    return $this->sendError('Can not find document to be deleted');
                }
            }

            return $this->sendResponse("Delete page success");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="api_document_add", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function addAction( Request $request ): JsonResponse {
        try {
            $condition = [
                'title' => 'required',
                'type' => '',
                'key' => 'required',
                'folderId' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $title = $request->get('title');
            $folderId = $request->get('folderId');
            $parentId = $folderId  ? (int)$folderId : 1;

            $type = $request->get('type');
            $key = $request->get('key');

            $key = trim($key);
            if ($title) {
                $checkPage = Document::getByPath("/" . $title);
                if (!$checkPage) {
                    $page = DocumentServices::createDoc($key, $title, $type, $parentId);
                    if ($page){
                        $data['data'] = self::listingResponse($page);
                        return $this->sendResponse($data);
                    } else {
                        return $this->sendError('Create failed');
                    }
                } else {
                    return $this->sendError('Page "' . $title . '" already exists');
                }
            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // trả ra dữ liệu
    public function listingResponse($item)
    {
        $json = [];
        $publicURL = DocumentServices::getThumbnailPath($item);

        $draft = $this->checkLastest($item);
        if ($draft) {
            $status = 'Draft';
        } else {
            if ($item->getPublished()) {
                $status = 'Publish';
            } else {
                $status = 'Draft';
            }
        }

        $listChills = new \Pimcore\Model\Document\Listing();
        $listChills->setCondition("parentId = :parentId", ['parentId' => $item->getId()]);
        $chills = [];
        foreach ($listChills as $chill) {
            $chills[] = $chill;
        }

        $checkName = strpos($item->getKey(), 'email');
        if ($checkName === false && $item->getType() != "email") {
            $json = [
                'id' => $item->getId(),
                'key' =>  $item->getId() == 1 ? 'Home' : $item->getKey(),
                'image' => $publicURL,
                'type' => $item->getType(),
                'published' => $status,
                'createDate' => DocumentServices::getTimeAgo($item->getCreationDate()),
                'modificationDate' => DocumentServices::getTimeAgo($item->getModificationDate()),
                'parent' => $chills ? true : false,
                'index' => $item->getId() == 1 ? 0 : $item->getIndex(),
            ];
        }

        return $json;
    }


    // trả ra dữ liệu
    public function detailResponse($document)
    {
        $seoImage = null;
        $seo = \Starfruit\BuilderBundle\Model\Seo::getOrCreate($document);
        if ($seo) {
            if (method_exists($seo, 'getImageAsset')) {
                $idImage = $seo->getImageAsset();
                $asset = Asset::getById((int)$idImage);
                if ($asset) {
                    $seoImage = $asset->getFullPath();
                }
            }
        }

        // get document type
        $listDocType = new DocType\Listing();
        if ($type = $document->getType()) {
            if (!Document\Service::isValidType($type)) {
                throw new BadRequestHttpException('Invalid type: ' . $type);
            }
            $listDocType->setFilter(static function (DocType $docType) use ($type) {
                return $docType->getType() === $type;
            });
        }
        $docTypes = [];
        $lisDocType = [];
        foreach ($listDocType->getDocTypes() as $type) {
            $docTypes[] = [
                'id' => $type->getObjectVars()['id'],
                'name' => $type->getObjectVars()['name'],
            ];
            $lisDocType[] = $type->getObjectVars();
        }

        // nếu page là dạng email
        $listEmail = [];
        if ($document->getType() == "email") {
            $list = new Tool\Email\Log\Listing();
            $list->setCondition('documentId = ' . (int)$document->getId());
            $list->setLimit(50);
            $list->setOffset(0);
            $list->setOrderKey('sentDate');
            $list->setOrder('DESC');


            $data = $list->load();
            foreach($data as $item) {
                $type = 'text';
                if (($item->getEmailLogExistsHtml() == 1 && $item->getEmailLogExistsText() == 1) || ($item->getEmailLogExistsHtml() == 1 && $item->getEmailLogExistsText() != 1)) {
                    $type = 'html';
                }
                $listEmail[] = [
                    'id' => $item->getId(),
                    'from' => $item->getFrom(),
                    'to' => $item->getTo(),
                    'cc' => $item->getCc(),
                    'bcc' => $item->getBcc(),
                    'subject' => $item->getSubject(),
                    'error' => $item->getError(),
                    'bodyHtml' => $item->getBodyHtml(),
                    'bodyText' => $item->getBodyText(),
                    'sentDate' => date("M j, Y  H:i", $item->getSentDate()),
                    'params' => $item->getParams(),
                    'type' => $type,
                ];
            }
        }

        $href = '';
        // nếu page là dạng link
        if ($document->getType() == 'link') {
            $href = $document->getHref();
        }

        $json = [
            'id' => $document->getId() ?? '',
            'title' => method_exists($document, 'getTitle') ? $document->getTitle() : $document->getKey(),
            'imageSeo' => $seoImage,
            'prettyUrl' =>  method_exists($document, 'getPrettyUrl') ?  $document->getPrettyUrl() : '',
            'description' => method_exists($document, 'getDescription') ? $document->getDescription() : '',
            'controller' => method_exists($document, 'getController') ? $document->getController() : '',
            'path' => method_exists($document, 'getPath') ? $document->getPath() : '',
            'key' => method_exists($document, 'getKey') ? $document->getKey() : '',
            'type' => method_exists($document, 'getType') ? $document->getType() : '',
            'subject' =>  method_exists($document, 'getSubject') ?  $document->getSubject() : '',
            'from' =>  method_exists($document, 'getFrom') ?  $document->getFrom() : '',
            'replyTo' =>  method_exists($document, 'getReplyTo') ?  $document->getReplyTo() : '',
            'to' =>  method_exists($document, 'getTo') ?  $document->getTo() : '',
            'cc' =>  method_exists($document, 'getCc') ?  $document->getCc() : '',
            'bcc' =>  method_exists($document, 'getBcc') ?  $document->getBcc() : '',
            'docTypes' => $docTypes,
            'docType' => '',
            'lisDocType' => $lisDocType,
            'listEmail' => $listEmail,
            'href' => $href,
        ];

        return $json;
    }
}

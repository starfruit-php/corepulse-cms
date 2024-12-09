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
use Pimcore\Document\Editable\EditmodeEditableDefinitionCollector;
use Pimcore\Document\Renderer\DocumentRenderer;

/**
 * @Route("/document")
 */
class DocumentController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_document_listing", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function listing(): JsonResponse {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'parentId' => 'numeric',
                'type' => '',
                'filterRule' => '',
                'filter' => '',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $parentId = $this->request->get('parentId', 1);
            if (is_numeric($parentId)) {
                $conditionQuery .= ' AND parentId = :parentId';
                $conditionParams['parentId'] = $parentId;
            }

            $type = $this->request->get('type') ? $this->request->get('type') : '';
            if ($type) {
                $conditionQuery .= ' AND type = :type';
                $conditionParams['type'] = $type;
            }

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) $orderKey = 'index';
            if (empty($order)) $order = 'desc';

            $list = new Document\Listing();
            $list->setOrderKey($orderKey);
            $list->setOrder($order);
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setUnpublished(true);

            $paginationData = $this->paginator( $list, $page, $limit);
            $data = [
                'data' => [],
                'paginationData' => $paginationData->getPaginationData(),
            ];

            foreach ($list as $item) {
                $data['data'][] = self::listingResponse($item);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail/{id}", name="corepulse_api_document_detail", methods={"GET", "POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function detail()
    {
        // try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $id = $this->request->get('id');
            $document = Document::getById($id);
            if ($document) {
                if($this->request->isMethod(Request::METHOD_POST)) {
                    $params = $this->request->get('data');
                    if ($params) {
                        $params = json_decode($params, true);
                        foreach ($params as $key => $value) {
                            $document = DocumentServices::processField($document, $value);
                            if (!$document instanceof Document) {
                                return $this->sendResponse(['success' => false, 'message' => $document['name'] . ': ' . $document['error']]);
                            }
                        }
                        $document->setPublished($this->request->get('_publish') === 'publish');
                        $document->save();
                        return $this->sendResponse(['success' => true, 'message' => "Update document success"]);
                    }
                }

                $sidebar = DocumentServices::getSidebar($document);

                $data = [
                    'data' => [],
                    'sidebar' => $sidebar,
                ];

                if ($document->getType() != 'folder') {
                    $data['data'] = self::detailResponse($document);
                }
                
                return $this->sendResponse($data);
            }
            return $this->sendError("page.not.found");

        // } catch (\Exception $e) {
        //     return $this->sendError($e->getMessage(), 500);
        // }
    }

    /**
     * @Route("/edit-mode", name="corepulse_api_document_edit_mode", methods={"GET"})
     */
    public function editModeAction(EditmodeEditableDefinitionCollector $definitionCollector, DocumentRenderer $documentRenderer)
    {
        $document = Document::getById((int) $this->request->get('id'));
        // $res = $this->renderView($document->getTemplate(), ['editmode' => true, 'document' => $document]);
        // dd($document, $definitionCollector->getDefinitions());
        if ($document->getTemplate()) {
            return $this->render($document->getTemplate(), [
                'editmode' => true
            ]);
        } else {
            return $this->forward($document->getController());
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_document_delete", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function delete(): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $ids = $this->request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $document = Document::getById((int) $id);
                    if ($document) {
                        $document->delete();
                    } else {
                        return $this->sendResponse([ 'success' => false, 'message' => "Can not find document to be deleted"]);
                    }
                }
            } else {
                $document = Document::getById((int) $ids);
                if ($document) {
                    $document->delete();
                } else {
                    return $this->sendResponse([ 'success' => false, 'message' => "Can not find document to be deleted"]);
                }
            }

            return $this->sendResponse([ 'success' => true, 'message' => "Delete page success"]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/add", name="corepulse_api_document_add", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function add(): JsonResponse {
        try {
            $condition = [
                'title' => 'required',
                'type' => '',
                'key' => 'required',
                'parentId' => 'numeric',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $title = $this->request->get('title');
            $parentId = (int)$this->request->get('parentId', 1);
            if (empty($parentId)) $parentId = 1;

            $type = $this->request->get('type');
            $key = $this->request->get('key');

            $parent = Document::getById($parentId);
            $key = trim($key);
            if ($title) {
                $checkPage = Document::getByPath($parent->getFullPath() . $title);
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
            return $this->sendError($e->getMessage());
        }
    }

    // trả ra dữ liệu
    public function listingResponse($item)
    {

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

        $data = [
            'id' => $item->getId(),
            'key' => $item->getId() == 1 ? 'Home' : $item->getKey(),
            'type' => $item->getType(),
            'published' => $status,
            'createDate' => $this->getTimeAgo($item->getCreationDate()),
            'modificationDate' => $this->getTimeAgo($item->getModificationDate()),
            'parent' => (boolean)DocumentServices::isParent($item->getId()),
            'index' => $item->getIndex(),
        ];

        return $data;
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

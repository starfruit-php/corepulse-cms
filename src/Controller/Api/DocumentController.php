<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\DocumentServices;
use CorepulseBundle\Services\PermissionServices;
use Pimcore\Controller\Config\ControllerDataProvider;
use Pimcore\Document\Editable\EditmodeEditableDefinitionCollector;
use Pimcore\Document\Renderer\DocumentRenderer;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Document\DocType;
use Pimcore\Model\Tool;
use Pimcore\Templating\Renderer\ActionRenderer;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

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
    public function listing(): JsonResponse
    {
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
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $parentId = $this->request->get('parentId', 1);

            $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $parentId == 0 ? 1 : $parentId, PermissionServices::ACTION_LISTING);

            if (is_numeric($parentId)) {
                if ($parentId == 0) {
                    $conditionQuery .= ' AND ( parentId = 0 OR parentId = 1 )';
                } else {
                    $conditionQuery .= ' AND parentId = :parentId';
                    $conditionParams['parentId'] = $parentId;
                }
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
            if (empty($orderKey)) {
                $orderKey = 'index';
            }

            if (empty($order)) {
                $order = 'desc';
            }

            $list = new Document\Listing();
            $list->setOrderKey($orderKey);
            $list->setOrder($order);
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setUnpublished(true);

            $user = $this->getUser();
            $permissionData = PermissionServices::getPermissionData($user);

            $listPermission = array_filter($list->getData(), function ($item) use ($user, $permissionData) {
                return $user->getDefaultAdmin() || PermissionServices::isValid($permissionData, PermissionServices::TYPE_DOCUMENTS, $item->getId(), PermissionServices::ACTION_LISTING);
            });

            $paginationData = $this->paginator($listPermission, $page, $limit);
            $data = [
                'data' => [],
                'paginationData' => $paginationData->getPaginationData(),
            ];

            $data = [
                'data' => array_map(function ($item) use ($permissionData) {
                    return self::listingResponse($item, array_column($permissionData[PermissionServices::TYPE_DOCUMENTS], null, 'path'));
                }, $paginationData->getItems()),
                'paginationData' => $paginationData->getPaginationData(),
            ];
            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
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
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $id = $this->request->get('id');
            $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $id, PermissionServices::ACTION_VIEW);

            $document = Document::getById($id);
            if ($document) {
                if ($this->request->isMethod(Request::METHOD_POST)) {
                    $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $id, PermissionServices::ACTION_SAVE);

                    $setting = $this->request->get('setting');
                    if ($setting) {
                        $setting = json_decode($setting, true);
                        foreach ($setting as $key => $value) {
                            $document = DocumentServices::processSetting($document, $key, $value);
                            if (!$document instanceof Document) {
                                return $this->sendResponse(['success' => false, 'message' => $document['name'] . ': ' . $document['error']]);
                            }
                        }
                    }
                    $params = $this->request->get('data');
                    if ($params) {
                        $params = json_decode($params, true);
                        foreach ($params as $key => $value) {
                            $document = DocumentServices::processField($document, $value);
                            if (!$document instanceof Document) {
                                return $this->sendResponse(['success' => false, 'message' => $document['name'] . ': ' . $document['error']]);
                            }
                        }
                    }

                    $document->setPublished($this->request->get('_publish') === 'publish');
                    $document->save();
                    return $this->sendResponse([
                        'success' => true,
                        'message' => "Update document success",
                        "trans" => "pages.success.update_success",
                    ]);
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
            return $this->sendError([
                'success' => false,
                'message' => "Document not found",
                "trans" => "pages.errors.detail.not_found",
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/options", name="corepulse_api_document_option", methods={"POST"})
     */
    public function options()
    {
        try {
            $condition = [
                'id' => 'required',
                'config' => 'required',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $id = (int) $this->request->get('id');
            $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $id, PermissionServices::ACTION_VIEW);
            $document = Document::getById($id);

            if (!$document) {
                return $this->sendError([
                    'success' => false,
                    'message' => "Document not found",
                    "trans" => "pages.errors.detail.not_found",
                ], Response::HTTP_FORBIDDEN);
            }

            $data = [];
            $config = $this->request->get('config');
            if ($config) {
                $config = json_decode($config, true);

                $data = DocumentServices::getOption($config);
            } else {
                $data = DocumentServices::getOption([]);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendResponse([]);
            // return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/edit-mode", name="corepulse_api_document_edit_mode", methods={"GET"})
     */
    public function editModeAction(ActionRenderer $actionRenderer, EditmodeEditableDefinitionCollector $definitionCollector, DocumentRenderer $documentRenderer, FragmentRendererInterface $fragmentRenderer)
    {
        $condition = [
            'id' => 'required',
        ];

        $errorMessages = $this->validator->validate($condition, $this->request);
        if ($errorMessages) {
            return $this->sendError($errorMessages);
        }

        $id = (int) $this->request->get('id');
        $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $id, PermissionServices::ACTION_VIEW);
        $document = Document::getById($id);

        if (!$document) {
            return $this->sendResponse(['success' => false, 'message' => "Document not found"]);
        }

        list($class, $action) = explode('::', $document->getController());

        try {
            $reflector = new \ReflectionClass($class);
            $method = $reflector->getMethod($action);

            $template = $this->filterAttributeTemplate($method);
            if (!$template) {
                $template = $this->filterRenderTemplate($method);
            }

            return $this->render($template, [
                'editmode' => true,
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        }

        if ($document->getTemplate()) {
            return $this->render($document->getTemplate(), [
                'editmode' => true,
            ]);
        }

        return $this->forward($document->getController());
    }

    public function filterAttributeTemplate($method)
    {
        $attributes = $method->getAttributes(Template::class);
        if (!empty($attributes)) {
            // Lấy giá trị từ Attribute Template
            $templateAttribute = $attributes[0];
            $templateArguments = $templateAttribute->getArguments();

            $template = $templateArguments[0] ?? null;

            return $template;
        }

        return null;
    }

    public function filterRenderTemplate($method)
    {

        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $source = file($file);
        $code = implode("", array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        $tokens = token_get_all("<?php\n" . $code);
        $lastRenderTemplate = null;

        for ($i = 0; $i < count($tokens); $i++) {
            // Tìm câu lệnh `return $this->render`
            if (
                is_array($tokens[$i]) &&
                $tokens[$i][0] === T_RETURN &&
                isset($tokens[$i + 1]) &&
                is_array($tokens[$i + 1]) &&
                $tokens[$i + 1][0] === T_WHITESPACE &&
                isset($tokens[$i + 2]) &&
                is_array($tokens[$i + 2]) &&
                $tokens[$i + 2][1] === '$this' &&
                isset($tokens[$i + 3]) &&
                is_array($tokens[$i + 3]) &&
                $tokens[$i + 3][1] === '->render'
            ) {
                // Tìm template trong `render(...)`
                $j = $i + 4;
                while (isset($tokens[$j])) {
                    if ($tokens[$j] === '(') {
                        if (isset($tokens[$j + 1]) && is_array($tokens[$j + 1]) && $tokens[$j + 1][0] === T_CONSTANT_ENCAPSED_STRING) {
                            $lastRenderTemplate = trim($tokens[$j + 1][1], "'\"");
                            break;
                        }
                    }
                    $j++;
                }
            }
        }

        if ($lastRenderTemplate) {
            return $lastRenderTemplate;
        }

        return null;
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
    public function delete(): JsonResponse
    {
        try {
            $condition = [
                'id' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $ids = $this->request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $document = Document::getById((int) $id);
                    if ($document) {
                        $document->delete();
                    } else {
                        return $this->sendError([
                            'success' => false,
                            'message' => "Document not found",
                            "trans" => "pages.errors.detail.not_found",
                        ], Response::HTTP_FORBIDDEN);
                    }
                }
            } else {
                $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $ids, PermissionServices::ACTION_DELETE);
                $document = Document::getById((int) $ids);
                if ($document) {
                    $document->delete();
                } else {
                    return $this->sendError([
                        'success' => false,
                        'message' => "Document not found",
                        "trans" => "pages.errors.detail.not_found",
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            return $this->sendResponse([
                'success' => true, 
                'message' => "Delete page success",
                "trans" => "pages.success.delete_success"
            ]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
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
    public function add(): JsonResponse
    {
        try {
            $condition = [
                'title' => 'required',
                'type' => '',
                'key' => 'required',
                'parentId' => 'numeric',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) {
                return $this->sendError($errorMessages);
            }

            $title = $this->request->get('title');
            $parentId = (int) $this->request->get('parentId', 1);
            if (empty($parentId)) {
                $parentId = 1;
            }

            $this->validPermissionOrFail(PermissionServices::TYPE_DOCUMENTS, $parentId, PermissionServices::ACTION_CREATE);

            $type = $this->request->get('type');
            $key = $this->request->get('key');

            $parent = Document::getById($parentId);
            $key = trim($key);
            if ($title) {
                $checkPage = Document::getByPath($parent->getFullPath() . $title);
                if (!$checkPage) {
                    $page = DocumentServices::createDoc($key, $title, $type, $parentId);
                    if ($page) {
                        $data['data'] = self::listingResponse($page, []);
                        return $this->sendResponse($data);
                    } else {
                        return $this->sendResponse([
                            'success' => false,
                            'message' => "Create Errors",
                            "trans" => "pages.errors.detail.create_errors",
                        ]);
                    }
                } else {
                    return $this->sendResponse([
                        'success' => false,
                        'message' => "Already exists",
                        "trans" => "pages.errors.detail.duplicate",
                    ]);
                }
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    // get item json
    public function listingResponse($item, $permissionData = [])
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

        $id = $item->getId();
        $data = [
            'id' => $id,
            'key' => $id == 1 ? 'Home' : $item->getKey(),
            'type' => $item->getType(),
            'published' => $status,
            'createDate' => $this->getTimeAgo($item->getCreationDate()),
            'modificationDate' => $this->getTimeAgo($item->getModificationDate()),
            'parent' => (boolean) DocumentServices::isParent($item->getId()),
            'index' => $item->getIndex(),
            'permissions' => [],
        ];

        //fill all permission
        if (!empty($permissionData)) {
            $data['permissions'] = PermissionServices::getPermissionsRecursively($permissionData, PermissionServices::TYPE_DOCUMENTS, $id);
        } elseif ($this->getUser()?->getDefaultAdmin()) {
            $data['permissions'] = $this->getDefaultPermission();
        }

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
                $asset = Asset::getById((int) $idImage);
                if ($asset) {
                    $seoImage = $asset->getFullPath();
                }
            }
        }

        // nếu page là dạng email
        $listEmail = [];
        if ($document->getType() == "email") {
            $list = new Tool\Email\Log\Listing();
            $list->setCondition('documentId = ' . (int) $document->getId());
            $list->setLimit(50);
            $list->setOffset(0);
            $list->setOrderKey('sentDate');
            $list->setOrder('DESC');

            $data = $list->load();
            foreach ($data as $item) {
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

        $json = [
            'image' => $seoImage,
            'listEmail' => $listEmail,
        ];

        $params = ['id', 'title', 'prettyUrl', 'description', 'controller', 'template', 'path', 'key',
            'type', 'subject', 'from', 'replyTo', 'to', 'cc', 'bcc', 'href'];

        foreach ($params as $item) {
            $method = "get" . ucfirst($item);
            if (method_exists($document, $method)) {
                $json[$item] = $document->$method();
            }
        }

        return $json;
    }

    /**
     * @Route("/get-controller", name="corepulse_api_document_get_controller", methods={"GET"})
     *
     * @param ControllerDataProvider $provider
     *
     * @return JsonResponse
     */
    public function getControllerReferences(ControllerDataProvider $provider): JsonResponse
    {
        $controllerReferences = $provider->getControllerReferences();

        $result = array_map(function ($controller) {
            return [
                'key' => $controller,
                'label' => $controller,
                'value' => $controller,
            ];
        }, $controllerReferences);

        return $this->sendResponse(['data' => $result]);
    }

    /**
     * @Route("/get-document-list-type", name="corepulse_api_document_get_document_list_type", methods={"GET"})
     */
    public function getDocumentListType()
    {
        $condition = [
            'id' => 'required',
        ];

        $errorMessages = $this->validator->validate($condition, $this->request);
        if ($errorMessages) {
            return $this->sendError($errorMessages);
        }

        $document = Document::getById($this->request->get('id'));

        if (!$document) {
            return $this->sendResponse(['success' => false, 'message' => "Document not found"]);
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

        $result = [];
        foreach ($listDocType->getDocTypes() as $type) {
            $dataItem = $type->getObjectVars();

            $result[] = array_merge($dataItem, [
                'key' => $dataItem['name'],
                'label' => $dataItem['name'],
                'value' => $dataItem['id'],
            ]);
        }

        return $this->sendResponse(['data' => $result]);
    }

    /**
     * @Route("/get-templates", name="corepulse_api_document_get_templates", methods={"GET"})
     *
     * @param ControllerDataProvider $provider
     *
     * @return JsonResponse
     */
    public function getTemplates(ControllerDataProvider $provider): JsonResponse
    {
        $templates = $provider->getTemplates();

        sort($templates, SORT_NATURAL | SORT_FLAG_CASE);

        $result = array_map(static function ($template) {
            return [
                'key' => $template,
                'label' => $template,
                'value' => $template,
            ];
        }, $templates);

        return $this->sendResponse(['data' => $result]);
    }
}

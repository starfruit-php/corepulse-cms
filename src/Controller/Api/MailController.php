<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\DocumentServices;
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
use Pimcore\Logger;

/**
 * @Route("/mail")
 */
class MailController extends BaseController
{

    /**
     * @Route("/listing", name="api_email_listing", methods={"GET"})
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
            $orderByOptions = ['index'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'folderId' => '',
                'type' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if ($messageError) return $this->sendError($messageError);

            $orderKey = "index";
            $orderSet = "ASC";
            $limit = 25;
            $offset = 0;

            $conditionQuery = 'id != 1 AND type = "email"';
            $conditionParams = [];

            $id = $request->get('folderId') ? $request->get('folderId') : '';
            $parentId = 1;
            if ($id) {
                $parentId = $id;
                $conditionQuery .= ' AND parentId = :parentId';
                $conditionParams['parentId'] = $parentId;
            }

            $list = new \Pimcore\Model\Document\Listing();
            $list->setUnpublished(true);
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey($orderKey);
            $list->setOrder($orderSet);
            $list->setOffset($offset);
            $list->setLimit($limit);
            $list->load();

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'data' => []
                ],
                $paginationData,
            );

            foreach ($list as $item) {
                $data['data'][] = self::listingResponse($item);
            }
            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/show-email-log", name="api_email_show_email_log", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function showEmailLog( Request $request ): JsonResponse {
        try {
            $condition = [
                'id' => 'required',
                'type' => '',
            ];

            // $errorMessages = $this->validator->validate($condition, $request);
            // if ($errorMessages) return $this->sendError($errorMessages);

            // $type = $request->get('type');
            // $emailLog = Tool\Email\Log::getById((int) $request->get('id'));

            // if (!$emailLog) {
            //     throw $this->createNotFoundException();
            // }

            // if ($type === 'text') {
            //     return $this->render('@PimcoreAdmin/admin/email/text.html.twig', ['log' => $emailLog->getTextLog()]);
            // }
            // elseif ($type === 'html') {
            //     return new Response($emailLog->getHtmlLog(), 200, [
            //         'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src * data:",
            //     ]);
            // } elseif ($type === 'params') {
            //     try {
            //         $params = $emailLog->getParams();
            //     } catch (\Exception $e) {
            //         Logger::warning('Could not decode JSON param string');
            //         $params = [];
            //     }
            //     foreach ($params as &$entry) {
            //         $this->enhanceLoggingData($entry);
            //     }

            //     return $this->adminJson($params);
            // } elseif ($type === 'details') {
            //     $data = $emailLog->getObjectVars();

            //     return $this->adminJson($data);
            // } else {
            //     return new Response('No Type specified');
            // }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // trả ra dữ liệu
    public function listingResponse($item)
    {
        $json = [];

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
        if ($checkName === false) {
            $json[] = [
                'id' => $item->getId(),
                'name' => $item->getKey(),
                'type' => $item->getType(),
                'status' => $status,
                'createDate' => $this->getTimeAgo($item->getCreationDate()),
                'modificationDate' => $this->getTimeAgo($item->getModificationDate()),
                'parent' => $chills ? true : false,
                'noMultiEdit' => [
                    'name' => $chills ? [] : ['name'],
                ],
                "noAction" =>  $chills ? [] : ['seeMore'],
            ];
        }

        return $json;
    }

}

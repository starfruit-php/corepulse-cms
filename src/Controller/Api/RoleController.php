<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use CorepulseBundle\Model\User;
use CorepulseBundle\Model\Role;

/**
 * @Route("/roles")
 */
class RoleController extends BaseController
{
    /**
     * @Route("/listing", name="api_role_listing", methods={"GET","POST"})
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
        PaginatorInterface $paginator): JsonResponse
    {
        try {
            $this->setLocaleRequest();

            $orderByOptions = ['name'];
            $conditions = $this->getPaginationConditions($request, $orderByOptions);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'search' => '',
            ]);
            $messageError = $this->validator->validate($condition, $request);
            if($messageError) return $this->sendError($messageError);

            $conditionQuery = "";
            $conditionParams = [];

            $search = $request->get('search');
            if ($search) {
                foreach ($search as $key => $value) {
                    $conditionName = $key . " LIKE '%" . $value . "%'";
                    $conditionQuery .= ' AND ' . $conditionName;
                }
            }

            $list = new Role\Listing();
            $list->setCondition($conditionQuery, $conditionParams);
            $list->setOrderKey($request->get('order_by', 'name'));
            $list->setOrder($request->get('order', 'desc'));

            $paginationData = $this->helperPaginator($paginator, $list, $page, $limit);
            $data = array_merge(
                [
                    'totalItems' => $list->count(),
                    'data' => [],
                ],
                $paginationData,
            );

            foreach($list as $item)
            {
                $data['data'][] = self::listingResponse($item);
            }

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // Trả ra dữ liệu
    public function listingResponse($item)
    {
        $json[] = [
            'id' => $item->getId(),
            'name' => $item->getName(),
        ];

        return $json;
    }
}

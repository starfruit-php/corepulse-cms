<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Controller\Api\BaseController;
use Symfony\Component\Routing\Annotation\Route;
use CorepulseBundle\Model\TimeLine;
use CorepulseBundle\Services\TimeLineServices;

/**
 * @Route("/timeline"))
 */
class TimeLineController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_timeline_listing"))
     */
    public function listing()
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'orderId' => 'required',
            ]);

            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $order = $this->getOrderById($this->request->get('orderId'));
            if (!$order) return $this->sendError([ 'success' => false, 'message' => 'Order not found.' ]);

            $listing = TimeLineServices::getListingByOrder($order->getId());

            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
            ];

            foreach($pagination as $item) {
                $data['data'][] = TimeLineServices::getData($item);
            }

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/create", name="corepulse_api_timeline_create"))
     */
    public function create()
    {
        try {
            $conditions = [
                'orderId' => 'required',
                'title' => 'required',
                'description' => 'required',
            ];

            $messageError = $this->validator->validate($conditions, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $order = $this->getOrderById($this->request->get('orderId'));
            if (!$order) return $this->sendError([ 'success' => false, 'message' => 'Order not found.' ]);

            $timeLine = TimeLineServices::create([
                'title' => $this->request->get('title'),
                'orderId' => $this->request->get('orderId'),
                'description' => $this->request->get('description'),
            ]);

            $data = [
                'success' => true,
                'message' => 'Create TimeLine Success.'
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/update", name="corepulse_api_timeline_update"))
     */
    public function update()
    {
        try {
            $conditions = [
                'id' => 'required',
                'title' => '',
                'description' => '',
            ];

            $messageError = $this->validator->validate($conditions, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $timeLine = TimeLine::getById($this->request->get('id'));
            if (!$timeLine) return $this->sendError([ 'success' => false, 'message' => 'TimeLine not found.' ]);

            $timeLine = TimeLineServices::edit([
                'title' => $this->request->get('title'),
                'orderId' => $this->request->get('orderId'),
                'description' => $this->request->get('description'),
            ], $timeLine);

            $data = [
                'success' => true,
                'message' => 'Update TimeLine Success.'
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_timeline_delete")
     */
    public function delete()
    {
        try {
            $conditions = [
                'id' => 'required'
            ];

            $messageError = $this->validator->validate($conditions, $this->request);
            if ($messageError) return $this->sendError($messageError);

            $timeLine = TimeLine::getById($this->request->get('id'));
            if (!$timeLine) return $this->sendError([ 'success' => false, 'message' => 'TimeLine not found.' ]);

            TimeLineServices::delete($timeLine->getId());

            $data = [
                'success' => true,
                'message' => 'Delete TimeLine Success.'
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    private function getOrderById($orderId) {
        $orderManager = $this->factory->getOrderManager()->buildOrderList();
        $func = '\\Pimcore\\Model\\DataObject\\' . ucfirst($orderManager->getClassName());
        return $func::getById($orderId);
    }
}

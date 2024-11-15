<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\Helper\ArrayHelper;
use CorepulseBundle\Services\ReportServices;
use Pimcore\Bundle\CustomReportsBundle\Tool;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/report")
 */
class ReportController extends BaseController
{
    /**
     * @Route("/listing", name="corepulse_api_report_listing", methods={"GET","POST"})
     */
    public function listing()
    {
        try {
            $data = [];

            $list = new Tool\Config\Listing();
            $items = $list->getDao()->loadList();

            foreach ($items as $report) {
                if ($report->getDataSourceConfig()) {
                    $data[] = ReportServices::getReport($report);
                }
            }

            if ($data && $search = $this->request->get("search")) {
                $dataOld = $data;

                $data = ArrayHelper::filterData($data, 'id', $search);

                $data = array_merge($data, ArrayHelper::filterData($dataOld, 'niceName', $search));

                if (!empty($data)) {
                    $data = array_values($data);
                }
            }

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage(), 500);
        }
    }

    /**
     * @Route("/detail", name="corepulse_api_report_detail", methods={"GET","POST"})
     */
    public function detail()
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'id' => 'required',
                'type' => 'choice:table'
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $id = $this->request->get('id');
            $report = Tool\Config::getByName($id);

            if (!$report) {
                return $this->sendError('Report not found', 500);
            }

            $fields = [];
            $chartData = [];
            $chart = [];
            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');

            $fields = ReportServices::getColumn($report->getColumnConfiguration());

            $conditionQuery = '';
            $conditionParams = [];

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery = $arrQuery['query'];
                    $conditionParams = $arrQuery['params'];
                }
            }

            $listing = ReportServices::getSql($report->getDataSourceConfig(), $conditionQuery, $conditionParams);

            if ($listing && $orderKey && $order) {
                $listing = ArrayHelper::sortArrayByField($listing, $orderKey, $order);
            }

            $pagination = $this->paginator($listing, $page, $limit);

            if ($this->request->get('type') == 'table') {
                return $this->sendResponse([
                    'paginationData' => $pagination->getPaginationData(),
                    'data' => $pagination->getItems()
                ]);
            }

            $chart = ReportServices::getChart($report);

            if ($chart) {
                $chartData = ReportServices::getChartData($listing, $chart);
            }

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => $pagination->getItems(),
                'fields' => $fields,
                'chart' => $chart,
                'chartData' => $chartData,
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage(), 500);
        }
    }
}

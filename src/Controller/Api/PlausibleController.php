<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\APIService;
use Pimcore\Db;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\DataObject\ClassDefinition;


/**
 * @Route("/plausible")
 */
class PlausibleController extends BaseController
{
    /**
     * @Route("/get-setting", name="api_plausible_get_setting", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getSetting(
        Request $request): JsonResponse
    {
        try {

            $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_plausible`', ['id' => 1]);

            $data['data'] = [
                'domain' => '',
                'siteId' => '',
                'apiKey' => '',
                "username" => "",
                "password" => "",
                'link' => '',
            ];

            if ($item) {
                $data['data'] = $item;
            }

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/setup", name="api_plausible_setup", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function setup(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'domain' => '',
                'siteId' => '',
                'apiKey' => '',
                'link' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $item = Db::get()->fetchAssociative('SELECT * FROM `corepulse_plausible`', ['id' => 1]);

            $plausibleSet = [];
            $uniqueIdentifier = 1;
            if ($request->get('domain')) {
                $plausibleSet['domain'] = $request->get('domain');
            }
            if ($request->get('siteId')) {
                $plausibleSet['siteId'] = $request->get('siteId');
            }
            if ($request->get('apiKey')) {
                $plausibleSet['apiKey'] = $request->get('apiKey');
            }
            if ($request->get('link')) {
                $plausibleSet['link'] = $request->get('link');
            }

            if ($request->get('domain') || $request->get('siteId') || $request->get('apiKey')) {
                if ($item) {
                    Db::get()->update(
                        'corepulse_plausible',
                        $plausibleSet,
                        ['id' => $uniqueIdentifier]
                    );
                } else {
                    Db::get()->insert(
                        'corepulse_plausible',
                        [
                            'domain' => $plausibleSet['domain'],
                            'siteId' => $plausibleSet['siteId'],
                            'apiKey' => $plausibleSet['apiKey'],
                            'link' => $plausibleSet['link'],
                        ]
                    );
                }
            }

            return $this->sendResponse("setup.plausible.success");

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @Route("/get-data", name="api_plausible_get_data", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getData(
        Request $request): JsonResponse
    {
        try {
            $condition = [
                'type' => '',
                'start_date' => '',
                'end_date' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $type = $request->get('type');
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            $plasibleSet = Db::get()->fetchAssociative('SELECT * FROM `corepulse_plausible`', ['id' => 1]);

            $data['data'] = [];

            if ($plasibleSet) {
                $url = $plasibleSet['domain'];
                $siteId = $plasibleSet['siteId'];
                $apiKey = $plasibleSet['apiKey'];

                $url_api = $url . '/api/v1/stats/timeseries?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date;
                if ($type == 'visitors') {
                    $url_api = $url . '/api/v1/stats/timeseries?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date;
                }
                if ($type == 'realtime') {
                    $url_api = $url . '/api/v1/stats/aggregate?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date . '&metrics=visitors,pageviews,bounce_rate,visit_duration,views_per_visit,visits';
                }
                if ($type == 'toppages') {
                    $url_api = $url . '/api/v1/stats/breakdown?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date .'&property=event:page&limit=5';
                }
                if ($type == 'devices') {
                    $url_api = $url . '/api/v1/stats/breakdown?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date . '&property=visit:browser&metrics=visitors,bounce_rate&limit=5';
                }
                if ($type == 'topSoucres') {
                    $url_api = $url . '/api/v1/stats/breakdown?site_id=' . $siteId . '&period=custom&date=' . $start_date . ',' . $end_date . '&property=visit:source&metrics=visitors,bounce_rate&limit=5';
                }

                $header['Authorization'] = "Bearer " . $apiKey;
                $response = APIService::post($url_api, 'GET',  null, $header);
                if ($response && isset($response['results'])) {
                    $result = $response['results'];
                }

                $data['data'] = $result;
            }

            return $this->sendResponse($data);

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}

<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Model\Indexing;
use Firebase\JWT\JWT;
use Pimcore\Db;
use Pimcore\Model;
use Starfruit\BuilderBundle\Model\Option;
use Starfruit\BuilderBundle\Sitemap\Setting;

class GoogleServices
{
    const CONFIG_UPDATE_INDEXING = 'corepulse.event_listener.update_indexing';
    const CONFIG_INSPECTION_INDEX = 'corepulse.event_listener.inspection_index';

    public static function eventConfig($type = 'indexing')
    {
        $configName = self::CONFIG_UPDATE_INDEXING;
        if ($type == 'inspection') {
            $configName = self::CONFIG_INSPECTION_INDEX;
        }

        $config = \Pimcore::getContainer()->getParameter($configName);

        return $config;
    }

    public static function convertParams($params)
    {
        $params['value'] = json_decode($params['value'], true);

        return self::setConfig(($params));
    }

    public static function setConfig($params)
    {
        // try {
        if ($params['type'] == 'file') {
            $pathUpload = PIMCORE_PROJECT_ROOT . '/public/' . $params['value']->getClientOriginalName();
            if (!move_uploaded_file($params['value'], $pathUpload)) {
                $data = [
                    'success' => false,
                    'message' => 'Upload file error',
                ];

                return $data;
            }
            $params['value'] = $pathUpload;
        }

        $connect = self::connectGoogleIndex($params['value']);

        $setting = Option::getByName('index-setting');
        if (!$setting) {
            $setting = new Option();
            $setting->setName('index-setting');
        } else {
            $oldContent = $setting->getContent() ? json_decode($setting->getContent(), true) : [];
            if (isset($oldContent['type'])) {
                if (!$params['type']) {
                    $params['type'] = $oldContent['type'];
                    $params['value'] = $oldContent['value'];
                } else {
                    if ($oldContent['type'] == 'file') {
                        if (file_exists($oldContent['value'])) {
                            unlink($oldContent['value']);
                        }
                    }
                }
            }
        }

        $setting->setContent(json_encode($params));
        $setting->save();

        $data = [
            'success' => true,
            'message' => 'Indexing setting success',
        ];

        return $data;
        // } catch (\Throwable $th) {
        //     $data = [
        //         'success' => false,
        //         'message' => $th->getMessage(),
        //     ];

        //     return $data;
        // }
    }

    public static function getConfig()
    {
        $settingClass = Setting::getKeys();
        $settingDocument = Setting::getPages();

        $data = [
            "type" => "json",
            "value" => null,
            "classes" => $settingClass,
            "documents" => $settingDocument,
        ];

        try {
            $setting = Option::getByName('index-setting');
            if ($setting) {
                $content = json_decode($setting->getContent(), true);

                $data['type'] = $content['type'];

                if ($content['type'] == 'file') {
                    $data['value'] = basename($content['value']);
                }

                if ($content['type'] == 'json') {
                    $data['value'] = json_encode($content['value']);
                }

                if (is_string($content['classes'])) {
                    $content['classes'] = $data['classes'] = json_decode($content['classes'], true);
                }

                if (is_string($content['documents'])) {
                    $content['documents'] = $data['documents'] = json_decode($content['documents'], true);
                }

                if (is_array($content['classes']) && count($data['classes']) != count($settingClass)) {
                    $data['classes'] = $settingClass;
                }

                if (is_array($content['documents']) && count($data['documents']) != count($settingDocument)) {
                    $data['documents'] = $settingDocument;
                }
            }
        } catch (\Throwable $th) {
        }

        return $data;
    }

    public static function authConfig()
    {
        $data = null;

        $setting = Option::getByName('index-setting');
        if ($setting) {
            $content = json_decode($setting->getContent(), true);
            $data = $content['value'];
        }

        return $data;
    }

    public static function authConfigData()
    {
        $data = null;

        $config = self::authConfig();
        if (is_string($config)) {
            if (!file_exists($config)) {
                return false;
            }

            $config = file_get_contents($config);
        }

        if (!$data = json_decode($config, true)) {
            return false;
        }

        return $data;
    }

    public static function connectGoogleIndex($config = null)
    {
        if (!$config) {
            $config = self::authConfig();
        }

        $client = new \Google\Client();
        $client->setAuthConfig($config);
        $client->addScope([\Google\Service\Indexing::INDEXING, \Google\Service\SearchConsole::WEBMASTERS]);
        $client->setAccessType('offline');
        $client->setApplicationName('Corepulse CMS');

        $indexing = new \Google\Service\Indexing($client);
        $searchConsole = new \Google\Service\SearchConsole($client);
        $searchConsole->sites->add(Option::getMainDomain() . '/');

        $httpClient = $client->authorize();

        return [$indexing, $searchConsole, $httpClient];
    }

    public static function submitIndex($params, ?bool $action = null)
    {
        $domain = Option::getMainDomain();
        $sites = "{$domain}/";

        // Lấy URL từ params hoặc từ đối tượng Indexing
        $url = self::getUrlFromParams($params, $domain);

        $type = $params['type'];
        $objectOrDocument = $params['objectOrDocument'] ?? null;

        // Khởi tạo hoặc lấy đối tượng Indexing
        $indexing = self::initializeIndexing($params, $url, $type, $objectOrDocument);

        // Nếu loại là "create" và đối tượng Indexing đã tồn tại, trả về lỗi
        if ($type === 'create' && $indexing) {
            return self::errorResponse('Indexing already exists', 'indexing.errors.detail.already_exists');
        }

        // Nếu không có Indexing, tạo một đối tượng mới
        if (!$indexing) {
            $indexing = new Indexing();
            $indexing->setUrl($url);
            $indexing->setCreateAt(date('Y-m-d H:i:s'));
        }

        try {
            // Xử lý yêu cầu gửi lên Google Indexing API
            $data = self::processIndexingRequest($url, $sites, $type, $action);

            // Lưu đối tượng Indexing và kết quả
            $data['indexing'] = self::saveIndex($indexing, array_merge($data, self::getParamSave($objectOrDocument, $params)));
            $data['success'] = true;
            $data['trans'] = 'indexing.success.create_success';
        } catch (\Throwable $th) {
            // Xử lý ngoại lệ và trả về lỗi
            return self::errorResponse($th->getMessage(), 'indexing.errors.exception');
        }

        return $data;
    }

    private static function getUrlFromParams($params, $domain)
    {
        // Nếu Indexing tồn tại trong params, lấy URL từ đó
        if (isset($params['indexing']) && $params['indexing'] instanceof Indexing) {
            return $params['indexing']->getUrl();
        }

        // Nếu không, lấy URL từ params hoặc mặc định domain
        return $domain . ($params['url'] ?? '');
    }

    private static function initializeIndexing($params, $url, $type, $objectOrDocument)
    {
        // Nếu objectOrDocument tồn tại, chuẩn bị thông tin cho việc khởi tạo Indexing
        if ($objectOrDocument instanceof Model\DataObject  || $objectOrDocument instanceof \Pimcore\Bundle\PersonalizationBundle\Model\Document\Page) {
            $paramSave = self::getParamSave($objectOrDocument, $params);

            // Lấy Indexing từ cơ sở dữ liệu dựa trên giá trị nội bộ (internalValue)
            $indexing = self::getIndexing($paramSave['internalValue'], $paramSave['internalType'], $paramSave['language']);

            // Nếu là loại "delete" và Indexing đã tồn tại, cập nhật URL
            if ($indexing && $paramSave['internalType'] === 'object' && $type === 'delete') {
                $url = $indexing->getUrl();
            }
            return $indexing;
        }

        // Nếu không có objectOrDocument, lấy Indexing từ URL
        return Indexing::getByUrl($url);
    }

    private static function getParamSave($objectOrDocument, $params)
    {
        // Chuẩn bị tham số để lưu thông tin Indexing
        if (!$objectOrDocument) {
            return [];
        }

        return [
            'internalValue' => (string) $objectOrDocument->getId(),
            'language' => $params['language'] ?? '',
            'internalType' => $objectOrDocument instanceof Model\DataObject  ? 'object' : 'page',
        ];
    }

    private static function processIndexingRequest($url, $sites, $type, $action)
    {
        // Kết nối đến Google Indexing API
        $connect = self::connectGoogleIndex();
        [$indexingService, $searchConsole, $httpClient] = $connect;

        // Định nghĩa endpoint và loại URL
        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $typeUrl = $type === 'delete' ? 'URL_DELETED' : 'URL_UPDATED';

        // Gửi yêu cầu đến Google API
        $content = json_encode(['url' => $url, 'type' => $typeUrl]);
        $response = $httpClient->post($endpoint, ['body' => $content]);
        $data = self::getResponData($response);

        // Nếu cần, thực hiện kiểm tra URL với Google Search Console
        $result = self::inspectUrlIfNeeded($url, $sites, $searchConsole, $action);

        // Chuẩn bị dữ liệu kết quả
        return array_merge($data, [
            'type' => $typeUrl,
            'result' => json_encode($result),
            'updateAt' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function inspectUrlIfNeeded($url, $sites, $searchConsole, $action)
    {
        // Nếu không yêu cầu kiểm tra, trả về dữ liệu mặc định
        if (!self::eventConfig('inspection') && !$action) {
            return [];
        }

        try {
            // Thực hiện kiểm tra URL với Google Search Console
            $inspectUrl = new \Google\Service\SearchConsole\InspectUrlIndexRequest();
            $inspectUrl->setInspectionUrl($url);
            $inspectUrl->setSiteUrl($sites);
            $inspectUrl->setLanguageCode('vi');

            $urlInspection = $searchConsole->urlInspection_index->inspect($inspectUrl);
            return [
                'indexStatusResult' => json_decode(json_encode($urlInspection->inspectionResult->indexStatusResult), true),
                'mobileUsabilityResult' => json_decode(json_encode($urlInspection->inspectionResult->mobileUsabilityResult), true),
            ];
        } catch (\Throwable $th) {
            return [
                'indexStatusResult' => [],
                'mobileUsabilityResult' => [],
                'status' => 'error',
                'message' => $th->getMessage(),
            ];
        }
    }

    private static function errorResponse($message, $trans)
    {
        return [
            'success' => false,
            'message' => $message,
            'trans' => $trans,
        ];
    }
    public static function getResponData($response)
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $bodyData = json_decode($body, true);

        if (isset($bodyData['error'])) {
            $data = $bodyData['error'];
        } else {
            // chuyển Metadata thành array
            $metadata = isset($bodyData['urlNotificationMetadata']) ? $bodyData['urlNotificationMetadata'] : [];

            $key = 'latestUpdate';

            if (isset($metadata['latestUpdate']) && isset($metadata['latestRemove'])) {
                $latestUpdateTime = new \DateTime($metadata['latestUpdate']['notifyTime']);
                $latestRemoveTime = new \DateTime($metadata['latestRemove']['notifyTime']);

                if ($latestUpdateTime < $latestRemoveTime) {
                    $key = 'latestRemove';
                }
            } else if (isset($metadata['latestRemove'])) {
                $key = 'latestRemove';
            }

            $data = isset($metadata[$key]) ? self::responArray($metadata[$key]) : [];
        }

        $data['response'] = $statusCode;

        return $data;
    }

    public static function responArray($item)
    {
        $localTimezone = new \DateTimeZone(date_default_timezone_get());

        $notifyDateTime = new \DateTime($item["notifyTime"], new \DateTimeZone('UTC'));
        $notifyDateTime->setTimezone($localTimezone);

        $time = $notifyDateTime->format('Y-m-d H:i:s');

        $data = [
            'url' => $item['url'],
            'type' => $item['type'],
            'time' => $time,
        ];

        return $data;
    }

    public static function getIndexing($internalValue, $internalType, $language = null)
    {
        $query = 'SELECT id FROM `corepulse_indexing` where `internalValue` = ? AND `internalType` = ? ';
        $params = [$internalValue, $internalType];

        if ($language) {
            $query .= ' AND `language` = ?';
            $params[] = $language;
        }

        $id = Db::get()->fetchAssociative($query, $params);
        if ($id) {
            return Indexing::getById($id['id']);
        }

        return false;
    }

    public static function saveIndex($indexing, $params)
    {
        foreach ($params as $key => $value) {
            $function = 'set' . ucfirst($key);

            if (method_exists($indexing, $function)) {
                $indexing->$function($value);
            }
        }

        $indexing->save();

        return $indexing;
    }

    public static function filterIndexingStatus($listing)
    {
        $datas = [];
        $colors = $counts = [
            "coverageState" => [],
            "crawledAs" => [],
            "googleCanonical" => [],
            "indexingState" => [],
            "pageFetchState" => [],
            "robotsTxtState" => [],
            "userCanonical" => [],
            "verdict" => [],
        ];

        foreach ($listing as $item) {
            $data = array_merge($item->getDataJson(), [
                'coverageState' => null,
                'crawledAs' => null,
                'googleCanonical' => null,
                'indexingState' => null,
                'pageFetchState' => null,
                'robotsTxtState' => null,
                'userCanonical' => null,
                'verdict' => null,
            ]);

            if (isset($data["result"]["indexStatusResult"])) {
                $indexStatusResult = $data["result"]["indexStatusResult"];
                foreach ($indexStatusResult as $key => $value) {
                    if (array_key_exists($key, $counts)) {
                        if ($value == null) {
                            $value = 'null';
                        }

                        try {
                            if (array_key_exists($value, $counts[$key])) {
                                $counts[$key][$value]++;
                            } else {
                                $counts[$key][$value] = 1;
                            }
                        } catch (\Throwable $th) {
                            //     dd($key,$data, $value);
                        }

                        $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

                        // Kiểm tra xem màu đã được sử dụng chưa, nếu đã sử dụng, tạo lại
                        while (in_array($color, $colors[$key])) {
                            $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                        }

                        $colors[$key][$value] = $color;

                        $data[$key] = $value;
                    }
                }
            }
        }

        $countsTotal = array_map('array_sum', $counts); // Tổng số lần xuất hiện của mỗi giá trị
        $percents = [];

        foreach ($counts as $key => $values) {
            $total = $countsTotal[$key];
            $percents[$key] = array_map(function ($value) use ($total) {
                return (int) round(($value / $total) * 100);
            }, $values);
        }


        $datas['percents'] = $percents;
        $datas['counts'] = $counts;
        $datas['colors'] = $colors;

        return $datas;
    }

    public static function getAccessToken()
    {
        $config = self::authConfigData();
        $privateKey = $config['private_key'];
        $payload = array(
            "iss" => $config['client_email'],
            "scope" => "https://www.googleapis.com/auth/indexing https://www.googleapis.com/auth/webmasters",
            "aud" => $config['token_uri'],
            "exp" => time() + 3600,
            "iat" => time(),
        );

        $jwt = JWT::encode($payload, $privateKey, 'RS256', $config['private_key_id']);

        $data = array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        );

        $url = 'https://oauth2.googleapis.com/token';

        $connect = APIService::curl($url, 'POST', $data);
        $connect = json_decode($connect, true);

        return isset($connect['access_token']) ? $connect['access_token'] : false;
    }

    public static function submitType($idsOrId, $type)
    {
        if (is_array($idsOrId)) {
            $domain = Option::getMainDomain();
            $sites = $domain . '/';

            $boundary = '===============7330845974216740156==';
            $ids = $idsOrId;
            $updateKey = 'URL_UPDATED';
            $deleteKey = 'URL_DELETED';
            $contentId = 'corepusleIndexing';
            $batchRequestData = '';
            $batchSearchData = '';
            $dataOld = [];
            foreach ($ids as $key => $id) {
                $option = Indexing::getById($id);
                if ($option) {
                    switch ($type) {
                        case 'update-submit':
                            $dataOld["response-$contentId+$key"] = $option;

                            $url = $option->getUrl();

                            $requestContent = json_encode(["url" => $url, "type" => $updateKey]);
                            $batchRequestData .= "--$boundary\r\n";
                            $batchRequestData .= "Content-Type: application/http\r\nContent-Transfer-Encoding: binary\r\n";
                            $batchRequestData .= "Content-ID: <$contentId+$key>\r\n\r\n";
                            $batchRequestData .= "POST /v3/urlNotifications:publish\r\n";
                            $batchRequestData .= "Content-Type: application/json\r\naccept: application/json\r\n";
                            $batchRequestData .= "content-length: " . strlen($requestContent) . "\r\n\r\n";
                            $batchRequestData .= $requestContent . "\r\n";

                            break;

                        case 'delete-submit':
                            $dataOld["response-$contentId+$key"] = $option;

                            $url = $option->getUrl();

                            $requestContent = json_encode(["url" => $url, "type" => $deleteKey]);
                            $batchRequestData .= "--$boundary\r\n";
                            $batchRequestData .= "Content-Type: application/http\r\nContent-Transfer-Encoding: binary\r\n";
                            $batchRequestData .= "Content-ID: <$contentId+$key>\r\n\r\n";
                            $batchRequestData .= "POST /v3/urlNotifications:publish\r\n";
                            $batchRequestData .= "Content-Type: application/json\r\naccept: application/json\r\n";
                            $batchRequestData .= "content-length: " . strlen($requestContent) . "\r\n\r\n";
                            $batchRequestData .= $requestContent . "\r\n";
                            break;

                        case 'inspection':
                            $dataOld["response-$contentId+$key"] = $option;

                            $url = $option->getUrl();

                            $searchContent = json_encode(["inspectionUrl" => $url, "siteUrl" => $sites, "languageCode" => 'vi']);
                            $batchSearchData .= "--$boundary\r\n";
                            $batchSearchData .= "Content-Type: application/http\r\nContent-Transfer-Encoding: binary\r\n";
                            $batchSearchData .= "Content-ID: <$contentId+$key>\r\n\r\n";
                            $batchSearchData .= "POST /v1/urlInspection/index:inspect\r\n";
                            $batchSearchData .= "Content-Type: application/json\r\naccept: application/json\r\n";
                            $batchSearchData .= "content-length: " . strlen($searchContent) . "\r\n\r\n";
                            $batchSearchData .= $searchContent . "\r\n";
                            break;

                        default:
                            break;
                    }
                }
            }

            $batchRequestData .= "--$boundary--";
            $batchSearchData .= "--$boundary--";

            $token = GoogleServices::getAccessToken();

            $batchRequestHeaders = [
                'Content-Length: ' . strlen($batchRequestData),
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
                'Authorization: Bearer ' . $token,
            ];
            $urlRequest = 'https://indexing.googleapis.com/batch';

            $requestBody = APIService::curl($urlRequest, 'POST', $batchRequestData, $batchRequestHeaders);

            if ($type == 'inspection') {
                $batchSearchHeaders = [
                    'Content-Length: ' . strlen($batchSearchData),
                    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
                    'Authorization: Bearer ' . $token,
                ];
                $urlSearch = 'https://searchconsole.googleapis.com/batch';

                $searchBody = APIService::curl($urlSearch, 'POST', $batchSearchData, $batchSearchHeaders);

                $converts = explode("--", $searchBody);

                $result = [];

                foreach ($converts as $convert) {
                    if (strpos($convert, "Content-Type: application/http") !== false) {
                        preg_match('/Content-ID: <(.*?)>/', $convert, $contentIdMatches);
                        $contentId = $contentIdMatches[1];

                        preg_match('/{(.*)}/s', $convert, $matches);
                        $json = $matches[0];

                        $array = json_decode($json, true);

                        if ($array) {
                            $result[$contentId] = $array;
                        }
                    }
                }

                foreach ($result as $key => $value) {
                    $indexing = $dataOld[$key];
                    $indexing->setResult(json_encode($value['inspectionResult']));
                    $indexing->setType('update');
                    $indexing->save();
                }
            } else {
                foreach ($dataOld as $key => $value) {
                    $value->setType($type == 'update-submit' ? 'update' : 'delete');
                    $value->save();
                }
            }

            return [
                'success' => true,
                'message' => 'Multi indexing success',
                'trans' => 'indexing.success.multi_success',
            ];
        } else {
            $id = $idsOrId;
            $option = Indexing::getById($id);

            if (!$option) {
                return self::errorResponse('Indexing not found', 'indexing.errors.not_found');
            }

            $mapping = [
                'update-submit' => [
                    'type' => 'update',
                    'successMessage' => 'Update submit indexing success',
                    'trans' => 'indexing.success.update_submit_success',
                ],
                'delete-submit' => [
                    'type' => 'delete',
                    'successMessage' => 'Delete submit indexing success',
                    'trans' => 'indexing.success.delete_submit_success',
                ],
                'inspection' => [
                    'type' => 'update',
                    'successMessage' => 'Inspection indexing success',
                    'trans' => 'indexing.success.inspection_success',
                ],
            ];

            if (!isset($mapping[$type])) {
                return self::errorResponse('Invalid type provided', 'indexing.errors.detail.invalid_type');
            }

            $params = [
                'type' => $mapping[$type]['type'],
                'indexing' => $option,
            ];

            $data = GoogleServices::submitIndex($params, true);

            if (isset($data['status'])) {
                $data['success'] = false;
                return $data;
            }

            return [
                'success' => true,
                'message' => $mapping[$type]['successMessage'],
                'trans' => $mapping[$type]['trans'],
                'data' => $data,
            ];
        }
    }

    public static function deleteAction($idsOrId)
    {
        if (is_array($idsOrId)) {
            foreach ($idsOrId as $id) {
                $option = Indexing::getById($id);
                if ($option) {
                    $option->delete();
                } else {
                    return self::errorResponse('Indexing not found', 'indexing.errors.detail.not_found');
                }
            }
            return [
                'success' => true,
                'message' => 'Delete indexing success',
                'trans' => 'indexing.success.delete_success',
            ];
        }

        $option = Indexing::getById($idsOrId);
        if ($option) {
            $option->delete();
            return [
                'success' => true,
                'message' => 'Delete indexing success',
                'trans' => 'indexing.success.delete_success',
            ];
        } else {
            return self::errorResponse('Indexing not found', 'indexing.errors.detail.not_found');
        }
    }
}

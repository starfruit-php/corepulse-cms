<?php

namespace CorepulseBundle\Services;

use Pimcore\Model;
use CorepulseBundle\Model\Indexing;
use Starfruit\BuilderBundle\Model\Option;
use Starfruit\BuilderBundle\Sitemap\Setting;
use Pimcore\Db;
use Firebase\JWT\JWT;

class GoogleServices
{
    const CONFIG_UPDATE_INDEXING = 'corepulse.event_listener.update_indexing';
    const CONFIG_INSPECTION_INDEX = 'corepulse.event_listener.inspection_index';

    static public function eventConfig($type = 'indexing')
    {
        $configName = self::CONFIG_UPDATE_INDEXING;
        if ($type == 'inspection') {
            $configName = self::CONFIG_INSPECTION_INDEX;
        }

        $config = \Pimcore::getContainer()->getParameter($configName);

        return $config;
    }

    static public function setConfig($params)
    {
        try {
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

            $data =  [
                'success' => true,
                'message' => 'Indexing setting success',
            ];

            return $data;
        } catch (\Throwable $th) {
            $data = [
                'success' => false,
                'message' => $th->getMessage(),
            ];

            return $data;
        }
    }

    static public function getConfig() {
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

    static public function authConfig()
    {
        $data = null;

        $setting = Option::getByName('index-setting');
        if ($setting) {
            $content = json_decode($setting->getContent(), true);
            $data = $content['value'];
        }

        return $data;
    }

    static public function authConfigData()
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

    static public function connectGoogleIndex($config = null)
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

    static public function submitIndex($params, ?bool $action = null)
    {
        $domain = Option::getMainDomain();
        $sites = $domain . '/';

        $indexing = '';
        if (!$indexing) {
            $indexing = isset($params['indexing']) && $params['indexing'] instanceof Indexing ? $params['indexing'] : '';
        }

        if ($indexing) {
            $url = $indexing->getUrl();
        } else {
            $url = $domain . $params['url'];
        }

        $type = $params['type'];
        $objectOrDocument = isset($params['objectOrDocument']) ? $params['objectOrDocument'] : null;

        $data = [];

        try {
            $paramSave = [];
            if ($objectOrDocument instanceof Model\DataObject || $objectOrDocument instanceof \Pimcore\Bundle\PersonalizationBundle\Model\Document\Page) {
                $paramSave = [
                    'internalValue' => (string)$objectOrDocument->getId(),
                    'language' => $params['language'],
                ];

                if ($objectOrDocument instanceof Model\DataObject) {
                    $paramSave['internalType'] = 'object';
                } else {
                    $paramSave['internalType'] = 'page';
                }

                $indexing = self::getIndexing($paramSave['internalValue'], $paramSave['internalType'], $paramSave['language']);

                if ($indexing && $paramSave['internalType'] == 'object' && $type == 'delete') {
                    $url = $indexing->getUrl();
                }
            }

            if (!$indexing) {
                $indexing = isset($params['url']) ? Indexing::getByUrl($params['url']) : '';
            }

            if (!$indexing) {
                $indexing = new Indexing();
            }

            $connect = self::connectGoogleIndex();
            list($indexingService, $searchConsole, $httpClient) = $connect;

            $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

            $typeUrl = 'URL_UPDATED';
            if ($type == 'delete') {
                $typeUrl = "URL_DELETED";
            }

            $content = json_encode([
                "url" => $url,
                "type" => $typeUrl
            ]);

            $response = $httpClient->post($endpoint, [ 'body' => $content ]);

            $data = self::getResponData($response);

            $result = [
                'indexStatusResult' => [],
                'mobileUsabilityResult' => [],
            ];
            if (self::eventConfig('inspection') || $action) {
                try {
                    $inspectUrl = new \Google\Service\SearchConsole\InspectUrlIndexRequest();
                    $inspectUrl->setInspectionUrl($url);
                    $inspectUrl->setSiteUrl($sites);
                    $inspectUrl->setLanguageCode('vi');

                    $urlInspection = $searchConsole->urlInspection_index->inspect($inspectUrl);

                    $indexStatusResult = $urlInspection->inspectionResult->indexStatusResult;
                    $mobileUsabilityResult = $urlInspection->inspectionResult->mobileUsabilityResult;

                    $result = [
                        'indexStatusResult' => json_decode(json_encode($indexStatusResult), true),
                        'mobileUsabilityResult' => json_decode(json_encode($mobileUsabilityResult), true),
                    ];
                } catch (\Throwable $th) {
                    $result = [
                        'indexStatusResult' => [],
                        'mobileUsabilityResult' => [],
                        'status' => 'error',
                        'message' => $th->getMessage(),
                    ];
                }
            }

            if (isset($data['status'])) {
                $data["url"] = $url;
                $data["type"] = $data['status'];
                $data["result"] = json_encode($result);
            } else {
                $data['success'] = true;
                $data['message'] = 'Submit indexing success';
                $data["type"] = $type;
                $data["result"] = json_encode($result);
            }

            self::saveIndex($indexing, array_merge($data, $paramSave));
        } catch (\Throwable $th) {
            $data['message'] = $th->getMessage();
        }

        return $data;
    }

    static public function getResponData($response)
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $bodyData = json_decode($body, true);

        if (isset($bodyData['error'])) {
            $data = $bodyData['error'];
        } else {
            $metadata = isset($bodyData['urlNotificationMetadata']) ? $bodyData['urlNotificationMetadata'] : [];

            $key = 'latestUpdate';

            if (isset($metadata['latestUpdate']) && isset($metadata['latestRemove'])) {
                $latestUpdateTime = new \DateTime($metadata['latestUpdate']['notifyTime']);
                $latestRemoveTime = new \DateTime($metadata['latestRemove']['notifyTime']);

                if ($latestUpdateTime < $latestRemoveTime ) {
                    $key = 'latestRemove';
                }
            } else if (isset($metadata['latestRemove'])) {
                $key = 'latestRemove';
            }

            $data = self::responArray($metadata[$key]);
        }

        $data['response'] = $statusCode;

        return $data;
    }

    static public function responArray($item)
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

    static public function getIndexing($internalValue, $internalType, $language = null)
    {
        $query = 'SELECT id FROM `corepulse_indexing` where `internalValue` = ? AND `internalType` = ? ';
        $params = [ $internalValue, $internalType ];

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

    static public function saveIndex($indexing, $params)
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

    static public function filterIndexingStatus($listing)
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
            $data = $item->getDataJson();
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
                    }
                }
            }

            $datas['data'][] = $data;
        }

        $countsTotal = array_map('array_sum', $counts); // Tổng số lần xuất hiện của mỗi giá trị
        $percents = [];

        foreach ($counts as $key => $values) {
            $total = $countsTotal[$key];
            $percents[$key] = array_map(function ($value) use ($total) {
                return (int)round(($value / $total) * 100);
            }, $values);
        }

        $columns = ['url', 'indexingState', 'coverageState', 'verdict'];
        $fields = [];
        foreach ($columns as $key => $value) {
            $fields[] = [
                'key' => $value,
                'tooltip' => '',
                'title' => $value,
                'removable' => true,
                'searchType' => 'Input',
            ];
        }

        $datas['percents'] = $percents;
        $datas['counts'] = $counts;
        $datas['colors'] = $colors;
        $datas['fields'] = $fields;

        return $datas;
    }

    static public function getAccessToken()
    {
        $config = self::authConfigData();
        $privateKey = $config['private_key'];
        $payload = array(
            "iss" =>  $config['client_email'],
            "scope" => "https://www.googleapis.com/auth/indexing https://www.googleapis.com/auth/webmasters",
            "aud" => $config['token_uri'],
            "exp" => time() + 3600,
            "iat" => time()
        );

        $jwt = JWT::encode($payload, $privateKey, 'RS256', $config['private_key_id']);

        $data = array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        );

        $url = 'https://oauth2.googleapis.com/token';

        $connect = APIService::curl($url, 'POST', $data);
        $connect = json_decode($connect, true);

        return isset($connect['access_token']) ? $connect['access_token'] : false;
    }

    static public function submitType($idsOrId, $type)
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
            foreach($ids as $key => $id) {
                $option = Indexing::getById($id);
                if ($option) {
                    switch ($type) {
                        case 'delete':
                            $option->delete();
                            break;

                        case 'update-submit':
                            $dataOld["response-$contentId+$key"] = $option;

                            $url = $option->getUrl();

                            $requestContent = json_encode(["url" => $url, "type" => $updateKey]);
                            $batchRequestData .= "--$boundary\r\n";
                            $batchRequestData .= "Content-Type: application/http\r\nContent-Transfer-Encoding: binary\r\n";
                            $batchRequestData .= "Content-ID: <$contentId+$key>\r\n\r\n";
                            $batchRequestData .= "POST /v3/urlNotifications:publish\r\n";
                            $batchRequestData .= "Content-Type: application/json\r\naccept: application/json\r\n";
                            $batchRequestData .= "content-length: " . strlen($requestContent ) . "\r\n\r\n";
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
                            $batchRequestData .= "content-length: " . strlen($requestContent ) . "\r\n\r\n";
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

            if ($type != 'delete') {
                $token = GoogleServices::getAccessToken();

                $batchRequestHeaders = [
                    'Content-Length: ' . strlen($batchRequestData),
                    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
                    'Authorization: Bearer ' . $token
                ];
                $urlRequest = 'https://indexing.googleapis.com/batch';

                $requestBody = APIService::curl($urlRequest, 'POST', $batchRequestData, $batchRequestHeaders);

                if ($type == 'inspection') {
                    $batchSearchHeaders = [
                        'Content-Length: ' . strlen($batchSearchData),
                        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
                        'Authorization: Bearer ' . $token
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
            }
        } else {
            $id = $idsOrId;
            $option = Indexing::getById($id);
            if ($option) {
                switch ($type) {
                    case 'delete':
                        $option->delete();
                        break;

                    case 'update-submit':
                        $params = [
                            'type' => 'update',
                            'indexing' => $option,
                        ];

                        $data = GoogleServices::submitIndex($params, true);

                        break;

                    case 'delete-submit':
                        $params = [
                            'type' => 'delete',
                            'indexing' => $option,
                        ];

                        $data = GoogleServices::submitIndex($params, true);
                        break;

                    case 'inspection':
                        $params = [
                            'type' => 'update',
                            'indexing' => $option,
                        ];

                        $data = GoogleServices::submitIndex($params, true);

                        break;

                    default:
                        break;
                }
            }
        }

        return true;
    }
}

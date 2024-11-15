<?php

namespace CorepulseBundle\Services;

use Google\Service\AIPlatformNotebooks\Status;
use Pimcore\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;

class APIService
{
    //========================> SUPPORT METHOD <==============================
    static public function post($url, $method, $data = null, $header = null)
    {
        try {
            $response = self::process($url,$method,$data,$header);
            $response = json_decode($response, true);
            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    static public function process($url, $method, $params = null,$header = null)
    {
        try {
            $client = new Client([
                'verify' => false
            ]);
            $response = $client->request($method, $url, [
                'headers' => $header,
                'json' => $params
            ]);

            return (string)$response->getBody();
        } catch (\Exception $e) {
            $response=['error'=>'errors.social.unauthenticated'];
            return $response = json_encode($response, true);
        }
    }

    static public function curl($url, $method, $params = null, $header = [])
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 1000,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_SSL_VERIFYPEER => false, // Tắt kiểm tra chứng chỉ SSL
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_PROXY => $_ENV['SSO_PROXY'] ?: true,
                CURLOPT_NOPROXY => $_ENV['SSO_NOPROXY'] ?: true,
            ));
            $response = curl_exec($curl);

            curl_close($curl);

            // $response = json_decode($response, true);

            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

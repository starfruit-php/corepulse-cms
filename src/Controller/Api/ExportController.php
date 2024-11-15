<?php

namespace CorepulseBundle\Controller\Api;

use Pimcore\Db;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * @Route("/export")
 */
class ExportController extends BaseController
{
     /**
     * @Route("/excel", name="corepulse_api_export_excel", methods={"GET","POST"})
     */
    public function exportAction(Request $request)
    {
        try {
            $condition = [
                'data' => 'required',
                'fields' => 'required',
                'fileName' => '',
            ];

            $errorMessages = $this->validator->validate($condition, $request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $response = new Response();
            $filterData = [];
            $data = $request->get('data'); //dữ liệu cần export
            $fields = $request->get('fields'); //mảng các field cần export
            $fileName = $request->get('fileName') ? $request->get('fileName') : 'file-export';
            $header = [];

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] = json_decode($value);
                }

                $fields = json_decode($fields);
                foreach ($data as $key => $value) {
                    $result = [];
                    foreach ($fields as $item) {
                        if ($item->removable) {
                            $v = $item->key;
                            $result[$item->key] = isset($value->$v) ? $value->$v : '';

                            if (!in_array($v, $header)) {
                                $header[] = $v;
                            }
                        }
                    }

                    $filterData[] = $result;
                }

                array_unshift($filterData, $header);
                $spreadsheet = self::getExcel($filterData);

                $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '.xlsx"');

                // Lưu tệp Excel
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }

            return $response;

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

     //xuất excel
     static public function getExcel($data)
     {
         ob_start();

         $spreadsheet = new Spreadsheet();
         $sheet = $spreadsheet->getActiveSheet();

         // Duyệt qua dữ liệu và ghi vào các ô tương ứng
         foreach ($data as $rowIndex => $row) {
             $i = 0;

             foreach ($row as $colIndex => $value) {
                 if (is_array($value)) {
                     $value = json_encode($value);
                 }
                 $i++;
                 $cellCoordinate = Coordinate::stringFromColumnIndex($i) . ($rowIndex + 1);
                 $sheet->setCellValue($cellCoordinate, $value);
             }
         }

         return $spreadsheet;
     }

}

<?php

namespace CorepulseBundle\Services;

use Pimcore\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use CorepulseBundle\Services\Helper\Text\PrettyText;

class ReportServices
{
    static public function getReport($report)
    {
        $data = [];
        if ($report->getDataSourceConfig() !== null) {
            $data = [
                'id' => htmlspecialchars($report->getName()),
                'niceName' => htmlspecialchars($report->getNiceName()),
                'iconClass' => htmlspecialchars($report->getIconClass()),
                'group' => htmlspecialchars($report->getGroup()),
                'groupIconClass' => htmlspecialchars($report->getGroupIconClass()),
                'menuShortcut' => $report->getMenuShortcut(),
                'reportClass' => htmlspecialchars($report->getReportClass()),
            ];
        }

        return $data;
    }



    static public function getSql($config, $conditions = '', $params = [])
    {
        $sql = '';
        $type = $config->type;
        if ($type == 'sql') {
            $sql = 'SELECT ' . $config->sql . ' FROM ' . $config->from;

            if ($conditions && $config->where) {
                $sql .= ' WHERE ' . $config->where . ' AND ' . $conditions;
            } elseif ($conditions) {
                $sql .= ' WHERE ' . $conditions;
            } elseif ($config->where) {
                $sql .= ' WHERE ' . $config->where;
            }

            // if ($orderkey && $order) {
            // dd($config->sql, $orderkey, $order);
            // $sql .= ' ORDER BY ' . $orderkey . ' ' . $order;
            // $sql .= ' ORDER BY date asc';
            // } else {
            if ($config->groupby) {
                $sql .= ' GROUP BY ' . $config->groupby;
            }
            // }

            $db = Db::get();
            $data = $db->fetchAllAssociative($sql, $params);

            return $data;
        }

        return null;
    }

    static public function getColumn($column)
    {
        $data = [];
        foreach ($column as $key => $value) {
            $data[] = [
                'key' => $value['name'],
                'tooltip' => '',
                'title' => $value['name'],
                'removable' => true,
                'searchType' => 'Input',
            ];
        }

        return $data;
    }

    static public function getChart($chart)
    {
        $data = [];
        if ($chart->getChartType() == 'bar' || $chart->getChartType() == 'line') {
            $data = [
                'type' => $chart->getChartType(),
                'label' => $chart->getXAxis(),
                'column' => $chart->getYAxis(),
            ];
        } elseif ($chart->getChartType() == 'pie') {
            $data = [
                'type' => $chart->getChartType(),
                'label' => $chart->getPieLabelColumn(),
                'column' => $chart->getPieColumn(),
            ];
        }

        return $data;
    }

    static public function getChartData($listing, $chart, $title = '')
    {
        $chartData = [];
        $labels = [];
        $datas = [];
        $colors = [];

        foreach ($listing as $key => $value) {
            if ($chart['label']) {
                $labels[] = $value[$chart['label']];
            }
        }

        if ($chart['type'] == 'pie') {
            foreach ($listing as $key => $value) {
                $datas[] = $value[$chart['column']];
            }

            $chartData['series'] =  $datas;
        } else {
            foreach ($chart['column'] as $k => $i) {
                $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                while (in_array($color, $colors)) {
                    $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                }
                $colors[] = $color;

                $data = [];
                foreach ($listing as $key => $value) {
                    $data[] = $value[$i];
                }

                $datas[] = [
                    'name' => $i,
                    'data' => $data
                ];
            }

            $chartData['series'] =  $datas;
        }

        $chartData['categories'] = $labels;
        $chartData['colors'] = $colors;
        $chartData['text'] = $chart['label'];
        $chartData['title'] = $title;
        $chartData['labels'] = $labels;

        return $chartData;
    }

    public static function getFieldExport($column)
    {
        $data = [];
        foreach ($column as $key => $value) {
            $data[] = $value['name'];
        }

        return $data;
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
                $i++;
                $sheet->setCellValueByColumnAndRow($i, (int)$rowIndex + 1, $value);
            }
        }

        return $spreadsheet;
    }
}

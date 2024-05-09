<?php

namespace Dux\Utils;

use App\Safe\Web\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Http\Message\ResponseInterface;

class ExcelExport
{

    /**
     * @var Sheet[]
     */
    public array $sheet = [];

    public \PhpOffice\PhpSpreadsheet\Spreadsheet $excel;
    public function __construct()
    {
        $this->excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    }


    public function sheet(Sheet $sheet): static
    {
        $this->sheet[] = $sheet;
        return $this;
    }

    public function getObject(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        return $this->excel;
    }

    public function send(string $name, ResponseInterface $response)
    {
        foreach ($this->sheet as $k => $sheet) {
            $sheet->send($this->excel, $k);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->excel);
        header('Content-Type:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition:attachment; filename=' . rawurlencode($name . '-' . date('YmdHis')) . '.xlsx');
        header('Cache-Control:max-age=0');

        $output = fopen('php://output', 'w');
        $writer->save($output);
        return $response;
    }


}
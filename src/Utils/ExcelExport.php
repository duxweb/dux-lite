<?php

namespace Dux\Utils;

use Dux\Utils\Excel\Sheet;
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

        $output = fopen('php://output', 'rw+');
        $writer->save($output);

        $stream = new \Slim\Psr7\Stream($output);

        $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Transfer-Encoding', 'Binary')
            ->withHeader('Content-Disposition', 'attachment; filename='.date('YmdHis').'.zip')
            ->withHeader('Content-Length', filesize($output))
            ->withBody($stream);

        return $response;
    }

    public function getFile(): string
    {
        foreach ($this->sheet as $k => $sheet) {
            $sheet->send($this->excel, $k);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->excel);

        $output = tempnam(sys_get_temp_dir(), 'spreadsheet');
        $writer->save($output);
        return $output;
    }


}
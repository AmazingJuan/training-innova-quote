<?php

namespace App\Exports;

use App\Models\Client;
use App\Utils\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ClientsExport implements FromQuery, WithColumnWidths, WithCustomStartCell, WithDrawings, WithEvents, WithHeadings, WithMapping, WithTitle
{
    private const HEADER_ROW = 5;

    private const FIRST_DATA_ROW = 6;

    private const LAST_COLUMN = 'J';

    // Brand palette
    private const COLOR_PRIMARY = '0473C2';   // logo blue (title + header band)

    private const COLOR_TITLE = '1F2937';     // slate-800

    private const COLOR_ZEBRA = 'EAF3FB';     // very light blue

    private const COLOR_BORDER = 'D1D5DB';    // gray-300

    private const COLOR_MUTED = '6B7280';     // gray-500

    /**
     * @param  array{search?:string, company?:string, business_unit?:string}  $filters
     */
    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        return Client::query()
            ->withClientStats()
            ->applyClientFilters($this->filters)
            ->orderByActivity();
    }

    public function title(): string
    {
        return 'Clientes';
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Razón social',
            'NIT',
            'Actividad económica',
            'Cargo',
            'Correo electrónico',
            'Teléfono',
            'Cotizaciones',
            'Última cotización',
        ];
    }

    /**
     * @param  Client  $client
     * @return array<int, mixed>
     */
    public function map($client): array
    {
        $lastQuotation = $client->getAttribute('last_quotation_at');

        return [
            $client->getId(),
            $client->getName(),
            $client->getCompany() ?: '—',
            $client->getNit() ?: '—',
            $client->getEconomicActivity() ?: '—',
            $client->getRole() ?: '—',
            $client->getEmail(),
            $client->getPhone() ?: '—',
            (int) $client->getAttribute('quotations_count'),
            $lastQuotation ? Carbon::parse($lastQuotation)->format('d/m/Y') : '—',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 26,
            'C' => 24,
            'D' => 16,
            'E' => 24,
            'F' => 26,
            'G' => 34,
            'H' => 20,
            'I' => 14,
            'J' => 18,
        ];
    }

    /**
     * @return array<int, Drawing>
     */
    public function drawings(): array
    {
        $logoPath = Brand::logoPath();

        if (! is_file($logoPath)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Training Corporation');
        $drawing->setPath($logoPath);
        $drawing->setHeight(56);
        $drawing->setCoordinates('F1');
        $drawing->setOffsetX(12);
        $drawing->setOffsetY(8);

        return [$drawing];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;

                $highestRow = $sheet->getHighestRow();
                $hasData = $highestRow >= self::FIRST_DATA_ROW;
                $lastDataRow = $hasData ? $highestRow : self::HEADER_ROW;
                $total = $hasData ? ($highestRow - self::HEADER_ROW) : 0;

                /* ---- Title ---- */
                $sheet->mergeCells('A1:D1');
                $sheet->setCellValue('A1', 'REPORTE DE CLIENTES');
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => self::COLOR_PRIMARY]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                /* ---- Metadata band ---- */
                $sheet->setCellValue('A2', 'Fecha de generación:');
                $sheet->setCellValue('C2', now()->translatedFormat('d/m/Y h:i a'));
                $sheet->setCellValue('A3', 'Total de registros:');
                $sheet->setCellValue('C3', $total);

                $sheet->getStyle('A2:A3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_PRIMARY]],
                ]);
                $sheet->getStyle('C2:C3')->applyFromArray([
                    'font' => ['color' => ['rgb' => self::COLOR_TITLE]],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(6);

                /* ---- Header row ---- */
                $headerRange = 'A'.self::HEADER_ROW.':'.$last.self::HEADER_ROW;
                $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(22);
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_PRIMARY]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_PRIMARY]]],
                ]);

                $sheet->setAutoFilter($headerRange);
                $sheet->freezePane('A'.self::FIRST_DATA_ROW);

                /* ---- Data ---- */
                if ($hasData) {
                    $dataRange = 'A'.self::FIRST_DATA_ROW.':'.$last.$lastDataRow;
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDER]]],
                    ]);

                    // Center the ID, count and date columns
                    $sheet->getStyle('A'.self::FIRST_DATA_ROW.':A'.$lastDataRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('I'.self::FIRST_DATA_ROW.':J'.$lastDataRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Zebra striping on even rows
                    for ($row = self::FIRST_DATA_ROW; $row <= $lastDataRow; $row++) {
                        if (($row - self::FIRST_DATA_ROW) % 2 === 1) {
                            $sheet->getStyle('A'.$row.':'.$last.$row)->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_ZEBRA]],
                            ]);
                        }
                        $sheet->getRowDimension($row)->setRowHeight(18);
                    }
                } else {
                    $emptyRow = self::FIRST_DATA_ROW;
                    $sheet->mergeCells('A'.$emptyRow.':'.$last.$emptyRow);
                    $sheet->setCellValue('A'.$emptyRow, 'No hay clientes que coincidan con los filtros aplicados.');
                    $sheet->getStyle('A'.$emptyRow)->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => self::COLOR_MUTED]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                /* ---- Footer note ---- */
                $footerRow = ($hasData ? $lastDataRow : self::FIRST_DATA_ROW) + 2;
                $sheet->mergeCells('A'.$footerRow.':'.$last.$footerRow);
                $sheet->setCellValue(
                    'A'.$footerRow,
                    'Este reporte contiene los datos de los clientes que han solicitado una cotización a través de la plataforma.'
                );
                $sheet->getStyle('A'.$footerRow)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => self::COLOR_MUTED]],
                ]);
            },
        ];
    }
}

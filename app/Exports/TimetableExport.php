<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel export of a timetable: one summary sheet + one sheet per student group.
 */
class TimetableExport implements WithEvents, WithMultipleSheets
{
    use Exportable;

    public function __construct(private array $data)
    {
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $sheets = [new SummarySheet($this->data)];

        foreach ($this->data['counts']['byGroup']->keys() as $groupName) {
            $sheets[] = new GroupSheet($this->data, $groupName);
        }

        return $sheets;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Auto-size columns of all sheets.
                foreach ($event->sheet->getParent()->getSheetNames() as $sheetName) {
                    $sheet = $event->sheet->getParent()->getSheetByName($sheetName);
                    foreach ($sheet->getColumnIterator() as $column) {
                        $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                    }
                }
            },
        ];
    }
}

class SummarySheet implements FromCollection, WithTitle
{
    use Exportable;

    public function __construct(private array $data)
    {
    }

    public function collection()
    {
        $rows = collect([
            ['Emploi du temps', $this->data['program'] . ' — ' . $this->data['semester']->name],
            ['Année universitaire', $this->data['academicYear']],
            ['Exporté le', now()->format('d/m/Y H:i')],
            ['Nombre total de séances', count($this->data['entries'])],
            [],
            ['Jour', 'Créneau', 'Cours', 'Groupe', 'Enseignant', 'Salle'],
        ]);

        foreach ($this->data['entries'] as $entry) {
            $rows->push([app(\App\Services\TimetableExportService::class)->translateDay($entry->day_name), $entry->timeslot_name, $entry->module, $entry->groupe, $entry->professeur, $entry->salle]);
        }

        return $rows;
    }

    public function title(): string
    {
        return mb_substr('Récapitulatif', 0, 31);
    }
}

class GroupSheet implements FromCollection, WithTitle
{
    use Exportable;

    public function __construct(private array $data, private string $groupName)
    {
    }

    public function collection()
    {
        $rows = collect([
            ['Emploi du temps — ' . $this->groupName],
            ['Filière', $this->data['program']],
            ['Semestre', $this->data['semester']->name],
            ['Année universitaire', $this->data['academicYear']],
            [],
            ['Jour', 'Créneau', 'Cours', 'Enseignant', 'Salle'],
        ]);

        foreach ($this->data['entries']->where('groupe', $this->groupName) as $entry) {
            $rows->push([app(\App\Services\TimetableExportService::class)->translateDay($entry->day_name), $entry->timeslot_name, $entry->module, $entry->professeur, $entry->salle]);
        }

        return $rows;
    }

    public function title(): string
    {
        $title = transliterator_transliterate('Any-Latin; Latin-ASCII; Upper()', $this->groupName);
        $title = preg_replace('/[^A-Z0-9]+/', '_', $title);

        return mb_substr('GRP_' . trim($title, '_'), 0, 31);
    }
}

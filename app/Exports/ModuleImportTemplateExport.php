<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ModuleImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['name', 'code', 'program_code', 'semester_number', 'academic_year_name', 'type', 'weekly_hours', 'professor_emails'];
    }

    public function array(): array
    {
        return [[
            'Mathématiques générales', 'MAT101', 'LST-SM', 1, '2026/2027', 'cours', 3, 'prof.exemple@universite.ma',
        ]];
    }
}

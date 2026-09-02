<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfessorImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['name', 'email', 'password', 'max_weekly_hours', 'max_daily_minutes', 'module_codes'];
    }

    public function array(): array
    {
        return [[
            'Dr. Exemple', 'prof.exemple@universite.ma', 'MotDePasse123', 12, 360, 'MAT101;PHY102',
        ]];
    }
}

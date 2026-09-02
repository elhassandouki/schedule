<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProfessorsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public array $rowErrors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $data = $this->normaliseRow($row->toArray());
            $errors = $this->validateRow($data);

            $professor = !empty($data['email'])
                ? User::where('email', $data['email'])->first()
                : null;

            if ($professor && $professor->role !== 'prof') {
                $errors[] = 'Cette adresse email appartient déjà à un utilisateur qui n’est pas professeur.';
            }
            if (!$professor && empty($data['password'])) {
                $errors[] = 'Le mot de passe est obligatoire pour créer un nouveau professeur.';
            }

            $moduleCodes = $this->splitList($data['module_codes'] ?? null);
            $modules = collect();
            if ($moduleCodes !== []) {
                $modules = \DB::table('modules')->whereIn('code', $moduleCodes)->get();
                $foundCodes = $modules->pluck('code')->all();
                foreach (array_values(array_diff($moduleCodes, $foundCodes)) as $code) {
                    $errors[] = "Le module « {$code} » n’existe pas.";
                }
            }

            if ($errors !== []) {
                $this->rowErrors[] = ['row' => $line, 'messages' => array_values(array_unique($errors))];
                continue;
            }

            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'prof',
            ];
            foreach (['max_weekly_hours', 'max_daily_minutes'] as $field) {
                if ($data[$field] !== null && $data[$field] !== '') $attributes[$field] = (int) $data[$field];
            }
            if (!empty($data['password'])) $attributes['password'] = Hash::make($data['password']);

            if ($professor) {
                $professor->update($attributes);
                $this->updated++;
            } else {
                $professor = User::create($attributes);
                $this->created++;
            }

            if ($moduleCodes !== []) {
                $professor->modules()->sync($modules->pluck('id')->all());
            }
        }

        if ($this->rowErrors !== []) {
            throw new ImportRowsException($this->rowErrors);
        }
    }

    private function validateRow(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'max_weekly_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'max_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'module_codes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $validator->errors()->all();
    }

    private function normaliseRow(array $row): array
    {
        return [
            'name' => $this->value($row, ['name', 'nom', 'nom_complet']),
            'email' => $this->value($row, ['email', 'e_mail']),
            'password' => $this->value($row, ['password', 'mot_de_passe', 'mot_passe']),
            'max_weekly_hours' => $this->value($row, ['max_weekly_hours', 'max_heures_semaine', 'heures_max_semaine']),
            'max_daily_minutes' => $this->value($row, ['max_daily_minutes', 'max_minutes_jour', 'minutes_max_jour']),
            'module_codes' => $this->value($row, ['module_codes', 'codes_modules', 'modules']),
        ];
    }

    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) return is_string($row[$key]) ? trim($row[$key]) : $row[$key];
        }
        return null;
    }

    private function splitList(mixed $value): array
    {
        if ($value === null || trim((string) $value) === '') return [];
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;|\n]+/', (string) $value)))));
    }
}

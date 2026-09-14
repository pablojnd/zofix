<?php

namespace Database\Seeders;

use App\Models\SveParameter\SveTariffCode;
use App\Models\SveParameter\SveUnitOfMeasurement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SveParametersSeeder extends Seeder
{
    private const CSV_DIR = __DIR__.'/Csv';

    /**
     * Seeds the SVE parameter catalogs from the CSV files shipped with the
     * application. Rows are related by "code" in the CSVs but persisted as
     * ULID foreign keys; an unknown reference aborts the whole load.
     *
     * Idempotency strategy: updateOrCreate keyed on the unique "code" column,
     * so re-running the seeder updates existing rows (keeping their ULIDs and
     * any references pointing at them) instead of duplicating them.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $unitIds = $this->seedUnitOfMeasurements();
            $this->seedTariffCodes($unitIds);
        });
    }

    /**
     * @return array<string, string> Unit code => unit ULID
     */
    private function seedUnitOfMeasurements(): array
    {
        $unitIds = [];

        foreach ($this->csvRows('unidades_de_medida.csv') as $dataRow => $fields) {
            [$code, $name, $description, $printLabel, $isActive] = $fields;

            $unit = SveUnitOfMeasurement::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $this->nullable($description),
                    // The CSV has no hierarchy column and its print_label is
                    // the unit sigla; codigos_arancelarios.csv denormalizes
                    // each unit as (code, name, print_label), matching
                    // (code, unit_name, unit_sigla) here.
                    'unit_name' => $name,
                    'unit_sigla' => $printLabel,
                    'is_active' => $this->toBoolean($isActive),
                ],
            );

            $unitIds[$code] = $unit->getKey();
        }

        // Guarantee the "no unit of measurement" record (code 0) so empty-unit
        // lookups and integrity checks always find a match.
        $unitIds['0'] = SveUnitOfMeasurement::updateOrCreate(
            ['code' => '0'],
            [
                'name' => 'SIN UNIDAD DE MEDIDA',
                'description' => 'SIN UNIDAD DE MEDIDA',
                'unit_name' => 'SIN UNIDAD DE MEDIDA',
                'unit_sigla' => 'SIN UNI',
                'is_active' => true,
            ],
        )->getKey();

        return $unitIds;
    }

    /**
     * @param  array<string, string>  $unitIds
     */
    private function seedTariffCodes(array $unitIds): void
    {
        foreach ($this->csvRows('codigos_arancelarios.csv') as $dataRow => $fields) {
            [$code, $name, $description, $unitCode, , , $isActive] = $fields;

            $unitId = null;

            if ($unitCode !== '') {
                $unitId = $unitIds[$unitCode] ?? throw new RuntimeException(
                    sprintf(
                        'SVE parameters seed: missing unit reference in [codigos_arancelarios.csv], row %d (tariff code [%s]): unit code [%s] does not exist in [unidades_de_medida.csv].',
                        $dataRow,
                        $code,
                        $unitCode,
                    ),
                );
            }

            SveTariffCode::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $this->nullable($description),
                    'sve_unit_of_measurement_id' => $unitId,
                    'is_active' => $this->toBoolean($isActive),
                ],
            );
        }
    }

    /**
     * Streams the CSV file, skipping blank lines and rejecting malformed rows.
     *
     * @return \Generator<int, list<string>> 1-based data row number => trimmed fields
     */
    private function csvRows(string $filename): \Generator
    {
        $handle = fopen(self::CSV_DIR.'/'.$filename, 'rb');

        if ($handle === false) {
            throw new RuntimeException("SVE parameters seed: cannot open CSV file [{$filename}].");
        }

        try {
            $header = $this->csvRow($handle);

            if ($header === false) {
                throw new RuntimeException("SVE parameters seed: CSV file [{$filename}] is empty.");
            }

            $dataRow = 0;

            while (($fields = $this->csvRow($handle)) !== false) {
                $fields = array_map(trim(...), $fields);

                if (implode('', $fields) === '') {
                    continue;
                }

                $dataRow++;

                if (count($fields) !== count($header)) {
                    throw new RuntimeException(sprintf(
                        'SVE parameters seed: malformed row in [%s], row %d: expected %d fields, got %d.',
                        $filename,
                        $dataRow,
                        count($header),
                        count($fields),
                    ));
                }

                if ($fields[0] === '' || $fields[1] === '') {
                    throw new RuntimeException(sprintf(
                        'SVE parameters seed: row without code or name in [%s], row %d.',
                        $filename,
                        $dataRow,
                    ));
                }

                yield $dataRow => $fields;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<string>|false
     */
    private function csvRow($handle): array|false
    {
        // PHP 8.5 requires the $escape argument explicitly.
        return fgetcsv($handle, null, ',', '"', '\\');
    }

    private function nullable(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    private function toBoolean(string $value): bool
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolean === null) {
            throw new RuntimeException("SVE parameters seed: invalid boolean value [{$value}] (expected true or false).");
        }

        return $boolean;
    }
}

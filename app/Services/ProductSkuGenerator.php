<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySkuSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProductSkuGenerator
{
    private const MAX_RESERVATION_ATTEMPTS = 10;

    /**
     * Generate and reserve the next SKU for a company.
     *
     * @param  Company|string  $company  A persisted company or its ULID.
     */
    public function generate(Company|string $company): string
    {
        $companyId = $this->resolveCompanyId($company);

        return DB::transaction(function () use ($companyId): string {
            if (! Company::query()->whereKey($companyId)->exists()) {
                throw new InvalidArgumentException("Company [{$companyId}] does not exist.");
            }

            $now = now();

            DB::table('company_sku_settings')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'prefix' => 'P',
                'format' => '{prefix}{number}',
                'sequence_length' => 6,
                'next_sequence' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($reservationAttempt = 0; $reservationAttempt < self::MAX_RESERVATION_ATTEMPTS; $reservationAttempt++) {
                $setting = CompanySkuSetting::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->first();

                if ($setting === null) {
                    throw new RuntimeException("SKU settings could not be created for company [{$companyId}].");
                }

                $rules = $this->validatedRules($setting);
                $nextSequence = $this->validateNextSequence($setting->next_sequence, $rules['sequence_length']);
                $sku = $this->buildSku(
                    $rules['prefix'],
                    $rules['format'],
                    $rules['sequence_length'],
                    $nextSequence,
                );

                $reserved = DB::table('company_sku_settings')
                    ->where('company_id', $companyId)
                    ->where('next_sequence', $nextSequence)
                    ->update([
                        'next_sequence' => DB::raw('next_sequence + 1'),
                        'updated_at' => now(),
                    ]);

                if ($reserved === 1) {
                    return $sku;
                }
            }

            throw new RuntimeException("SKU sequence reservation could not be completed for company [{$companyId}].");
        }, attempts: 3);
    }

    /**
     * Validate an existing SKU against a company's effective SKU rules.
     *
     * @throws InvalidArgumentException When the company or SKU is invalid.
     */
    public function validateExistingSku(string $sku, Company|string $company): void
    {
        $companyId = $this->resolveCompanyId($company);

        if (! Company::query()->whereKey($companyId)->exists()) {
            throw new InvalidArgumentException("Company [{$companyId}] does not exist.");
        }

        $setting = CompanySkuSetting::query()
            ->where('company_id', $companyId)
            ->first();
        $rules = $this->validatedRules($setting);

        if ($sku === '' || mb_strlen($sku) > 255) {
            throw new InvalidArgumentException('Product SKU must be between 1 and 255 characters.');
        }

        $pattern = preg_quote($rules['format'], '/');
        $pattern = str_replace(
            [preg_quote('{prefix}', '/'), preg_quote('{number}', '/')],
            [preg_quote($rules['prefix'], '/'), '[0-9]{'.$rules['sequence_length'].'}'],
            $pattern,
        );

        if (preg_match('/\A'.$pattern.'\z/D', $sku) !== 1) {
            throw new InvalidArgumentException("Product SKU [{$sku}] does not match Company [{$companyId}] SKU rules.");
        }
    }

    private function resolveCompanyId(Company|string $company): string
    {
        if ($company instanceof Company) {
            $companyId = $company->getKey();

            if (! $company->exists || ! is_string($companyId) || trim($companyId) === '') {
                throw new InvalidArgumentException('A persisted Company is required to generate a product SKU.');
            }

            return $companyId;
        }

        $companyId = trim($company);

        if ($companyId === '') {
            throw new InvalidArgumentException('A Company ID is required to generate a product SKU.');
        }

        return $companyId;
    }

    /**
     * @return array{prefix: string, format: string, sequence_length: int}
     */
    private function validatedRules(?CompanySkuSetting $setting): array
    {
        return [
            'prefix' => $this->validatePrefix($setting?->prefix ?? 'P'),
            'format' => $this->validateFormat($setting?->format ?? '{prefix}{number}'),
            'sequence_length' => $this->validateSequenceLength($setting?->sequence_length ?? 6),
        ];
    }

    private function buildSku(string $prefix, string $format, int $sequenceLength, int $nextSequence): string
    {
        $number = str_pad((string) $nextSequence, $sequenceLength, '0', STR_PAD_LEFT);
        $sku = str_replace(['{prefix}', '{number}'], [$prefix, $number], $format);

        if ($sku === '' || mb_strlen($sku) > 255) {
            throw new InvalidArgumentException('Generated product SKU must be between 1 and 255 characters.');
        }

        return $sku;
    }

    private function validatePrefix(mixed $prefix): string
    {
        if (! is_string($prefix) || $prefix === '' || mb_strlen($prefix) > 100 || preg_match('/\A[A-Z0-9]+\z/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Company SKU prefix must be a non-empty uppercase alphanumeric string of 100 characters or fewer.');
        }

        return $prefix;
    }

    private function validateFormat(mixed $format): string
    {
        if (! is_string($format) || $format === '' || mb_strlen($format) > 255) {
            throw new InvalidArgumentException('Company SKU format must be a non-empty string of 255 characters or fewer.');
        }

        preg_match_all('/\{([^{}]*)\}/', $format, $matches);
        $placeholders = $matches[1] ?? [];
        $literalFormat = str_replace(['{prefix}', '{number}'], '', $format);

        if (
            count($placeholders) !== 2
            || count(array_filter($placeholders, static fn (string $placeholder): bool => $placeholder === 'prefix')) !== 1
            || count(array_filter($placeholders, static fn (string $placeholder): bool => $placeholder === 'number')) !== 1
            || str_contains($literalFormat, '{')
            || str_contains($literalFormat, '}')
        ) {
            throw new InvalidArgumentException('Company SKU format must contain exactly one {prefix} and one {number} placeholder.');
        }

        return $format;
    }

    private function validateSequenceLength(mixed $sequenceLength): int
    {
        if (! is_int($sequenceLength) || $sequenceLength < 1 || $sequenceLength > 18) {
            throw new InvalidArgumentException('Company SKU sequence_length must be a positive integer between 1 and 18.');
        }

        return $sequenceLength;
    }

    private function validateNextSequence(mixed $nextSequence, int $sequenceLength): int
    {
        if (! is_int($nextSequence) || $nextSequence < 1) {
            throw new InvalidArgumentException('Company SKU next_sequence must be a positive integer.');
        }

        if (mb_strlen((string) $nextSequence) > $sequenceLength) {
            throw new InvalidArgumentException('Company SKU sequence is exhausted for the configured sequence_length.');
        }

        return $nextSequence;
    }
}

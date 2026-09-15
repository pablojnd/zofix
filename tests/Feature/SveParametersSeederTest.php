<?php

use App\Models\SveParameter\SveTariffCode;
use App\Models\SveParameter\SveUnitOfMeasurement;
use Database\Seeders\SveParametersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates tariff codes linked to their units of measurement', function (): void {
    $this->seed(SveParametersSeeder::class);

    $unit = SveUnitOfMeasurement::where('code', '6')->firstOrFail();
    $tariffCode = SveTariffCode::where('code', '00010100')->firstOrFail();

    expect(SveUnitOfMeasurement::count())->toBeGreaterThan(0)
        ->and(SveTariffCode::count())->toBeGreaterThan(0)
        ->and($tariffCode->code)->toBe('00010100')
        ->and($tariffCode->unitOfMeasurement->is($unit))->toBeTrue()
        ->and($unit->tariffCodes->contains($tariffCode))->toBeTrue();
});

it('keeps seeded rows and identifiers when run twice', function (): void {
    $this->seed(SveParametersSeeder::class);

    $unitCount = SveUnitOfMeasurement::count();
    $tariffCodeCount = SveTariffCode::count();
    $unitIds = SveUnitOfMeasurement::query()->orderBy('code')->pluck('id', 'code')->all();
    $tariffCodeIds = SveTariffCode::query()->orderBy('code')->pluck('id', 'code')->all();

    $this->seed(SveParametersSeeder::class);

    expect(SveUnitOfMeasurement::count())->toBe($unitCount)
        ->and(SveTariffCode::count())->toBe($tariffCodeCount)
        ->and(SveUnitOfMeasurement::query()->orderBy('code')->pluck('id', 'code')->all())->toBe($unitIds)
        ->and(SveTariffCode::query()->orderBy('code')->pluck('id', 'code')->all())->toBe($tariffCodeIds);
});

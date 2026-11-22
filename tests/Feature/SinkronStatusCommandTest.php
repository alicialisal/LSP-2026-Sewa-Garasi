<?php

namespace Tests\Feature;

use App\Domain\Enums\GarageStatus;
use App\Domain\Enums\RentalStatus;
use App\Domain\Models\Garage;
use App\Domain\Models\Rental;
use App\Repositories\GarageRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinkronStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21 00:05:00');
        CarbonImmutable::setTestNow('2026-11-21 00:05:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * FT-09 - Dua sewa sudah lewat: keluaran melaporkan 2 sewa diselesaikan,
     * garasinya kembali AVAILABLE; garasi dengan sewa berjalan tetap USED.
     */
    public function test_menyelesaikan_sewa_lewat_dan_membebaskan_garasi(): void
    {
        foreach (['C001', 'C002', 'C003'] as $kode) {
            Garage::factory()->car($kode)->used()->create();
        }
        $lewatA = Rental::factory()->periode('2026-11-17', '2026-11-19')->create(['garage_id' => 'C001', 'nomor_nota' => 'SEW-20261117-0001']);
        $lewatB = Rental::factory()->periode('2026-11-18', '2026-11-20')->create(['garage_id' => 'C002', 'nomor_nota' => 'SEW-20261118-0001']);
        $berjalan = Rental::factory()->periode('2026-11-20', '2026-11-22')->create(['garage_id' => 'C003', 'nomor_nota' => 'SEW-20261120-0001']);

        $this->artisan('garasi:sinkron-status')
            ->expectsOutputToContain('2 sewa diselesaikan')
            ->assertSuccessful();

        $repo = new GarageRepository();
        $this->assertSame(GarageStatus::AVAILABLE, $repo->cariId('C001')->status);
        $this->assertSame(GarageStatus::AVAILABLE, $repo->cariId('C002')->status);
        $this->assertSame(GarageStatus::USED, $repo->cariId('C003')->status);
        $this->assertSame(RentalStatus::SELESAI, $lewatA->fresh()->status);
        $this->assertSame(RentalStatus::SELESAI, $lewatB->fresh()->status);
        $this->assertSame(RentalStatus::AKTIF, $berjalan->fresh()->status);
    }

    public function test_tanpa_sewa_lewat_melaporkan_nol(): void
    {
        $this->artisan('garasi:sinkron-status')
            ->expectsOutputToContain('0 sewa diselesaikan')
            ->assertSuccessful();
    }
}

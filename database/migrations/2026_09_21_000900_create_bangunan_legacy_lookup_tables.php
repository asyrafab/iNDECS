<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS bangunan');

        Schema::create('bangunan.legacy_permohonan', function (Blueprint $table): void {
            $table->integer('pmn_id')->primary();
            $table->string('pmn_no_siri', 50)->unique();
            $table->string('pmn_no_rujukan', 50)->index();
            $table->text('pmn_tajuk')->nullable();
            $table->string('pmn_pemohon', 255)->nullable();
            $table->string('pmn_tuan_tanah', 255)->nullable();
            $table->string('pmn_no_lot', 150)->nullable();
            $table->string('pmn_tempat', 255)->nullable();
            $table->string('sd_mukim', 50)->nullable();
            $table->string('sd_daerah_kod', 10)->nullable()->index();
            $table->string('district', 100)->nullable()->index();
            $table->string('sk_kategori', 150)->nullable();
            $table->string('pmn_status', 20)->nullable()->index();
            $table->date('pmn_tkh_pohon')->nullable()->index();
            $table->date('pmn_tkh_bayar')->nullable();
            $table->date('pmn_tkh_lulus')->nullable();
            $table->date('pmn_tkh_tolak')->nullable();
            $table->date('pmn_tkh_batal')->nullable();
            $table->date('pmn_tkh_tarik_balik')->nullable();
            $table->date('pmn_tkh_tangguh')->nullable();
            $table->decimal('pmn_bayaran', 14, 2)->nullable();
            $table->decimal('pmn_bayaran_cagaran', 14, 2)->nullable();
            $table->string('pmn_no_rjk_perancang', 100)->nullable();
            $table->string('pmn_keputusan_osc', 100)->nullable();
            $table->string('pmn_keputusan_ydp', 100)->nullable();
            $table->boolean('is_excluded_test_record')->default(false)->index();
            $table->timestampTz('synced_at')->useCurrent();
        });

        DB::statement('CREATE INDEX legacy_permohonan_no_rujukan_trgm_idx ON bangunan.legacy_permohonan USING gin (pmn_no_rujukan gin_trgm_ops)');
        DB::statement('CREATE INDEX legacy_permohonan_no_siri_trgm_idx ON bangunan.legacy_permohonan USING gin (pmn_no_siri gin_trgm_ops)');
        DB::statement('CREATE INDEX legacy_permohonan_pemohon_trgm_idx ON bangunan.legacy_permohonan USING gin (pmn_pemohon gin_trgm_ops)');
        DB::statement('CREATE INDEX legacy_permohonan_no_lot_trgm_idx ON bangunan.legacy_permohonan USING gin (pmn_no_lot gin_trgm_ops)');
        DB::statement('CREATE INDEX legacy_permohonan_tempat_trgm_idx ON bangunan.legacy_permohonan USING gin (pmn_tempat gin_trgm_ops)');

        Schema::create('bangunan.legacy_perunding', function (Blueprint $table): void {
            $table->integer('prd_id')->primary();
            $table->string('prd_pmn_no_siri', 50)->index();
            $table->string('prd_perunding', 255)->nullable();
            $table->string('prd_pelan', 100)->nullable();
            $table->string('prd_arkitek', 150)->nullable();
            $table->string('prd_no_daftar', 50)->nullable()->index();
            $table->string('prd_tel', 50)->nullable();
            $table->timestampTz('synced_at')->useCurrent();
        });

        Schema::create('bangunan.legacy_bangunan', function (Blueprint $table): void {
            $table->integer('bgn_id')->primary();
            $table->string('bgn_pmn_no_siri', 50)->index();
            $table->string('bgn_pmn_no_rujukan', 50)->nullable()->index();
            $table->integer('bgn_unit')->default(0);
            $table->integer('bgn_tingkat')->default(0);
            $table->string('sb_jenis_binaan', 150)->nullable();
            $table->decimal('bgn_luas', 18, 2)->default(0);
            $table->decimal('bgn_jumlah', 18, 2)->default(0);
            $table->boolean('bgn_chk_spah')->default(false);
            $table->boolean('bgn_chk_ottv')->default(false);
            $table->boolean('bgn_chk_oku')->default(false);
            $table->timestampTz('synced_at')->useCurrent();
        });

        Schema::create('bangunan.legacy_ccc', function (Blueprint $table): void {
            $table->integer('ccc_id')->primary();
            $table->string('ccc_pmn_no_rujukan', 50)->index();
            $table->integer('sccc_id')->nullable();
            $table->string('sccc_jenis', 20)->nullable()->index();
            $table->string('sccc_komponen', 255)->nullable();
            $table->string('ccc_status', 5)->nullable();
            $table->date('ccc_tkh_ccc')->nullable();
            $table->timestampTz('synced_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bangunan.legacy_ccc');
        Schema::dropIfExists('bangunan.legacy_bangunan');
        Schema::dropIfExists('bangunan.legacy_perunding');
        Schema::dropIfExists('bangunan.legacy_permohonan');
        DB::statement('DROP SCHEMA IF EXISTS bangunan');
    }
};

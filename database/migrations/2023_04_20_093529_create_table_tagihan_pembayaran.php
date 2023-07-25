<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_tagihan_pembayaran', function (Blueprint $table) {
            $table->string('id_invoice', 7)->primary();
            $table->date('tanggal_invoice');
            $table->integer('nim');
            $table->integer('nominal_tagihan', unsigned: true);
            $table->string('keterangan', 25);
            $table->date('tanggal_invoice');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_tagihan_pembayaran');
    }
};

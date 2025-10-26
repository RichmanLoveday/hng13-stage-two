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
        Schema::create('country_currencies', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("capital")->nullable();
            $table->string("region")->nullable();
            $table->string("flag_url")->nullable();
            $table->integer("population");
            $table->string("country_code")->nullable();
            $table->string('currency_code')->nullable();
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->decimal("estimated_gdp", 16, 4)->nullable();

            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_currencies');
    }
};

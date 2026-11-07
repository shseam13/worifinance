<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('name_bn', 80)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('project_categories')->insert([
            ['name' => 'Residential', 'name_bn' => 'আবাসিক', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Commercial', 'name_bn' => 'বাণিজ্যিক', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hospitality', 'name_bn' => 'আতিথেয়তা', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Land', 'name_bn' => 'জমি', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mixed-use', 'name_bn' => 'মিশ্র ব্যবহার', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_categories');
    }
};

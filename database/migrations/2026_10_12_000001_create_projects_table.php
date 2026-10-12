<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('category', 80)->index();
            $table->string('project_type', 80)->nullable();
            $table->string('location');
            $table->string('location_bn')->nullable();
            $table->string('developer')->nullable();
            $table->string('developer_bn')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->text('summary')->nullable();
            $table->text('summary_bn')->nullable();
            $table->longText('description')->nullable();
            $table->longText('description_bn')->nullable();
            $table->unsignedBigInteger('target_units')->default(0);
            $table->unsignedBigInteger('available_units')->default(0);
            $table->boolean('is_sample')->default(false);
            $table->date('expected_handover_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('project_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('unit_price_bdt', 20, 2);
            $table->decimal('annual_rate_percent', 8, 4)->nullable();
            $table->decimal('estimated_return_min_percent', 8, 4)->nullable();
            $table->decimal('estimated_return_max_percent', 8, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
            $table->index(['project_id', 'effective_from']);
        });

        Schema::create('project_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('visibility', 24)->default('private');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('project_images');
        Schema::dropIfExists('project_terms');
        Schema::dropIfExists('projects');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client can attach the documents the encuadre asks for: a question of
 * type "file", and the files of each request, kept on the documents disk
 * (local for now, S3 later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->enum('type', ['text', 'number', 'date', 'file'])->default('text')->change();
        });

        Schema::create('quotation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quotation_proposal_order_id')->constrained()->cascadeOnDelete();
            // Key of the answer it was attached in (the question's label), to show it under that question.
            $table->string('question');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_documents');

        Schema::table('conditions', function (Blueprint $table) {
            $table->enum('type', ['text', 'number', 'date'])->default('text')->change();
        });
    }
};

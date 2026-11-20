<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documents a client attaches are no longer kept: they travel as
 * attachments of the notice to management and are deleted right after, so
 * neither the table nor the files on the documents disk have a reader left.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quotation_documents');
    }

    public function down(): void
    {
        Schema::create('quotation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quotation_proposal_order_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }
};

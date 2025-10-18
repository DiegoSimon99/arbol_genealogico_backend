<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_closure', function (Blueprint $table) {
            // IDs de las personas relacionadas
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');

            // Distancia entre ancestro y descendiente (0 = la misma persona)
            $table->unsignedInteger('depth');

            // Clave primaria compuesta
            $table->primary(['ancestor_id', 'descendant_id']);

            // Relaciones con la tabla people
            $table->foreign('ancestor_id')
                ->references('id')->on('people')
                ->onDelete('cascade');

            $table->foreign('descendant_id')
                ->references('id')->on('people')
                ->onDelete('cascade');

            // Índices para mejorar búsquedas por profundidad y relaciones
            $table->index(['ancestor_id', 'depth']);
            $table->index(['descendant_id', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_closure');
    }
};

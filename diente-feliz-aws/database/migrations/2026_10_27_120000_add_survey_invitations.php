<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('recalls',fn(Blueprint $t)=>$t->date('due_on')->nullable()->change());
        Schema::create('survey_invitations',function(Blueprint $t) {
            $t->id();
            $t->foreignId('survey_id')->constrained('surveys');
            $t->foreignId('patient_id')->constrained('patients');
            $t->foreignId('branch_id')->constrained('branches');
            $t->foreignId('created_by')->constrained('users');
            $t->char('token_hash',64)->unique();
            $t->text('questions');
            $t->string('channel',20);
            $t->timestampTz('expires_at');
            $t->timestampTz('responded_at')->nullable();
            $t->timestampsTz();
        });
        if (!DB::table('surveys')->where('name','Satisfacción de la atención')->exists()) {
            DB::table('surveys')->insert(['name'=>'Satisfacción de la atención','questions'=>"¿Cómo evalúa la atención recibida?\n¿Cómo evalúa la claridad de las explicaciones del profesional?\n¿Cómo evalúa la puntualidad de su atención?\n¿Cómo evalúa la limpieza y comodidad de la clínica?",'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void {
        Schema::dropIfExists('survey_invitations');
        // Keep optional dates and the template: reverting them would alter user records.
    }
};

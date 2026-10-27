<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('active')->default(true);
            $t->timestampTz('disabled_from')->nullable();
            $t->timestampTz('disabled_until')->nullable();
            $t->unsignedInteger('session_version')->default(1);
        });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->jsonb('permissions'); });
        Schema::create('role_user', function (Blueprint $t) { $t->foreignId('user_id')->constrained(); $t->foreignId('role_id')->constrained(); $t->primary(['user_id','role_id']); });
        Schema::create('branches', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('address'); $t->boolean('active')->default(true); $t->timestampsTz(); });
        Schema::create('branch_user', function (Blueprint $t) { $t->foreignId('user_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->primary(['user_id','branch_id']); });
        Schema::create('rooms', function (Blueprint $t) { $t->id(); $t->foreignId('branch_id')->constrained(); $t->string('name'); $t->string('equipment')->nullable(); $t->boolean('active')->default(true); $t->timestampsTz(); });
        Schema::create('professionals', function (Blueprint $t) { $t->id(); $t->foreignId('branch_id')->constrained(); $t->foreignId('user_id')->constrained(); $t->string('name'); $t->string('specialty'); $t->boolean('active')->default(true); $t->timestampTz('disabled_from')->nullable(); $t->timestampTz('disabled_until')->nullable(); $t->timestampsTz(); $t->unique(['branch_id','user_id']); });
        Schema::create('availabilities', function (Blueprint $t) { $t->id(); $t->foreignId('branch_id')->constrained(); $t->foreignId('professional_id')->constrained(); $t->foreignId('room_id')->constrained(); $t->timestampTz('starts_at'); $t->timestampTz('ends_at'); $t->timestampsTz(); });
        Schema::create('agreements', function (Blueprint $t) { $t->id(); $t->string('name'); $t->unsignedSmallInteger('discount_percent')->default(0); $t->date('expires_on'); $t->boolean('active')->default(true); $t->timestampsTz(); });
        Schema::create('patients', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('identifier')->unique(); $t->date('birth_date'); $t->text('email')->nullable(); $t->text('phone')->nullable(); $t->text('emergency_contact')->nullable(); $t->string('category')->default('General'); $t->foreignId('agreement_id')->nullable()->constrained(); $t->boolean('active')->default(true); $t->boolean('contact_consent')->default(false); $t->timestampsTz(); });
        Schema::create('branch_patient', function (Blueprint $t) { $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->primary(['patient_id','branch_id']); });
        Schema::create('appointments', function (Blueprint $t) { $t->id(); $t->foreignId('branch_id')->constrained(); $t->foreignId('patient_id')->constrained(); $t->foreignId('professional_id')->constrained(); $t->foreignId('room_id')->constrained(); $t->timestampTz('starts_at'); $t->timestampTz('ends_at'); $t->string('status')->default('Pendiente'); $t->string('reason'); $t->timestampsTz(); });
        Schema::create('clinical_entries', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->text('diagnosis'); $t->text('treatment'); $t->text('allergies')->nullable(); $t->text('medications')->nullable(); $t->text('history')->nullable(); $t->date('follow_up_on')->nullable(); $t->timestampsTz(); });
        Schema::create('comments', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->string('visibility'); $t->text('body'); $t->timestampsTz(); });
        Schema::create('tooth_records', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->unsignedSmallInteger('tooth'); $t->string('surface'); $t->string('condition'); $t->text('notes')->nullable(); $t->timestampsTz(); });
        Schema::create('documents', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->string('name'); $t->string('kind'); $t->string('path'); $t->string('mime'); $t->unsignedInteger('size'); $t->string('sha256',64); $t->timestampsTz(); });
        Schema::create('templates', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('kind'); $t->text('body'); $t->unsignedInteger('version')->default(1); $t->boolean('active')->default(true); $t->timestampsTz(); });
        Schema::create('consents', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->foreignId('template_id')->constrained(); $t->text('body'); $t->text('signer'); $t->string('document_hash',64); $t->timestampTz('signed_at'); $t->timestampsTz(); });
        Schema::create('budgets', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->string('name'); $t->text('details'); $t->unsignedBigInteger('gross_amount'); $t->unsignedSmallInteger('discount_percent')->default(0); $t->unsignedBigInteger('total'); $t->date('due_on'); $t->string('status')->default('Pendiente'); $t->timestampsTz(); });
        Schema::create('payments', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('budget_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->unsignedBigInteger('amount'); $t->string('method'); $t->uuid('idempotency_key')->unique(); $t->timestampsTz(); });
        Schema::create('refunds', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('payment_id')->constrained(); $t->foreignId('author_id')->constrained('users'); $t->unsignedBigInteger('amount'); $t->text('reason'); $t->uuid('idempotency_key')->unique(); $t->timestampsTz(); });
        Schema::create('recalls', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->string('reason'); $t->date('due_on'); $t->string('status'); $t->text('notes')->nullable(); $t->timestampsTz(); });
        Schema::create('messages', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->string('subject'); $t->text('body'); $t->string('status')->default('Pendiente'); $t->timestampTz('sent_at')->nullable(); $t->timestampsTz(); });
        Schema::create('surveys', function (Blueprint $t) { $t->id(); $t->string('name'); $t->text('questions'); $t->boolean('active')->default(true); $t->timestampsTz(); });
        Schema::create('survey_responses', function (Blueprint $t) { $t->id(); $t->foreignId('patient_id')->constrained(); $t->foreignId('branch_id')->constrained(); $t->foreignId('survey_id')->constrained(); $t->unsignedSmallInteger('score'); $t->text('answers'); $t->text('question_snapshot'); $t->timestampsTz(); });
        Schema::create('audit_events', function (Blueprint $t) { $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users'); $t->string('action'); $t->string('entity'); $t->unsignedBigInteger('entity_id')->nullable(); $t->text('changes')->nullable(); $t->string('ip',45)->nullable(); $t->timestampTz('created_at')->useCurrent(); });
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT valid_appointment_interval CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE availabilities ADD CONSTRAINT valid_availability_interval CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT positive_payment CHECK (amount > 0)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT positive_refund CHECK (amount > 0)');
        // Defense in depth against concurrent inserts, including clients outside the app.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        foreach (['professional_id','room_id','patient_id'] as $resource) {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_{$resource}_overlap EXCLUDE USING gist ({$resource} WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status <> 'Cancelada')");
        }
    }
    public function down(): void {
        foreach (['audit_events','survey_responses','surveys','messages','recalls','refunds','payments','budgets','consents','templates','documents','tooth_records','comments','clinical_entries','appointments','branch_patient','patients','agreements','availabilities','professionals','rooms','branch_user','branches','role_user','roles'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn(['active','disabled_from','disabled_until','session_version']));
    }
};

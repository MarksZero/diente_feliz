<?php
namespace Tests\Feature;

use App\Models\{User,Role,Branch,Record};
use App\Services\Modules;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Auth};
use Tests\TestCase;

class WorkflowRevisionTest extends TestCase
{
    use DatabaseTransactions;
    private User $staff;
    private Branch $branch;
    private Branch $other;
    private int $patient;

    protected function setUp(): void {
        parent::setUp();
        $this->branch=Branch::create(['name'=>'Sede prueba','address'=>'Calle prueba','active'=>true]);
        $this->other=Branch::create(['name'=>'Segunda prueba','address'=>'Otra calle','active'=>true]);
        $this->staff=User::factory()->create()->refresh();
        $role=Role::create(['name'=>'Prueba '.uniqid(),'permissions'=>Modules::permissions()]);
        $this->staff->roles()->attach($role);
        $this->staff->branches()->attach([$this->branch->id,$this->other->id]);
        $record=Record::for('patients');
        $record->fill(['name'=>'Paciente de prueba','identifier'=>'TEST-'.uniqid(),'birth_date'=>'2000-01-01','active'=>true,'contact_consent'=>true,'email'=>'prueba@example.test'])->save();
        $this->patient=$record->id;
        DB::table('branch_patient')->insert(['branch_id'=>$this->branch->id,'patient_id'=>$this->patient]);
        $this->actingAs($this->staff)->withSession(['session_version'=>$this->staff->session_version]);
    }

    public function test_forms_and_relevant_filters(): void {
        $this->get('/modules/budgets/create')->assertOk()->assertDontSee('name="name"',false);
        $this->get('/modules/patients/create')->assertOk()->assertSee('Código de paciente')->assertSee('No es un RUT');
        $this->get('/modules/appointments/create')->assertOk()->assertSee('name="duration"',false)->assertDontSee('name="ends_at"',false);
        foreach (['branches','rooms','professionals','templates','surveys','agreements'] as $module) {
            $this->get('/modules/'.$module)->assertOk()->assertDontSee('name="from"',false)->assertDontSee('name="to"',false);
        }
        $this->get('/modules/branches?address=Calle%20prueba&active=1')->assertOk()->assertSee('Sede prueba')->assertDontSee('Otra calle');
        $this->get('/modules/surveys/create')->assertOk()->assertSee('¿Cómo evalúa la atención recibida?');
        $this->get('/modules/responses')->assertOk()->assertDontSee('/modules/responses/create',false);
        $this->get('/modules/responses/create')->assertForbidden();
        $this->post('/modules/responses',[])->assertForbidden();
        $this->get('/modules/patients/'.$this->patient)->assertOk()->assertSee('Seleccionar incisivo, pieza 11')->assertSee('Seleccionar molar, pieza 18');
    }

    public function test_budget_title_optional_recall_and_message_warning(): void {
        $base=['patient_id'=>$this->patient,'branch_id'=>$this->branch->id];
        $this->post('/modules/budgets',$base+['details'=>'Control','gross_amount'=>10000,'due_on'=>'2030-01-01','status'=>'Pendiente','name'=>'No debe usarse'])->assertRedirect();
        $this->assertDatabaseHas('budgets',['patient_id'=>$this->patient,'name'=>'Presupuesto · Paciente de prueba','total'=>10000]);
        $this->post('/modules/recalls',$base+['reason'=>'Control','status'=>'Pendiente'])->assertRedirect();
        $this->assertDatabaseHas('recalls',['patient_id'=>$this->patient,'due_on'=>null]);
        $this->post('/modules/messages',['patient_id'=>$this->patient,'branch_id'=>$this->other->id,'subject'=>'Aviso','body'=>'Prueba'])->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseHas('messages',['patient_id'=>$this->patient,'branch_id'=>$this->other->id,'subject'=>'Aviso']);
        $this->assertDatabaseMissing('branch_patient',['patient_id'=>$this->patient,'branch_id'=>$this->other->id]);
        $this->post('/modules/clinical',['patient_id'=>$this->patient,'branch_id'=>$this->other->id,'diagnosis'=>'Prueba','treatment'=>'Prueba'])->assertSessionHasErrors('patient_id');
        $foreign=Branch::create(['name'=>'Fuera','address'=>'Fuera','active'=>true]);
        $this->post('/modules/messages',['patient_id'=>$this->patient,'branch_id'=>$foreign->id,'subject'=>'Aviso','body'=>'Prueba'])->assertNotFound();
    }

    public function test_appointment_computes_end_and_rejects_conflicts(): void {
        $pro=DB::table('professionals')->insertGetId(['user_id'=>$this->staff->id,'branch_id'=>$this->branch->id,'name'=>'Profesional','specialty'=>'General','active'=>true]);
        $room=DB::table('rooms')->insertGetId(['branch_id'=>$this->branch->id,'name'=>'Box prueba','active'=>true]);
        DB::table('availabilities')->insert(['branch_id'=>$this->branch->id,'professional_id'=>$pro,'room_id'=>$room,'starts_at'=>'2030-01-02 08:00:00','ends_at'=>'2030-01-02 18:00:00']);
        $data=['branch_id'=>$this->branch->id,'patient_id'=>$this->patient,'professional_id'=>$pro,'room_id'=>$room,'starts_at'=>'2030-01-02T09:00','duration'=>45,'ends_at'=>'2040-01-01','reason'=>'Control','status'=>'Pendiente'];
        $this->post('/modules/appointments',$data)->assertRedirect()->assertSessionHasNoErrors();
        $appointment=DB::table('appointments')->where('patient_id',$this->patient)->first();
        $this->assertSame('09:45',\Carbon\Carbon::parse($appointment->ends_at)->format('H:i'));
        $this->assertDatabaseHas('messages',['patient_id'=>$this->patient,'subject'=>'Actualización de cita']);
        $this->post('/modules/appointments',$data)->assertSessionHasErrors('starts_at');
        $this->post('/modules/appointments',array_replace($data,['duration'=>90]))->assertSessionHasErrors('duration');
    }

    public function test_local_mail_is_marked_simulated_not_delivered(): void {
        config(['mail.default'=>'log']);
        \Illuminate\Support\Facades\Mail::fake();
        [$survey,$path]=$this->invite('email');
        $this->artisan('clinic:send-messages')->assertSuccessful();
        $message=Record::for('messages')->where('patient_id',$this->patient)->firstOrFail();
        $this->assertSame('Simulado',$message->status);
        $this->assertNull($message->sent_at);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
    public function test_public_survey_requires_csrf_token(): void {
        [$survey,$path]=$this->invite();
        Auth::logout();
        // Exercise real request-forgery middleware instead of its testing bypass.
        $this->app->instance('env','local');
        $this->post($path,['ratings'=>[5,5,5,5]])->assertStatus(419);
        $csrf=bin2hex(random_bytes(20));
        $this->withSession(['_token'=>$csrf])->post($path,['_token'=>$csrf,'ratings'=>[5,5,5,5]])->assertRedirect('/survey-complete');
    }
    private function invite(string $channel='link'): array {
        $survey=Record::for('surveys');
        $survey->fill(['name'=>'Encuesta prueba','questions'=>Modules::surveyQuestions(),'active'=>true])->save();
        $this->post('/surveys/'.$survey->id.'/invite',['patient_id'=>$this->patient,'branch_id'=>$this->branch->id,'channel'=>$channel])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('survey_link');
        return [$survey,parse_url(session('survey_link'),PHP_URL_PATH)];
    }

    public function test_patient_survey_snapshot_validation_and_single_use(): void {
        [$survey,$path]=$this->invite();
        $invitation=DB::table('survey_invitations')->where('survey_id',$survey->id)->first();
        $this->assertNotSame(basename($path),$invitation->token_hash);
        $this->assertLessThan(2,abs(now()->addDays(14)->timestamp - \Carbon\Carbon::parse($invitation->expires_at)->timestamp));
        $survey->questions='Cambio posterior'; $survey->save();
        Auth::logout();
        $this->get($path)->assertOk()->assertSee('¿Cómo evalúa la atención recibida?')->assertDontSee('Paciente de prueba')->assertDontSee('Cambio posterior');
        $this->post($path,['ratings'=>[5,4,3]])->assertSessionHasErrors('ratings');
        $this->post($path,['ratings'=>[5,4,3,9]])->assertSessionHasErrors('ratings.3');
        $this->post($path,['ratings'=>[5,4,3,4],'comment'=>'<script>test</script>','patient_id'=>99999])->assertRedirect('/survey-complete');
        $response=Record::for('responses')->where('survey_id',$survey->id)->firstOrFail();
        $this->assertSame($this->patient,$response->patient_id);
        $this->assertSame(4,$response->score);
        $this->assertStringContainsString('atención recibida',$response->question_snapshot);
        $this->assertNotEquals($response->answers,DB::table('survey_responses')->find($response->id)->answers);
        $this->post($path,['ratings'=>[5,5,5,5]])->assertStatus(410);
        $this->assertSame(1,DB::table('survey_responses')->where('survey_id',$survey->id)->count());
        $this->actingAs($this->staff)->withSession(['session_version'=>$this->staff->session_version]);
        $this->get('/modules/responses/'.$response->id)->assertOk()->assertSee('&lt;script&gt;test&lt;/script&gt;',false)->assertDontSee('<script>test</script>',false);
        $this->put('/modules/responses/'.$response->id,[])->assertForbidden();
    }

    public function test_expired_invalid_links_and_email_invitation(): void {
        [$survey,$path]=$this->invite('email');
        $message=Record::for('messages')->where('patient_id',$this->patient)->firstOrFail();
        $this->assertStringContainsString($path,$message->body);
        $this->assertSame('Pendiente',$message->status);
        DB::table('survey_invitations')->where('survey_id',$survey->id)->update(['expires_at'=>now()->subSecond()->toIso8601String()]);
        Auth::logout();
        $this->get($path)->assertStatus(410);
        $this->get('/survey/'.str_repeat('a',64))->assertNotFound();
    }
}

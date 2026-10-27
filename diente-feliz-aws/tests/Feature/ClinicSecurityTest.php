<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash,Storage};
use App\Models\{User,Record,Role};
use App\Services\{Modules,Clinic};
class ClinicSecurityTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }
    private function loginAs(int $id=1): User { $u=User::findOrFail($id); $this->actingAs($u)->withSession(['session_version'=>$u->session_version]); return $u; }
    public function test_all_authorized_pages_and_forms_render(): void {
        $this->loginAs(); foreach(Modules::all() as $key=>$m) {
            $this->get('/modules/'.$key)->assertOk(); $this->get('/modules/'.$key.'/create')->assertStatus(!empty($m['readonly']) ? 403 : 200);
            $record=Record::for($key)->first(); if($record) $this->get('/modules/'.$key.'/'.$record->id)->assertOk();
        }
        foreach(['/','/users','/roles','/reports','/audit','/help','/account'] as $url) $this->get($url)->assertOk();
    }
    public function test_reception_cannot_access_clinical_data_or_admin(): void {
        $this->loginAs(2);
        foreach(['/users','/roles','/audit','/modules/clinical','/modules/odontogram','/modules/documents','/modules/clinical/1','/modules/clinical/export'] as $url) $this->get($url)->assertForbidden();
        $this->get('/modules/patients/1?view=advanced')->assertOk()->assertDontSee('Evaluación odontológica de demostración.');
    }
    public function test_branch_scope_prevents_idor_and_exports(): void {
        $this->loginAs(2); $this->get('/modules/patients/8')->assertNotFound();
        $this->get('/modules/patients?branch_id=2')->assertDontSee('Lucas Ficticio');
        $this->get('/modules/patients/export?format=csv&ids[]=8')->assertOk()->assertStreamedContent("\xEF\xBB\xBFid;name;identifier;birth_date;email;phone;emergency_contact;category;agreement_id;contact_consent;active\n");
    }
    public function test_login_lockout_and_hashes(): void {
        $this->assertTrue(Hash::check(env('DEMO_PASSWORD'),User::find(1)->password));
        for($i=0;$i<5;$i++) $this->post('/login',['email'=>'admin@dientefeliz.test','password'=>'incorrecta'])->assertSessionHasErrors('email');
        $this->post('/login',['email'=>'admin@dientefeliz.test','password'=>env('DEMO_PASSWORD')])->assertStatus(429);
    }
    public function test_login_logout_and_security_headers(): void {
        $this->post('/login',['email'=>'admin@dientefeliz.test','password'=>env('DEMO_PASSWORD')])->assertRedirect('/');
        $this->assertAuthenticated(); $this->get('/')->assertOk()->assertHeader('X-Frame-Options','DENY')->assertHeader('X-Content-Type-Options','nosniff');
        $this->post('/logout')->assertRedirect('/login'); $this->assertGuest();
    }
    public function test_disabled_and_revoked_sessions_are_rejected(): void {
        $u=$this->loginAs(2); $u->update(['disabled_from'=>now()->subMinute(),'disabled_until'=>now()->addDay()]);
        $this->get('/')->assertRedirect('/login'); $this->assertGuest();
        $u->update(['disabled_from'=>null,'disabled_until'=>null]); $this->loginAs(2); $u->increment('session_version');
        $this->get('/')->assertRedirect('/login');
    }
    public function test_clinical_text_is_encrypted_and_xss_is_escaped(): void {
        $this->loginAs(3); $payload='<script>alert(1)</script>';
        $this->post('/modules/clinical',['branch_id'=>1,'patient_id'=>1,'diagnosis'=>$payload,'treatment'=>'Prueba segura'])->assertRedirect();
        $r=Record::for('clinical')->latest('id')->first(); $this->assertNotEquals($payload,DB::table('clinical_entries')->find($r->id)->diagnosis);
        $this->get('/modules/clinical/'.$r->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee($payload,false);
    }
    public function test_mass_assignment_and_sql_injection_cannot_escalate(): void {
        $this->loginAs(2);
        $this->get('/modules/patients?q=%27%20OR%201%3D1--')->assertOk()->assertDontSee('Sofía Ejemplo');
        $this->post('/modules/patients',['name'=>'Nuevo Demo','identifier'=>'DEMO-NEW','birth_date'=>'2000-01-01','category'=>'General','contact_consent'=>0,'active'=>1,'branches'=>[1],'id'=>1000,'is_admin'=>true])->assertRedirect();
        $this->assertDatabaseMissing('patients',['id'=>1000]);
        $this->post('/modules/patients',['name'=>'Intruso','identifier'=>'DEMO-INTR','birth_date'=>'2000-01-01','category'=>'General','contact_consent'=>0,'active'=>1,'branches'=>[2]])->assertForbidden();
    }
    public function test_role_specific_comments_are_enforced(): void {
        $this->loginAs(3); $this->post('/modules/comments',['branch_id'=>1,'patient_id'=>1,'visibility'=>'clinical','body'=>'Comentario clínico reservado'])->assertRedirect();
        $id=Record::for('comments')->latest('id')->value('id'); $this->loginAs(2);
        $this->get('/modules/comments/'.$id)->assertNotFound();
        $this->post('/modules/comments',['branch_id'=>1,'patient_id'=>1,'visibility'=>'clinical','body'=>'Intento'])->assertForbidden();
    }
    private function appointment(array $extra=[]): array { return array_merge(['branch_id'=>1,'patient_id'=>6,'professional_id'=>1,'room_id'=>1,'starts_at'=>today()->setTime(15,0)->format('Y-m-d\TH:i'),'duration'=>30,'reason'=>'Control','status'=>'Pendiente'],$extra); }
    public function test_appointments_validate_duration_availability_and_overlaps(): void {
        $this->loginAs(2); $data=$this->appointment();
        $this->post('/modules/appointments',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/modules/appointments',$data)->assertSessionHasErrors('starts_at');
        $this->post('/modules/appointments',$this->appointment(['starts_at'=>today()->setTime(20,0)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('starts_at');
        $this->post('/modules/appointments',$this->appointment(['duration'=>20]))->assertSessionHasErrors('duration');
    }
    public function test_postgres_rejects_direct_overlapping_insert(): void {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('appointments')->insert(['branch_id'=>1,'patient_id'=>6,'professional_id'=>1,'room_id'=>1,'starts_at'=>today()->setTime(9,0),'ends_at'=>today()->setTime(9,30),'reason'=>'Conflicto','status'=>'Pendiente']);
    }
    public function test_partial_payments_refunds_and_replay_protection(): void {
        $this->loginAs(); $data=['branch_id'=>1,'patient_id'=>1,'budget_id'=>1,'amount'=>30000,'method'=>'Efectivo','idempotency_key'=>(string)\Str::uuid()];
        $this->post('/modules/payments',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(30000,Clinic::balance(Record::for('budgets')->find(1)));
        $this->post('/modules/payments',$data)->assertSessionHasErrors();
        $this->post('/modules/payments',array_merge($data,['idempotency_key'=>(string)\Str::uuid(),'amount'=>30001]))->assertSessionHasErrors('amount');
        $refund=['branch_id'=>1,'patient_id'=>1,'payment_id'=>1,'amount'=>5000,'reason'=>'Ajuste demo','idempotency_key'=>(string)\Str::uuid()];
        $this->post('/modules/refunds',$refund)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(35000,Clinic::balance(Record::for('budgets')->find(1)));
        $this->post('/modules/refunds',array_merge($refund,['amount'=>20000,'idempotency_key'=>(string)\Str::uuid()]))->assertSessionHasErrors('amount');
        $this->put('/modules/payments/1',$data)->assertForbidden();
    }
    public function test_payment_must_match_patient(): void {
        $this->loginAs(); $this->post('/modules/payments',['branch_id'=>1,'patient_id'=>2,'budget_id'=>1,'amount'=>1,'method'=>'Efectivo','idempotency_key'=>(string)\Str::uuid()])->assertSessionHasErrors('patient_id');
    }
    public function test_secure_file_upload_download_and_forbidden_content(): void {
        Storage::fake('local'); $this->loginAs(3);
        $data=['branch_id'=>1,'patient_id'=>1,'name'=>'Radiografía demo','kind'=>'Radiografía'];
        $this->post('/modules/documents',$data+['file'=>UploadedFile::fake()->image('radiografia.png')])->assertRedirect()->assertSessionHasNoErrors();
        $doc=Record::for('documents')->first(); Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('radiografia.png',$doc->path);
        $this->get('/documents/'.$doc->id.'/download')->assertDownload('documento-'.$doc->id.'.png');
        $this->post('/modules/documents',$data+['file'=>UploadedFile::fake()->createWithContent('evil.php','<?php echo "evil";')])->assertSessionHasErrors('file');
        $this->post('/modules/documents',$data+['file'=>UploadedFile::fake()->create('large.pdf',11000,'application/pdf')])->assertSessionHasErrors('file');
        $this->loginAs(2); $this->get('/documents/'.$doc->id.'/download')->assertForbidden();
    }
    public function test_consents_are_immutable_and_templates_versioned(): void {
        $this->loginAs();
        $this->post('/modules/consents',['branch_id'=>1,'patient_id'=>1,'template_id'=>1,'body'=>'Texto revisado','signer'=>'Paciente Demo','accepted'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $c=Record::for('consents')->first(); $this->assertSame(hash('sha256','Texto revisado|Paciente Demo'),$c->document_hash);
        $this->put('/modules/consents/'.$c->id,['body'=>'Alterado'])->assertForbidden();
        $this->put('/modules/templates/1',['name'=>'Revisada','kind'=>'Consentimiento','body'=>'Versión dos','active'=>1])->assertRedirect();
        $this->assertSame(2,Record::for('templates')->find(1)->version); $this->assertSame('Texto revisado',$c->fresh()->body);
    }
    public function test_financial_records_generate_audit_and_no_plain_password(): void {
        $this->loginAs(); $this->post('/users',['name'=>'Usuario Demo','email'=>'nuevo@example.test','password'=>'Password-Segura!123','active'=>1,'roles'=>[3],'branches'=>[1]])->assertRedirect()->assertSessionHasNoErrors();
        $audit=DB::table('audit_events')->where('action','user_access_changed')->first(); $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Password-Segura',\Crypt::decryptString($audit->changes));
    }
    public function test_csv_formula_injection_is_neutralized(): void {
        $this->loginAs(); $p=Record::for('patients')->find(1); $p->name='=HYPERLINK("evil")'; $p->save();
        $response=$this->get('/modules/patients/export?ids[]=1'); $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK",$response->streamedContent());
    }
    public function test_user_multiple_roles_and_temporary_disable(): void {
        $u=User::find(4); $this->assertTrue($u->permits('resources.write')); $this->assertTrue($u->permits('finance.write'));
        $u->disabled_from=now()->subDays(2); $u->disabled_until=now()->subDay(); $this->assertTrue($u->enabled());
        $u->disabled_until=now()->addDay(); $this->assertFalse($u->enabled());
    }
}


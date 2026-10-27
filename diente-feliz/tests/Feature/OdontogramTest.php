<?php
namespace Tests\Feature;

use App\Models\{Branch,Record,Role,User};
use App\Services\Odontogram;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OdontogramTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chart_history_permissions_validation_and_encryption(): void
    {
        $branch = Branch::create(['name'=>'Prueba dental','address'=>'Ficticia','active'=>true]);
        $other = Branch::create(['name'=>'Otra sede','address'=>'Ficticia','active'=>true]);
        $role = Role::create(['name'=>'Dental '.uniqid(),'permissions'=>['patients.view','clinical.view','clinical.write']]);
        $user = User::factory()->create()->refresh();
        $user->roles()->attach($role);
        $user->branches()->attach($branch);
        $patient = DB::table('patients')->insertGetId(['name'=>'Paciente dental ficticio','identifier'=>'TEST-'.uniqid(),'birth_date'=>'2000-01-01','created_at'=>now(),'updated_at'=>now()]);
        DB::table('branch_patient')->insert(['patient_id'=>$patient,'branch_id'=>$branch->id]);
        $this->actingAs($user)->withSession(['session_version'=>$user->session_version]);
        // Populate the dynamic model cache with a different table before dental writes.
        Record::for('patients')->fill(['name'=>'No persistido']);
        $payload = ['patient_id'=>$patient,'branch_id'=>$branch->id,'tooth'=>11,'surface'=>'Mesial','condition'=>'Caries','notes'=>'<script>alert(1)</script>','author_id'=>999999];
        $this->post('/modules/odontogram',$payload)->assertRedirect('/modules/patients/'.$patient.'#odontograma');
        $first = Record::for('odontogram')->where('patient_id',$patient)->firstOrFail();
        $this->assertSame($user->id,$first->author_id);
        $this->assertSame($payload['notes'],$first->notes);
        $this->assertNotSame($payload['notes'],DB::table('tooth_records')->find($first->id)->notes);
        $this->assertDatabaseHas('audit_events',['entity'=>'tooth_records','entity_id'=>$first->id,'action'=>'create']);
        $this->get('/modules/patients/'.$patient)->assertOk()->assertSee('Dentición temporal')->assertSee('Pieza 11, Mesial, Caries')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $this->get('/modules/odontogram/create?patient_id='.$patient.'&tooth=11&surface=Mesial')->assertOk()->assertSee('value="11" selected',false);
        foreach ([['tooth'=>99],['surface'=>'Inexistente'],['condition'=>'Inventado']] as $invalid) {
            $this->post('/modules/odontogram',array_replace($payload,$invalid))->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->post('/modules/odontogram',array_replace($payload,['branch_id'=>$other->id]))->assertNotFound();
        $this->put('/modules/odontogram/'.$first->id,$payload)->assertForbidden();
        $this->post('/modules/odontogram',array_replace($payload,['surface'=>'Completa','condition'=>'Ausente','notes'=>null]))->assertRedirect();
        $history = Record::for('odontogram')->where('patient_id',$patient)->get();
        $this->assertCount(2,$history);
        $this->assertSame(['Ausente'],Odontogram::current($history)->pluck('condition')->all());
        $this->post('/modules/odontogram',array_replace($payload,['condition'=>'Restaurado']))->assertRedirect();
        $this->assertCount(2,Odontogram::current(Record::for('odontogram')->where('patient_id',$patient)->get()));
        $this->get('/modules/odontogram?patient_id='.$patient.'&tooth=12')->assertOk()->assertDontSee('alert(1)');
        $reader = Role::create(['name'=>'Lectura '.uniqid(),'permissions'=>['patients.view','clinical.view']]);
        $user->roles()->sync([$reader->id]); $user->unsetRelation('roles');
        $this->get('/modules/patients/'.$patient)->assertOk()->assertDontSee('/modules/odontogram/create',false);
        $this->post('/modules/odontogram',$payload)->assertForbidden();
        $reader->update(['permissions'=>['patients.view']]); $user->unsetRelation('roles');
        $this->get('/modules/patients/'.$patient)->assertOk()->assertDontSee('id="odontograma"',false);
        $this->get('/modules/odontogram/'.$first->id)->assertForbidden();
    }
}

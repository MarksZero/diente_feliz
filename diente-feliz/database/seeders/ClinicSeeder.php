<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\{User,Role,Branch,Record};
use App\Services\Modules;
class ClinicSeeder extends Seeder {
    public function run(): void {
        if(!app()->environment(['local','testing'])) throw new \RuntimeException('Los datos demo solo se permiten en local/testing.');
        if(User::exists()) { $this->command?->info('Ya existen usuarios; no se reemplazan datos.'); return; }
        $password=env('DEMO_PASSWORD'); if(!$password || strlen($password)<12) throw new \RuntimeException('Configura DEMO_PASSWORD (mínimo 12 caracteres) en .env.');
        $all=Modules::permissions();
        $permissions=function(array $groups,array $extra=[]) { $p=$extra; foreach($groups as $g) { $p[]=$g.'.view'; $p[]=$g.'.write'; } return $p; };
        $roles=[
            'Administrador general'=>$all,
            'Encargado de sucursal'=>$permissions(['resources','appointments','communication'],['patients.view','settings.view','finance.view','reports.view','exports.view']),
            'Recepcionista'=>$permissions(['patients','appointments','finance','communication','comments'],['comments.administrative','resources.view','settings.view','exports.view','reports.view']),
            'Dentista'=>$permissions(['clinical','comments'],['comments.clinical','patients.view','appointments.view','resources.view','templates.view','exports.view']),
        ];
        foreach($roles as $name=>$p) Role::create(['name'=>$name,'permissions'=>$p]);
        $center=Branch::create(['name'=>'Sede Centro','address'=>'Avenida Demostración 100, Ciudad Ficticia','active'=>true]);
        $north=Branch::create(['name'=>'Sede Norte','address'=>'Calle Ejemplo 200, Ciudad Ficticia','active'=>true]);
        foreach([['Administradora Demo','admin@dientefeliz.test',['Administrador general'],[1,2]],['Recepción Demo','recepcion@dientefeliz.test',['Recepcionista'],[1]],['Dra. Emilia Demo','dentista@dientefeliz.test',['Dentista'],[1]],['Encargada Demo','encargado@dientefeliz.test',['Encargado de sucursal','Recepcionista'],[2]]] as [$name,$email,$roleNames,$branches]) {
            $u=User::create(['name'=>$name,'email'=>$email,'password'=>$password,'active'=>true]); $u->roles()->sync(Role::whereIn('name',$roleNames)->pluck('id')); $u->branches()->sync($branches);
        }
        $create=function($m,$data) { $r=Record::for($m); $r->fill($data)->save(); return $r; };
        $box=$create('rooms',['branch_id'=>1,'name'=>'Box 01','equipment'=>'Sillón, lámpara y equipo radiográfico demo','active'=>true]);
        $create('rooms',['branch_id'=>1,'name'=>'Box 02','equipment'=>'Sillón y lámpara demo','active'=>true]);
        $create('rooms',['branch_id'=>2,'name'=>'Box Norte 01','active'=>true]);
        $pro=$create('professionals',['branch_id'=>1,'user_id'=>3,'name'=>'Dra. Emilia Demo','specialty'=>'Odontología general','active'=>true]);
        foreach(range(0,14) as $day) $create('availability',['branch_id'=>1,'professional_id'=>$pro->id,'room_id'=>$box->id,'starts_at'=>today()->addDays($day)->setTime(8,0),'ends_at'=>today()->addDays($day)->setTime(18,0)]);
        $agreement=$create('agreements',['name'=>'Convenio Educativo Demo','discount_percent'=>10,'expires_on'=>today()->addYear(),'active'=>true]);
        $names=['Sofía Ejemplo','Tomás Prueba','Isidora Demo','Mateo Ficticio','Valentina Ejemplo','Benjamín Prueba','Antonia Demo','Lucas Ficticio'];
        foreach($names as $index=>$name) {
            $p=$create('patients',['name'=>$name,'identifier'=>'DEMO-'.str_pad($index+1,4,'0',STR_PAD_LEFT),'birth_date'=>'1995-05-15','email'=>'paciente'.($index+1).'@example.test','phone'=>'+56 9 0000 0000','emergency_contact'=>'Contacto de prueba, sin datos reales','category'=>$index%2?'General':'Convenio','agreement_id'=>$index%2?null:$agreement->id,'active'=>true,'contact_consent'=>true]);
            DB::table('branch_patient')->insert(['patient_id'=>$p->id,'branch_id'=>$index===7?2:1]);
            if($index===7) continue;
            if($index<5) $create('appointments',['branch_id'=>1,'patient_id'=>$p->id,'professional_id'=>$pro->id,'room_id'=>$box->id,'starts_at'=>today()->setTime(9+$index,0),'ends_at'=>today()->setTime(9+$index,30),'reason'=>['Evaluación inicial','Control preventivo','Limpieza dental','Revisión de tratamiento','Consulta general'][$index],'status'=>$index<2?'Confirmada':'Pendiente']);
            $create('clinical',['patient_id'=>$p->id,'branch_id'=>1,'author_id'=>3,'diagnosis'=>'Evaluación odontológica de demostración.','treatment'=>'Control preventivo y recomendaciones de higiene.','allergies'=>'Sin antecedentes informados (dato ficticio).','medications'=>'Sin medicamentos informados.','history'=>'Anamnesis de prueba para presentación académica.','follow_up_on'=>today()->addMonths(6)]);
            $create('odontogram',['patient_id'=>$p->id,'branch_id'=>1,'author_id'=>3,'tooth'=>16,'surface'=>'Oclusal','condition'=>$index%2?'Sano':'Caries','notes'=>'Hallazgo ficticio para demostración.']);
            $budget=$create('budgets',['patient_id'=>$p->id,'branch_id'=>1,'name'=>'Plan preventivo · '.$name,'details'=>'1 evaluación + 1 limpieza dental','gross_amount'=>80000,'discount_percent'=>0,'total'=>80000,'due_on'=>today()->addDays(15),'status'=>$index<3?'Aceptado':'Pendiente']);
            if($index<3) $create('payments',['patient_id'=>$p->id,'branch_id'=>1,'budget_id'=>$budget->id,'author_id'=>2,'amount'=>20000,'method'=>'Transferencia','idempotency_key'=>(string)\Illuminate\Support\Str::uuid()]);
            $create('recalls',['patient_id'=>$p->id,'branch_id'=>1,'reason'=>$index<3?'Control':'Presupuesto pendiente','due_on'=>today()->addDays(7),'status'=>'Pendiente','notes'=>'Seguimiento de demostración.']);
        }
        $create('templates',['name'=>'Consentimiento general de atención','kind'=>'Consentimiento','body'=>"Declaro haber recibido una explicación comprensible del procedimiento propuesto, sus alternativas, beneficios y riesgos. He tenido la oportunidad de formular preguntas.\n\nProcedimiento: [completar].\nRiesgos y alternativas explicados: [completar].\nAcepto voluntariamente la atención descrita. Puedo retirar mi consentimiento antes del procedimiento.",'active'=>true,'version'=>1]);
        $create('templates',['name'=>'Constancia de devolución','kind'=>'Devolución','body'=>'Se deja constancia de la devolución del importe indicado, asociado al pago original y al motivo registrado.','active'=>true,'version'=>1]);
        $create('surveys',['name'=>'Tu experiencia en Diente Feliz','questions'=>"¿Cómo evaluarías la atención recibida?\n¿La información entregada fue clara?\n¿Qué podemos mejorar?",'active'=>true]);
    }
}

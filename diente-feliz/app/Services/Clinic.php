<?php
namespace App\Services;

use App\Models\Record;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Clinic {
    public static function query(string $module, ?User $user = null) {
        $user ??= auth()->user();
        $definition = Modules::get($module);
        $query = Record::for($module)->newQuery();
        if ($module==='patients') $query->whereIn('id', DB::table('branch_patient')->select('patient_id')->whereIn('branch_id',$user->branchIds()));
        elseif ($module==='branches') $query->whereIn('id',$user->branchIds());
        elseif (isset($definition['fields']['branch_id'])) $query->whereIn('branch_id',$user->branchIds());
        if ($module==='comments') $query->whereIn('visibility',array_values(array_filter(['clinical','administrative'],fn($v)=>$user->permits('comments.'.$v))));
        return $query;
    }
    public static function filtered(string $module, Request $request) {
        $filters=$request->validate(['q'=>'nullable|string|max:100','active'=>'nullable|boolean','address'=>'nullable|string|max:250','branch_id'=>'nullable|integer','patient_id'=>'nullable|integer','professional_id'=>'nullable|integer','room_id'=>'nullable|integer','status'=>'nullable|string|max:30','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $query=self::query($module); $fields=Modules::get($module)['fields'];
        if ($module === 'odontogram') {
            $dentalFilters = $request->validate(['tooth'=>'nullable|integer|in:'.implode(',',Modules::teeth()), 'surface'=>'nullable|in:Completa,Mesial,Distal,Vestibular,Lingual,Oclusal']);
            foreach ($dentalFilters as $field=>$value) if ($value !== null) $query->where($field,$value);
        }
        if (!empty($filters['q']) && isset($fields['name'])) $query->where('name','ilike','%'.$filters['q'].'%');
        foreach(['branch_id','patient_id','professional_id','room_id','status'] as $f) if (!empty($filters[$f]) && isset($fields[$f])) $query->where($f,$filters[$f]);
        if (isset($fields['active']) && isset($filters['active'])) $query->where('active',$filters['active']);
        if (isset($fields['address']) && !empty($filters['address'])) $query->where('address','ilike','%'.$filters['address'].'%');
        $date=Modules::dateFilter($module);
        if($date && !empty($filters['from'])) $query->whereDate($date,'>=',$filters['from']);
        if($date && !empty($filters['to'])) $query->whereDate($date,'<=',$filters['to']);
        return $query;
    }
    public static function options(string $module): array {
        $options=[];
        foreach (Modules::get($module)['fields'] as $field=>$spec) {
            if (!isset($spec[3])) continue;
            if (is_array($spec[3])) { $options[$field]=$spec[3]; continue; }
            if ($spec[3]==='users') { $options[$field]=User::whereHas('branches',fn($q)=>$q->whereIn('branches.id',auth()->user()->branchIds()))->pluck('name','id')->all(); continue; }
            $options[$field]=self::query($spec[3])->orderBy('id')->limit(1000)->get()->mapWithKeys(fn($r)=>[$r->id=>($r->name ?? ('#'.$r->id)).($r->branch_id?' · sede '.$r->branch_id:'')])->all();
        }
        return $options;
    }
    public static function save(string $module, Request $request, ?Record $existing = null): Record {
        $definition=Modules::get($module);
        abort_if(!empty($definition['readonly']),403);
        Gate::authorize($definition['permission'].'.write');
        if ($existing) Gate::authorize('update',$existing);
        if ($module === 'appointments') {
            $timing = $request->validate(['starts_at'=>'required|date_format:Y-m-d\TH:i','duration'=>'required|integer|in:30,45,60']);
            $request->merge(['ends_at'=>Carbon::parse($timing['starts_at'])->addMinutes((int)$timing['duration'])->format('Y-m-d\TH:i')]);
        }
        $rules=[]; foreach($definition['fields'] as $field=>$spec) $rules[$field]=$spec[2];
        if($module==='patients') { $rules['identifier'].='|unique:patients,identifier'.($existing?','.$existing->id:''); $rules['branches']='required|array|min:1|max:20'; $rules['branches.*']='integer'; }
        if(in_array($module,['payments','refunds'])) $rules['idempotency_key']='required|uuid';
        $data=$request->validate($rules);
        if ($module==='surveys') {
            $questions=array_values(array_filter(array_map('trim',preg_split('/\R/u',$data['questions'])),fn($q)=>$q!==''));
            if (count($questions)<1 || count($questions)>20) self::fail('questions','Ingresa entre 1 y 20 preguntas, una por línea.');
            $data['questions']=implode("\n",$questions);
        }
        $storedPath=null;
        try { return DB::transaction(function() use($module,$definition,$data,$existing,$request,&$storedPath) {
            if($existing) $existing=self::query($module)->lockForUpdate()->findOrFail($existing->id);
            foreach($definition['fields'] as $field=>$spec) {
                if(empty($data[$field]) || !isset($spec[3]) || !is_string($spec[3])) continue;
                if($spec[3]==='users') {
                    $related=User::findOrFail($data[$field]);
                    abort_unless($related->branches()->whereIn('branches.id',auth()->user()->branchIds())->exists(),403);
                } else {
                    $related=self::query($spec[3])->findOrFail($data[$field]);
                    if(isset($related->active) && !$related->active) self::fail($field,'El recurso está deshabilitado.');
                    if($related->branch_id && isset($data['branch_id']) && $related->branch_id != $data['branch_id']) self::fail($field,'El recurso pertenece a otra sucursal.');
                }
            }
            if(isset($data['patient_id'],$data['branch_id']) && !DB::table('branch_patient')->where('patient_id',$data['patient_id'])->where('branch_id',$data['branch_id'])->exists()) {
                if ($module !== 'messages') self::fail('patient_id','El paciente no está asociado a esta sucursal.');
                $request->session()->flash('warning','El mensaje se guardó, pero el paciente no pertenece a la sucursal seleccionada. No se modificaron sus asociaciones.');
            }
            if($module==='comments') Gate::authorize('comments.'.$data['visibility']);
            if($module==='odontogram' && !in_array((int)$data['tooth'],Modules::teeth())) self::fail('tooth','Pieza FDI inválida.');
            if(in_array($module,['appointments','availability'])) self::schedule($data,$existing,$module);
            if($module==='professionals' && !DB::table('branch_user')->where('user_id',$data['user_id'])->where('branch_id',$data['branch_id'])->exists()) self::fail('user_id','Asigna primero el usuario a la sucursal.');
            if($module==='budgets') {
                if($existing && DB::table('payments')->where('budget_id',$existing->id)->exists()) self::fail('name','Un presupuesto con pagos no puede modificarse.');
                $patient=self::query('patients')->findOrFail($data['patient_id']);
                $data['name']=$existing?->name ?? 'Presupuesto · '.$patient->name;
                $agreement=$patient->agreement_id?Record::for('agreements')->find($patient->agreement_id):null;
                $data['discount_percent']=($agreement && $agreement->active && $agreement->expires_on>=now()->toDateString()) ? $agreement->discount_percent : 0;
                $data['total']=self::discount((int)$data['gross_amount'],(int)$data['discount_percent']);
            }
            if($module==='payments') {
                $budget=self::query('budgets')->lockForUpdate()->findOrFail($data['budget_id']);
                self::samePatient($budget,$data);
                if($budget->status!=='Aceptado') self::fail('budget_id','El presupuesto debe estar aceptado.');
                if($data['amount']>self::balance($budget)) self::fail('amount','El abono supera el saldo pendiente.');
            }
            if($module==='refunds') {
                $payment=self::query('payments')->lockForUpdate()->findOrFail($data['payment_id']);
                self::samePatient($payment,$data);
                // Same budget lock as payments serializes financial changes.
                self::query('budgets')->lockForUpdate()->findOrFail($payment->budget_id);
                $remaining=$payment->amount-DB::table('refunds')->where('payment_id',$payment->id)->sum('amount');
                if($data['amount']>$remaining) self::fail('amount','La devolución supera el monto disponible del pago.');
            }
            if(in_array($module,['payments','refunds']) && Record::for($module)->where('idempotency_key',$data['idempotency_key'])->exists()) self::fail('amount','Esta operación ya se registró.');
            if($module==='templates') $data['version']=$existing?($existing->version+1):1;
            if($module==='consents') {
                $template=Record::for('templates')->findOrFail($data['template_id']);
                if($template->kind!=='Consentimiento') self::fail('template_id','Selecciona una plantilla de consentimiento.');
                unset($data['accepted']); $data['signed_at']=now(); $data['document_hash']=hash('sha256',$data['body'].'|'.$data['signer']);
            }
            if($module==='responses') $data['question_snapshot']=Record::for('surveys')->findOrFail($data['survey_id'])->questions;
            if($module==='documents') {
                $file=$request->file('file'); $mime=$file->getMimeType();
                if (!in_array($mime,['application/pdf','image/png','image/jpeg'],true)) self::fail('file','Formato no permitido.');
                if(str_starts_with($mime,'image/') && @getimagesize($file->getRealPath())===false) self::fail('file','La imagen no es válida.');
                $data['path']=$storedPath=$file->store('clinical','local'); $data['mime']=$mime; $data['size']=$file->getSize(); $data['sha256']=hash_file('sha256',$file->getRealPath()); unset($data['file']);
            }
            $branches=$data['branches']??null; unset($data['branches']);
            if($branches) foreach($branches as $id) abort_unless(in_array($id,auth()->user()->branchIds()),403);
            if(in_array($module,['clinical','comments','odontogram','documents','consents','payments','refunds'])) $data['author_id']=auth()->id();
            $record=$existing??Record::for($module);
            $before=$existing? $existing->only(array_keys($data)):[];
            $record->fill($data)->save();
            if($branches) {
                // Add authorized associations; never remove links to clinical history.
                foreach($branches as $id) DB::table('branch_patient')->insertOrIgnore(['patient_id'=>$record->id,'branch_id'=>$id]);
            }
            if($module==='branches' && !$existing) auth()->user()->branches()->attach($record->id);
            Audit::record($existing?'update':'create',$definition['table'],$record->id,['before'=>$before,'after'=>$record->only(array_keys($data))]);
            if($module==='appointments' && (!$existing || $existing->wasChanged(['starts_at','status']))) {
                $message=Record::for('messages'); $message->fill(['patient_id'=>$data['patient_id'],'branch_id'=>$data['branch_id'],'subject'=>'Actualización de cita','body'=>'Tu cita se encuentra '.$data['status'].'. Fecha: '.Carbon::parse($data['starts_at'])->format('d/m/Y H:i').'. Contacta a la clínica ante dudas.','status'=>'Pendiente'])->save();
            }
            return $record;
        },3); } catch(\Throwable $e) { if($storedPath) Storage::disk('local')->delete($storedPath); throw $e; }
    }
    private static function schedule(array &$data, ?Record $existing, string $module): void {
        $start=Carbon::parse($data['starts_at']); $end=Carbon::parse($data['ends_at']);
        $data['starts_at']=$start; $data['ends_at']=$end;
        $pro=self::query('professionals')->lockForUpdate()->findOrFail($data['professional_id']);
        $user=User::findOrFail($pro->user_id);
        if(!$user->enabled() || !$pro->active || ($pro->disabled_from && Carbon::parse($pro->disabled_from)->lt($end) && (!$pro->disabled_until || Carbon::parse($pro->disabled_until)->gt($start)))) self::fail('professional_id','Profesional no habilitado en ese período.');
        if($module==='appointments' && $data['status']!=='Cancelada') {
            if(!in_array((int)$start->diffInMinutes($end),[30,45,60]) || $start->second || $end->second) self::fail('ends_at','Las citas deben durar 30, 45 o 60 minutos.');
            $available=self::query('availability')->where('professional_id',$data['professional_id'])->where('room_id',$data['room_id'])->where('starts_at','<=',$start)->where('ends_at','>=',$end)->exists();
            if(!$available) self::fail('starts_at','No hay disponibilidad del profesional y box para ese horario.');
            $conflict=Record::for('appointments')->where('status','<>','Cancelada')->where('starts_at','<',$end)->where('ends_at','>',$start)->when($existing,fn($q)=>$q->where('id','<>',$existing->id))->where(fn($q)=>$q->where('professional_id',$data['professional_id'])->orWhere('room_id',$data['room_id'])->orWhere('patient_id',$data['patient_id']))->exists();
            if($conflict) self::fail('starts_at','El paciente, profesional o box ya tiene una cita en ese horario.');
        }
    }
    public static function discount(int $gross,int $percent): int { return (int) round($gross*(100-$percent)/100); }
    public static function balance(Record $budget): int {
        $paid=DB::table('payments')->where('budget_id',$budget->id)->sum('amount');
        $refund=DB::table('refunds')->whereIn('payment_id',DB::table('payments')->select('id')->where('budget_id',$budget->id))->sum('amount');
        return $budget->total-$paid+$refund;
    }
    private static function samePatient(Record $record,array $data): void { if($record->patient_id!=$data['patient_id'] || $record->branch_id!=$data['branch_id']) self::fail('patient_id','La operación no corresponde al paciente y sucursal seleccionados.'); }
    public static function fail(string $field,string $message): never { throw ValidationException::withMessages([$field=>$message]); }
}

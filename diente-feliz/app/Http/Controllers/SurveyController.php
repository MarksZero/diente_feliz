<?php
namespace App\Http\Controllers;

use App\Models\Record;
use App\Services\{Audit,Clinic};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
use Illuminate\Validation\ValidationException;

class SurveyController extends Controller
{
    public function inviteForm(int $id) {
        Gate::authorize('communication.write');
        $survey=Clinic::query('surveys')->where('active',true)->findOrFail($id);
        $patients=Clinic::query('patients')->where('active',true)->orderBy('name')->get();
        $branches=Clinic::query('branches')->where('active',true)->get();
        return view('survey-invite',compact('survey','patients','branches'));
    }

    public function invite(Request $request,int $id) {
        Gate::authorize('communication.write');
        $data=$request->validate(['patient_id'=>'required|integer','branch_id'=>'required|integer','channel'=>'required|in:link,email']);
        $survey=Clinic::query('surveys')->where('active',true)->findOrFail($id);
        $patient=Clinic::query('patients')->where('active',true)->findOrFail($data['patient_id']);
        $branch=Clinic::query('branches')->where('active',true)->findOrFail($data['branch_id']);
        abort_unless(DB::table('branch_patient')->where('patient_id',$patient->id)->where('branch_id',$branch->id)->exists(),422,'El paciente no pertenece a esta sucursal.');
        if (!$patient->contact_consent || ($data['channel']==='email' && !$patient->email)) {
            throw ValidationException::withMessages(['patient_id'=>'Se requiere autorización de comunicaciones y, para correo, una dirección registrada.']);
        }
        $questions=self::questions($survey->questions);
        if (count($questions)<1 || count($questions)>20) throw ValidationException::withMessages(['survey'=>'La encuesta debe contener entre 1 y 20 preguntas.']);
        $token=bin2hex(random_bytes(32));
        $link=url('/survey/'.$token);
        DB::transaction(function() use($data,$survey,$patient,$branch,$questions,$token,$link) {
            $invitation=DB::table('survey_invitations')->insertGetId([
                'survey_id'=>$survey->id,'patient_id'=>$patient->id,'branch_id'=>$branch->id,'created_by'=>auth()->id(),
                'token_hash'=>hash('sha256',$token),'questions'=>json_encode($questions,JSON_UNESCAPED_UNICODE),
                'channel'=>$data['channel'],'expires_at'=>now()->addDays(14)->toIso8601String(),'created_at'=>now()->toIso8601String(),'updated_at'=>now()->toIso8601String(),
            ]);
            if ($data['channel']==='email') {
                $message=Record::for('messages');
                $message->fill(['patient_id'=>$patient->id,'branch_id'=>$branch->id,'subject'=>'Encuesta de satisfacción',
                    'body'=>"Te invitamos a responder nuestra encuesta: ".$link."\nEl enlace es personal, vence en 14 días y admite una respuesta.",'status'=>'Pendiente'])->save();
            }
            Audit::record('survey_invited','survey_invitations',$invitation,['channel'=>$data['channel']]);
        });
        return redirect('/surveys/'.$id.'/invite')->with('survey_link',$link)->with('success',$data['channel']==='email'?'Invitación guardada en la cola de correos.':'Enlace personal generado. Compártelo únicamente con el paciente.');
    }

    public function show(string $token) {
        $invitation=$this->invitation($token);
        $questions=json_decode($invitation->questions,true,512,JSON_THROW_ON_ERROR);
        return view('survey-public',compact('questions','token'));
    }

    public function submit(Request $request,string $token) {
        DB::transaction(function() use($request,$token) {
            $invitation=$this->invitation($token,true);
            $questions=json_decode($invitation->questions,true,512,JSON_THROW_ON_ERROR);
            $rules=['ratings'=>'required|array|size:'.count($questions),'ratings.*'=>'required|integer|between:1,5','comment'=>'nullable|string|max:2000'];
            foreach (array_keys($questions) as $index) $rules['ratings.'.$index]='required|integer|between:1,5';
            $data=$request->validate($rules);
            $answers=[];
            foreach ($questions as $index=>$question) $answers[]=$question.' — '.$data['ratings'][$index].'/5';
            if (!empty($data['comment'])) $answers[]='Comentario: '.$data['comment'];
            $response=Record::for('responses');
            $response->fill(['survey_id'=>$invitation->survey_id,'patient_id'=>$invitation->patient_id,'branch_id'=>$invitation->branch_id,
                'score'=>(int)round(array_sum($data['ratings'])/count($questions)),
                'answers'=>implode("\n",$answers),'question_snapshot'=>implode("\n",$questions)])->save();
            DB::table('survey_invitations')->where('id',$invitation->id)->update(['responded_at'=>now()->toIso8601String(),'updated_at'=>now()->toIso8601String()]);
            Audit::record('survey_received','survey_responses',$response->id,['invitation_id'=>$invitation->id,'channel'=>$invitation->channel]);
        });
        return redirect('/survey-complete');
    }

    private function invitation(string $token,bool $lock=false): object {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token),404);
        $query=DB::table('survey_invitations')->where('token_hash',hash('sha256',$token));
        if ($lock) $query->lockForUpdate();
        $invitation=$query->first();
        abort_unless($invitation,404);
        abort_if($invitation->responded_at || now()->gte($invitation->expires_at),410,'El enlace venció o ya fue utilizado.');
        abort_unless(DB::table('surveys')->where('id',$invitation->survey_id)->where('active',true)->exists(),410,'La encuesta ya no está disponible.');
        return $invitation;
    }

    private static function questions(string $text): array {
        return array_values(array_filter(array_map('trim',preg_split('/\R/u',$text)),fn($q)=>$q!==''));
    }
}

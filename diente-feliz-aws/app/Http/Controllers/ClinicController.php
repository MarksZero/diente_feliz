<?php
namespace App\Http\Controllers;
use App\Models\Record;
use App\Services\{Modules,Clinic,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate,DB,Storage,Crypt};

class ClinicController extends Controller {
    public function dashboard() {
        $appointments=auth()->user()->permits('appointments.view')?Clinic::query('appointments')->whereDate('starts_at',today())->orderBy('starts_at')->get():collect();
        $patients=auth()->user()->permits('patients.view')?Clinic::query('patients')->count():0;
        $pending=auth()->user()->permits('communication.view')?Clinic::query('recalls')->where('status','Pendiente')->count():0;
        return view('dashboard',compact('appointments','patients','pending'));
    }
    public function index(Request $r,string $module) {
        $definition=Modules::get($module); Gate::authorize($definition['permission'].'.view');
        $records=Clinic::filtered($module,$r)->orderByDesc('id')->paginate(20)->withQueryString();
        $options=Clinic::options($module);
        Audit::record('list',$definition['table']);
        return view('records',compact('module','definition','records','options'));
    }
    public function form(Request $r,string $module,?int $id=null) {
        $definition=Modules::get($module); abort_if(!empty($definition['readonly']),403); Gate::authorize($definition['permission'].'.write');
        $record=$id?Clinic::query($module)->findOrFail($id):null;
        if($record) Gate::authorize('update',$record);
        $options=Clinic::options($module);
        $branches=Clinic::query('branches')->pluck('name','id');
        return view('form',compact('module','definition','record','options','branches'));
    }
    public function store(Request $r,string $module) { $record=Clinic::save($module,$r); return redirect($module === 'odontogram' ? '/modules/patients/'.$record->patient_id.'#odontograma' : '/modules/'.$module.'/'.$record->id)->with('success','Registro guardado correctamente.'); }
    public function update(Request $r,string $module,int $id) { $record=Clinic::query($module)->findOrFail($id); Clinic::save($module,$r,$record); return redirect('/modules/'.$module.'/'.$id)->with('success','Cambios guardados.'); }
    public function show(Request $r,string $module,int $id) {
        $definition=Modules::get($module); $record=Clinic::query($module)->findOrFail($id); Gate::authorize('view',$record);
        Audit::record('view',$definition['table'],$id); $options=Clinic::options($module);
        $sections=[]; $teeth=collect();
        if($module==='patients') {
            foreach(['appointments','clinical','comments','documents','consents','budgets','payments','refunds','recalls'] as $section) {
                if(auth()->user()->permits(Modules::get($section)['permission'].'.view')) $sections[$section]=Clinic::query($section)->where('patient_id',$id)->latest()->limit(100)->get();
            }
            if(auth()->user()->permits('clinical.view')) $teeth=Clinic::query('odontogram')->where('patient_id',$id)->latest('id')->get();
        }
        return view('show',compact('module','definition','record','options','sections','teeth'));
    }
    public function download(int $id) {
        $record=Clinic::query('documents')->findOrFail($id); Gate::authorize('view',$record); Audit::record('download','documents',$id);
        $ext=['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg'][$record->mime];
        return Storage::disk('local')->download($record->path,'documento-'.$id.'.'.$ext,['Content-Type'=>'application/octet-stream','X-Content-Type-Options'=>'nosniff']);
    }
    public function export(Request $r,string $module) {
        Gate::authorize('exports.view'); $definition=Modules::get($module); Gate::authorize($definition['permission'].'.view');
        $r->validate(['format'=>'nullable|in:csv,html,zip','ids'=>'nullable|array|max:100','ids.*'=>'integer']);
        $query=Clinic::filtered($module,$r); if($r->filled('ids')) $query->whereIn('id',$r->input('ids'));
        $rows=$query->limit(100)->get(); foreach($rows as $row) Gate::authorize('view',$row);
        Audit::record('export',$definition['table'],null,['ids'=>$rows->pluck('id')->all(),'format'=>$r->input('format','csv')]);
        if($r->input('format')==='zip') {
            abort_unless($module==='documents',422);
            $path=tempnam(storage_path('app/private'),'export-'); $zip=new \ZipArchive(); $zip->open($path,\ZipArchive::OVERWRITE);
            foreach($rows as $row) { $ext=['application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg'][$row->mime]; $zip->addFile(Storage::disk('local')->path($row->path),'documento-'.$row->id.'.'.$ext); }
            $zip->close(); return response()->download($path,'documentos.zip')->deleteFileAfterSend(true);
        }
        if($r->input('format')==='html') return response()->view('export',compact('rows','definition','module'))->header('Content-Disposition','attachment; filename="reporte.html"');
        $fields=array_diff(array_keys($definition['fields']),['file','accepted']);
        return response()->streamDownload(function() use($rows,$fields) { $f=fopen('php://output','w'); fwrite($f,"\xEF\xBB\xBF"); fputcsv($f,['id',...$fields],';','"',''); foreach($rows as $row) { $values=[$row->id]; foreach($fields as $field) { $value=(string)$row->$field; if(preg_match('/^[\s]*[=+@\-\t\r]/u',$value)) $value="'".$value; $values[]=$value; } fputcsv($f,$values,';','"',''); } fclose($f); },$module.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function audit() { Gate::authorize('audit.view'); $events=DB::table('audit_events')->orderByDesc('id')->paginate(40); return view('audit',compact('events')); }
    public function reports(Request $r) {
        Gate::authorize('reports.view'); $r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $appointments=auth()->user()->permits('appointments.view')?Clinic::filtered('appointments',$r)->select('status',DB::raw('count(*) as count'))->groupBy('status')->get():collect();
        $budgets=auth()->user()->permits('finance.view')?Clinic::filtered('budgets',$r)->get():collect();
        $scores=auth()->user()->permits('communication.view')?Clinic::filtered('responses',$r)->avg('score'):null;
        Audit::record('report','reports'); return view('reports',compact('appointments','budgets','scores'));
    }
}

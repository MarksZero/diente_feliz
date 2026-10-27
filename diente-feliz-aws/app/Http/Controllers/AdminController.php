<?php
namespace App\Http\Controllers;
use App\Models\{User,Role,Branch};
use App\Services\{Audit,Modules,Clinic};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate,DB};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller {
    public function users() { Gate::authorize('users.view'); return view('users',['users'=>User::with('roles','branches')->orderBy('id')->get(),'roles'=>Role::all(),'branches'=>Branch::all()]); }
    public function saveUser(Request $r,?int $id=null) {
        Gate::authorize('users.write');
        $data=$r->validate(['name'=>'required|string|max:150','email'=>['required','email','max:200',Rule::unique('users')->ignore($id)],'password'=>[$id?'nullable':'required',Password::min(12)->mixedCase()->numbers()->symbols()],'active'=>'required|boolean','disabled_from'=>'nullable|date','disabled_until'=>'nullable|date|after:disabled_from','roles'=>'required|array|min:1|max:20','roles.*'=>'integer|exists:roles,id','branches'=>'required|array|min:1|max:20','branches.*'=>'integer|exists:branches,id']);
        DB::transaction(function() use($id,$data) {
            // Serialize administrator changes and prevent self lockout.
            $users=User::orderBy('id')->lockForUpdate()->get();
            $user=$id?$users->find($id):new User; abort_if($id && !$user,404);
            if($id===auth()->id()) {
                $keepsAdmin=Role::whereIn('id',$data['roles'])->get()->contains(fn($r)=>in_array('users.write',$r->permissions) && in_array('roles.write',$r->permissions));
                if(!$data['active'] || $data['disabled_from'] || !$keepsAdmin) Clinic::fail('roles','No puedes quitarte el acceso administrativo ni deshabilitar tu propia cuenta.');
            }
            $roles=$data['roles']; $branches=$data['branches']; unset($data['roles'],$data['branches']); if(empty($data['password'])) unset($data['password']);
            $data['email']=strtolower($data['email']); $data['session_version']=$user->exists?$user->session_version+1:1;
            $user->fill($data)->save(); $user->roles()->sync($roles); $user->branches()->sync($branches);
            Audit::record('user_access_changed','users',$user->id,['roles'=>$roles,'branches'=>$branches,'active'=>$data['active'],'disabled_from'=>$data['disabled_from'],'disabled_until'=>$data['disabled_until']]);
            if($id===auth()->id()) session()->put('session_version',$user->session_version);
        }); return back()->with('success','Usuario guardado. Las sesiones anteriores fueron revocadas.');
    }
    public function roles() { Gate::authorize('roles.view'); return view('roles',['roles'=>Role::all(),'permissions'=>Modules::permissions()]); }
    public function saveRole(Request $r,?int $id=null) {
        Gate::authorize('roles.write'); $data=$r->validate(['name'=>['required','string','max:100',Rule::unique('roles')->ignore($id)],'permissions'=>'required|array|min:1','permissions.*'=>['string',Rule::in(Modules::permissions())]]);
        DB::transaction(function() use($id,$data) {
            $role=$id?Role::lockForUpdate()->findOrFail($id):new Role;
            if($role->name==='Administrador general') Clinic::fail('name','El rol administrador base está protegido. Crea un rol adicional para personalizar permisos.');
            $role->fill($data)->save();
            User::whereHas('roles',fn($q)=>$q->where('roles.id',$role->id))->increment('session_version');
            Audit::record('role_changed','roles',$role->id,$data);
        }); return back()->with('success','Permisos guardados y sesiones revocadas.');
    }
}

<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller {
    public function form() { return view('login'); }
    public function login(Request $r) {
        $data=$r->validate(['email'=>'required|email|max:200','password'=>'required|string|max:200']);
        $key='login:'.hash('sha256',strtolower($data['email'])); $ip='login-ip:'.$r->ip();
        if(RateLimiter::tooManyAttempts($key,5)||RateLimiter::tooManyAttempts($ip,30)) return back()->withErrors(['email'=>'Demasiados intentos. Espera 15 minutos antes de volver a intentar.'])->setStatusCode(429);
        $user=User::where('email',strtolower($data['email']))->first();
        $valid=Hash::check($data['password'],$user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if(!$user || !$valid || !$user->enabled()) {
            RateLimiter::hit($key,900); RateLimiter::hit($ip,900); Audit::record('login_failed','authentication');
            return back()->withErrors(['email'=>'No fue posible iniciar sesión con esos datos.']);
        }
        RateLimiter::clear($key); Auth::login($user,false); $r->session()->regenerate(); $r->session()->put('session_version',$user->session_version);
        Audit::record('login','users',$user->id);
        return redirect()->intended('/');
    }
    public function logout(Request $r) { Audit::record('logout','users',auth()->id()); Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login'); }
    public function password(Request $r) {
        $data=$r->validate(['current_password'=>'required|current_password','password'=>['required','confirmed',Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $r->user()->update(['password'=>$data['password'],'session_version'=>$r->user()->session_version+1]);
        $r->session()->put('session_version',$r->user()->session_version); $r->session()->regenerate();
        Audit::record('password_changed','users',auth()->id()); return back()->with('success','Contraseña actualizada. Se invalidaron las otras sesiones.');
    }
}

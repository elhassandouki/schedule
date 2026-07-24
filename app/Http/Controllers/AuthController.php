<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller {
 public function create(){ return view('auth.login'); }
 public function store(Request $request){ $data=$request->validate(['email'=>'required|email','password'=>'required']); if(Auth::attempt($data,$request->boolean('remember'))){$request->session()->regenerate();return redirect()->intended(route('dashboard'));} return back()->withErrors(['email'=>'Identifiants incorrects.'])->onlyInput('email'); }
 public function destroy(Request $request){ Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}

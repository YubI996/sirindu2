<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = AppServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
        $input = $request->all();

        $this->validate($request, [
            'email'    => 'required',
            'password' => 'required',
        ]);

        if (auth()->attempt(['email' => $input['email'], 'password' => $input['password']])) {
            $user = auth()->user();

            // Redirect berdasarkan role sistem baru + legacy type
            return match(true) {
                // Dinkes: superadmin — akses ke admin dashboard
                // (isSuperAdmin juga mengenali legacy type=1/'super-admin')
                $user->isSuperAdmin()          => redirect()->route('admin.home'),

                // Faskes surveilans (Puskesmas / RS) → dashboard epidemiologi
                $user->isFaskesSurveilans()    => redirect()->route('admin.epidemiologi.dashboard'),

                // Faskes imunisasi → admin dashboard (modul imunisasi)
                $user->isFaskesImunisasi()     => redirect()->route('admin.home'),

                // Legacy admin (type lama) → admin dashboard
                in_array($user->type, [1, 2])  => redirect()->route('admin.home'),

                // Fallback → admin dashboard
                default                        => redirect()->route('admin.home'),
            };
        } else {
            return redirect()->route('login')
                ->with('error', 'Email atau password salah. Silakan coba lagi.');
        }
    }
}

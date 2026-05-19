<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        $this->verifyRecaptcha($request);

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

    private function verifyRecaptcha(Request $request): void
    {
        $token = $request->input('g-recaptcha-response');

        if (empty($token)) {
            $this->failRecaptcha('Verifikasi keamanan diperlukan. Pastikan JavaScript aktif di browser Anda.');
        }

        try {
            $response = Http::timeout(5)->asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]
            );

            if (!$response->successful()) {
                return; // fail open jika API Google tidak tersedia
            }

            $result = $response->json();

            if (!($result['success'] ?? false)) {
                $this->failRecaptcha('Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.');
            }

            $score = $result['score'] ?? 1.0;
            if ($score < config('services.recaptcha.threshold', 0.5)) {
                $this->failRecaptcha('Aktivitas mencurigakan terdeteksi. Silakan coba lagi beberapa saat.');
            }
        } catch (\Exception) {
            // Gagal terhubung ke Google — izinkan login agar tidak memblokir pengguna
        }
    }

    private function failRecaptcha(string $message): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => $message,
        ]);
    }
}

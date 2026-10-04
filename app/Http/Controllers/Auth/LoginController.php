<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * The stock failure says the credentials do not match, which is untrue for
     * a deactivated account: the row is still there and one click from
     * reactivation.
     *
     * The state is revealed only after the password is known to be correct.
     * Reporting a trashed row on the email alone would turn the sign-in form
     * into an account-existence oracle, so every other failure keeps the
     * stock wording.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $message = trans('auth.failed');

        $user = User::withTrashed()
            ->where($this->username(), $request->input($this->username()))
            ->first();

        if ($user !== null && $user->trashed() && Hash::check((string) $request->input('password'), $user->password)) {
            $message = 'This account has been deactivated. Ask an administrator to reactivate it.';
        }

        throw ValidationException::withMessages([
            $this->username() => [$message],
        ]);
    }
}

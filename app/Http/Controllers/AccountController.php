<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Rbac;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A Customer's own account. Access is a role check rather than a permission:
 * CUSTOMER_PERMISSIONS carries a documented "nothing that writes" invariant, and
 * an account.manage permission would falsify that comment and put a row on the
 * roles matrix the Superadmin does not hold.
 */
class AccountController extends Controller
{
    public function __construct()
    {
        // One guard for all four actions. Spatie 8.3.0 registers no 'role'
        // middleware alias, so ->middleware('role:...') would throw; the closure
        // matches the constructor-middleware habit in RegisterController and
        // LoginController. A Superadmin or Admin gets 403 on GET and on POST.
        $this->middleware(function ($request, $next) {
            abort_unless($request->user()?->hasRole(Rbac::CUSTOMER_ROLE), 403);

            return $next($request);
        });
    }

    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['phone'] = PhilippineMobileNumber::normalise($data['phone']);

        $request->user()->update($data);

        return back()->with('success', 'Your account details were updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        // The User model casts password to 'hashed', so the plain string is
        // hashed exactly once. Calling Hash::make here would double-hash it.
        $request->user()->update(['password' => $request->validated()['password']]);

        return back()->with('success', 'Your password was updated.');
    }

    /**
     * Deactivate only: a soft delete a Superadmin can undo, keeping the addresses
     * in the book with their owner still attached. Every owner-reaching path
     * already reads the user through withTrashed, so nothing else has to change.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account has been deactivated.');
    }
}

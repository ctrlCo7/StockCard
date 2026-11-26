<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSignatureRequest;
use App\Services\AuditLogger;
use App\Services\SignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('roles', 'office');

        return view('profile.edit', [
            'user' => $user,
            'signature' => app(SignatureService::class)->dataUri($user->signature_path),
        ]);
    }

    public function updateSignature(UpdateSignatureRequest $request, SignatureService $signatures): RedirectResponse
    {
        $signatures->store($request->user(), $request->file('signature_file') ?? $request->validated('signature_data'));

        return back()->with('success', 'Your electronic signature has been saved. It will be applied to the requisitions you sign from now on.');
    }

    public function destroySignature(Request $request, SignatureService $signatures): RedirectResponse
    {
        $signatures->remove($request->user());

        return back()->with('success', 'Your electronic signature has been removed. Documents you already signed keep their signature.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        AuditLogger::log('user.password_changed', "{$request->user()->name} changed their password", $request->user());

        return back()->with('success', 'Your password has been updated.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:en,ar'],
        ]);

        if ($request->user()) {
            $request->user()->update(['locale' => $data['locale']]);
        }

        return back()->withCookie(cookie('locale', $data['locale'], 60 * 24 * 365));
    }
}

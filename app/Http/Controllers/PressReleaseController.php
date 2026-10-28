<?php

namespace App\Http\Controllers;

use App\Models\Polda;
use App\Models\PressRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PressReleaseController extends Controller
{
    /**
     * Store an official press release.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'polda_id'          => 'required|exists:poldas,id',
            'polres_id'         => 'nullable|exists:polres,id',
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'talking_points'    => 'nullable|array',
            'dos_and_donts'     => 'nullable|array',
            'spokesperson_name' => 'nullable|string|max:100',
        ]);

        $polda = Polda::find($validated['polda_id']);
        $poldaCode = $polda ? $polda->code : 'POLRI';
        $pressNum = 'B/SP-' . rand(10, 99) . '/' . date('n') . '/HUM.6.1.1/' . date('Y') . '/' . $poldaCode;

        PressRelease::create([
            'polda_id'          => $validated['polda_id'],
            'polres_id'         => $validated['polres_id'] ?? null,
            'author_id'         => null,
            'title'             => $validated['title'],
            'press_number'      => $pressNum,
            'content'           => $validated['content'],
            'talking_points'    => $validated['talking_points'] ?? [],
            'dos_and_donts'     => $validated['dos_and_donts'] ?? [],
            'status'            => 'published',
            'spokesperson_name' => $validated['spokesperson_name'] ?? ($polda->kabid_humas_name ?? 'Bidhumas Polda'),
            'published_at'      => now(),
        ]);

        return redirect()->back()->with('success', 'Siaran Pers Resmi berhasil diterbitkan!');
    }
}


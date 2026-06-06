<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInstruction;
use Illuminate\Http\Request;

class AiInstructionController extends Controller
{
    public function index()
    {
        $instructions = AiInstruction::query()
            ->orderBy('name')
            ->get();

        return view('admin.ai_instructions.index', compact('instructions'));
    }

    public function edit(AiInstruction $ai_instruction)
    {
        return view('admin.ai_instructions.edit', [
            'instruction' => $ai_instruction,
        ]);
    }

    public function update(Request $request, AiInstruction $ai_instruction)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'instruction' => ['required', 'string'],
        ]);

        $ai_instruction->update($data);

        return redirect()
            ->route('admin.ai-instructions.index')
            ->with('success', 'AI instruction saved.');
    }
}

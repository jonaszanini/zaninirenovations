@extends('admin.layouts.app')

@section('heading', 'Edit AI instruction')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.ai-instructions.update', $instruction) }}" method="post">
      @csrf
      @method('PUT')
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="ai-key">Key</label>
          <x-admin.info-tip label="Key">Internal identifier. Cannot be changed.</x-admin.info-tip>
        </span>
        <input class="admin-input" type="text" id="ai-key" value="{{ $instruction->key }}" class="admin-input" disabled>
      </div>
      <div class="admin-form-group">
        <label for="ai-name">Name</label>
        <input class="admin-input" type="text" id="ai-name" name="name" value="{{ old('name', $instruction->name) }}" required maxlength="120" class="admin-input">
        @error('name')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="ai-instruction">Instruction</label>
          <x-admin.info-tip label="Instruction">Sent as the Gemini <code>systemInstruction</code> for this feature.</x-admin.info-tip>
        </span>
        <textarea id="ai-instruction" name="instruction" rows="28" required class="admin-input" style="font-family:Consolas,Monaco,'Courier New',monospace;font-size:12px;line-height:1.45;">{{ old('instruction', $instruction->instruction) }}</textarea>
        @error('instruction')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-actions">
        <button type="submit" id="ai-btn-save" class="admin-btn admin-btn--primary" data-hotkey-save>Save changes</button>
        <a href="{{ route('admin.ai-instructions.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>
  </div>
@endsection

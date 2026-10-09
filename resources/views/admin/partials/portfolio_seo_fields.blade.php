@php
  $seoProject = $project ?? null;
@endphp
<div class="admin-form-group">
  <span class="admin-label-with-tip">
    <label for="slug">Page address</label>
    <x-admin.info-tip label="Page address">
      @if ($seoProject)
        This is the public web address. Renaming the project does not change it.
        @if ($seoProject->slug)
          <a href="{{ $seoProject->publicUrl() }}" target="_blank" rel="noopener">View page</a>
        @endif
      @else
        Leave blank to build the address from the title. The public page goes live when you save.
      @endif
    </x-admin.info-tip>
  </span>
  <input class="admin-input" type="text" id="slug" name="slug" value="{{ old('slug', $seoProject->slug ?? '') }}" maxlength="180" placeholder="deck-for-gazebo" autocomplete="off" spellcheck="false">
  @error('slug')
    <div class="admin-form-error">{{ $message }}</div>
  @enderror
</div>
<div class="admin-form-group">
  <span class="admin-label-with-tip">
    <label for="meta_title">SEO title</label>
    <x-admin.info-tip label="SEO title">Shown in Google. Leave blank to use the project title.</x-admin.info-tip>
  </span>
  <input class="admin-input" type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $seoProject->meta_title ?? '') }}" maxlength="255" placeholder="Optional — defaults to the project title">
  @error('meta_title')
    <div class="admin-form-error">{{ $message }}</div>
  @enderror
</div>
<div class="admin-form-group">
  <span class="admin-label-with-tip">
    <label for="meta_description">SEO description</label>
    <x-admin.info-tip label="SEO description">Short summary for Google, about 160 characters. Leave blank to use the start of the description.</x-admin.info-tip>
  </span>
  <textarea class="admin-input" id="meta_description" name="meta_description" maxlength="320" rows="3" placeholder="Optional — defaults to the start of the description">{{ old('meta_description', $seoProject->meta_description ?? '') }}</textarea>
  @error('meta_description')
    <div class="admin-form-error">{{ $message }}</div>
  @enderror
</div>

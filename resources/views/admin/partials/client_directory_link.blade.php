{{--
  Display a client label linked to the central client record when we know
  the directory id. Defaults to the edit page; pass $url to open View instead.

  Params:
    project        ClientProject — uses client_id (+ client_name if label omitted)
    client         Client — uses id (+ name if label omitted)
    clientId       int|string|null — explicit directory id
    url            string|null — override href (default: admin.clients.edit)
    label          string|null — text to show (default: project/client name)
    class          string — CSS classes on the <a> or <span>
    title          string|null — link title (default: Open client record)
    fallbackMailto bool — if no client id and label looks like an email, mailto:
    empty          string — when label is blank (default —)

  Never use `$label` as a @foreach value variable in parent views before this
  include — Blade leaks loop vars into partials (e.g. last status "Rejected").
--}}
@php
  $project = $project ?? null;
  $client = $client ?? null;
  $clientId = isset($clientId) ? (int) $clientId : null;
  $class = trim((string) ($class ?? ''));
  $empty = $empty ?? '—';
  $fallbackMailto = (bool) ($fallbackMailto ?? false);
  $title = $title ?? 'Open client record';

  if (! $clientId || $clientId <= 0) {
      if ($project && (int) $project->client_id > 0) {
          $clientId = (int) $project->client_id;
      } elseif ($client && (int) $client->id > 0) {
          $clientId = (int) $client->id;
      } else {
          $clientId = null;
      }
  }

  if (! isset($label)) {
      if ($project) {
          $label = $project->client_name;
      } elseif ($client) {
          $label = $client->name;
      } else {
          $label = null;
      }
  }

  $labelText = trim((string) ($label ?? ''));
  $linkClass = trim('admin-client-link'.($class !== '' ? ' '.$class : ''));
  $url = isset($url) ? (string) $url : '';
  if ($url === '' && $clientId) {
      $url = route('admin.clients.edit', $clientId);
  }
@endphp
@if ($labelText === '')
  <span @if ($class !== '') class="{{ $class }}" @endif>{{ $empty }}</span>
@elseif ($url !== '')
  <a href="{{ $url }}"
     class="{{ $linkClass }}"
     title="{{ $title }}">{{ $labelText }}</a>
@elseif ($fallbackMailto && str_contains($labelText, '@'))
  <a href="mailto:{{ $labelText }}" class="{{ $linkClass }}">{{ $labelText }}</a>
@else
  <span @if ($class !== '') class="{{ $class }}" @endif>{{ $labelText }}</span>
@endif

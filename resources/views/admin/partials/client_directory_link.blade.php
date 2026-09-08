{{--
  Display a client label linked to the central client edit page when we know
  the directory id. Use for read-only client name (or email) anywhere in admin.

  Params:
    project        ClientProject — uses client_id (+ client_name if label omitted)
    client         Client — uses id (+ name if label omitted)
    clientId       int|string|null — explicit directory id
    label          string|null — text to show (default: project/client name)
    class          string — CSS classes on the <a> or <span>
    title          string|null — link title (default: Open client record)
    fallbackMailto bool — if no client id and label looks like an email, mailto:
    empty          string — when label is blank (default —)
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
@endphp
@if ($labelText === '')
  <span @if ($class !== '') class="{{ $class }}" @endif>{{ $empty }}</span>
@elseif ($clientId)
  <a href="{{ route('admin.clients.edit', $clientId) }}"
     class="{{ $linkClass }}"
     title="{{ $title }}">{{ $labelText }}</a>
@elseif ($fallbackMailto && str_contains($labelText, '@'))
  <a href="mailto:{{ $labelText }}" class="{{ $linkClass }}">{{ $labelText }}</a>
@else
  <span @if ($class !== '') class="{{ $class }}" @endif>{{ $labelText }}</span>
@endif

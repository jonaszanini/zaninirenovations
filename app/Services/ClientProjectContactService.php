<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientProject;

/**
 * Links projects to the clients directory, keeps directory rows in sync from
 * project forms, and captures frozen client snapshots when a project completes.
 */
class ClientProjectContactService
{
    /**
     * @param  array<string, mixed>  $data  Validated project payload (mutated in place).
     */
    public function applyForCreate(array &$data, bool $syncDirectory = true): void
    {
        $clientId = isset($data['client_id']) && $data['client_id'] !== '' && $data['client_id'] !== null
            ? (int) $data['client_id']
            : null;

        if ($clientId === null) {
            $clientId = $this->resolveOrCreateClientFromProjectContact($data);
        } elseif ($syncDirectory) {
            $this->syncDirectoryFromProjectContact($clientId, $data);
        }

        $data['client_id'] = $clientId;
    }

    /**
     * @param  array<string, mixed>  $data  Validated project payload (mutated in place).
     */
    public function applyForUpdate(ClientProject $project, array &$data, bool $syncDirectory = true): void
    {
        if ($project->isCompleted()) {
            $this->stripClientFieldsFromData($data);

            return;
        }

        $clientId = array_key_exists('client_id', $data)
            ? ($data['client_id'] !== '' && $data['client_id'] !== null ? (int) $data['client_id'] : null)
            : $project->client_id;

        if ($clientId === null) {
            $clientId = $this->resolveOrCreateClientFromProjectContact($this->mergeProjectContact($project, $data));
        } elseif ($syncDirectory) {
            $this->syncDirectoryFromProjectContact($clientId, $this->mergeProjectContact($project, $data));
        }

        $data['client_id'] = $clientId;
    }

    /** Ensure quotation shortcuts and legacy rows gain a directory link. */
    public function ensureProjectLinkedToDirectory(ClientProject $project): void
    {
        if ($this->projectHasValidClientLink($project)) {
            return;
        }

        $clientId = $this->resolveOrCreateClientFromProjectContact(
            $this->contactDataFromProjectRow($project)
        );

        if ($clientId) {
            $project->forceFill(['client_id' => $clientId])->saveQuietly();
        }
    }

    /**
     * Link a project to the clients directory using stored project contact fields.
     *
     * @return 'already_linked'|'linked_existing'|'created'|'skipped_no_name'
     */
    public function linkProjectToClientDirectory(ClientProject $project, bool $dryRun = false): string
    {
        if ($this->projectHasValidClientLink($project)) {
            return 'already_linked';
        }

        $contact = $this->contactDataFromProjectRow($project);
        $name = trim((string) ($contact['client_name'] ?? ''));
        if ($name === '') {
            return 'skipped_no_name';
        }

        $existing = $this->findMatchingClient(
            $name,
            $contact['client_email'] ?? null,
            $contact['client_phone'] ?? null
        );

        if ($existing) {
            if (! $dryRun) {
                $project->forceFill(['client_id' => $existing->id])->saveQuietly();
            }

            return 'linked_existing';
        }

        if ($dryRun) {
            return 'created';
        }

        $clientId = $this->createClientFromProjectContact($contact);
        if ($clientId) {
            $project->forceFill(['client_id' => $clientId])->saveQuietly();
        }

        return $clientId ? 'created' : 'skipped_no_name';
    }

    public function projectHasValidClientLink(ClientProject $project): bool
    {
        if (! $project->client_id) {
            return false;
        }

        return Client::query()->whereKey($project->client_id)->exists();
    }

    /**
     * Raw project-row contact fields (ignores completion snapshot accessors).
     *
     * @return array<string, mixed>
     */
    public function contactDataFromProjectRow(ClientProject $project): array
    {
        $attrs = $project->getAttributes();
        $data = [];
        foreach (ClientProject::clientContactFieldKeys() as $key) {
            $data[$key] = $attrs[$key] ?? null;
        }

        return $data;
    }

    public function findMatchingClient(?string $name, ?string $email, ?string $phone): ?Client
    {
        $emailNorm = $this->normalizeEmail($email);
        if ($emailNorm !== '') {
            $match = Client::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
                ->orderBy('id')
                ->first();
            if ($match) {
                return $match;
            }
        }

        $nameNorm = strtolower(trim((string) $name));
        $phoneNorm = $this->normalizePhoneDigits($phone);
        if ($nameNorm !== '' && $phoneNorm !== '') {
            return Client::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [$nameNorm])
                ->where('phone', $phoneNorm)
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveOrCreateClientFromProjectContact(array $data): ?int
    {
        $name = trim((string) ($data['client_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $existing = $this->findMatchingClient(
            $name,
            $data['client_email'] ?? null,
            $data['client_phone'] ?? null
        );

        if ($existing) {
            return $existing->id;
        }

        return $this->createClientFromProjectContact($data);
    }

    protected function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    protected function normalizePhoneDigits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    public function captureClientSnapshot(ClientProject $project): void
    {
        if ($project->client_snapshot_captured_at !== null) {
            return;
        }

        $attrs = $project->getAttributes();
        $snapshot = [];
        foreach (ClientProject::clientSnapshotKeys() as $key) {
            $snapshot[$key] = $attrs[$key] ?? null;
        }

        $project->forceFill([
            'client_snapshot' => $snapshot,
            'client_snapshot_captured_at' => now(),
        ])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createClientFromProjectContact(array $data): ?int
    {
        $name = trim((string) ($data['client_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $client = Client::create([
            'name' => $name,
            'email' => filled($data['client_email'] ?? null) ? $data['client_email'] : null,
            'email_secondary' => filled($data['client_email_secondary'] ?? null) ? $data['client_email_secondary'] : null,
            'phone' => filled($data['client_phone'] ?? null) ? $data['client_phone'] : null,
            'address' => filled($data['client_address'] ?? null) ? $data['client_address'] : null,
            'address_place_id' => filled($data['client_address_place_id'] ?? null) ? $data['client_address_place_id'] : null,
            'address_latitude' => $data['client_address_latitude'] ?? null,
            'address_longitude' => $data['client_address_longitude'] ?? null,
        ]);

        return $client->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncDirectoryFromProjectContact(int $clientId, array $data): void
    {
        $client = Client::query()->find($clientId);
        if (! $client) {
            return;
        }

        $name = trim((string) ($data['client_name'] ?? ''));
        if ($name === '') {
            return;
        }

        $client->update([
            'name' => $name,
            'email' => filled($data['client_email'] ?? null) ? $data['client_email'] : null,
            'email_secondary' => filled($data['client_email_secondary'] ?? null) ? $data['client_email_secondary'] : null,
            'phone' => filled($data['client_phone'] ?? null) ? $data['client_phone'] : null,
            'address' => filled($data['client_address'] ?? null) ? $data['client_address'] : null,
            'address_place_id' => filled($data['client_address_place_id'] ?? null) ? $data['client_address_place_id'] : null,
            'address_latitude' => $data['client_address_latitude'] ?? null,
            'address_longitude' => $data['client_address_longitude'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeProjectContact(ClientProject $project, array $data): array
    {
        $merged = [];
        foreach (ClientProject::clientContactFieldKeys() as $key) {
            $merged[$key] = array_key_exists($key, $data)
                ? $data[$key]
                : $project->getRawOriginal($key);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function stripClientFieldsFromData(array &$data): void
    {
        foreach (array_merge(
            ['client_id'],
            ClientProject::clientContactFieldKeys()
        ) as $key) {
            unset($data[$key]);
        }
    }
}

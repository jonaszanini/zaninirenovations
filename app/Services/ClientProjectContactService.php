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
            $clientId = $this->createClientFromProjectContact($data);
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
            $clientId = $this->createClientFromProjectContact($this->mergeProjectContact($project, $data));
        } elseif ($syncDirectory) {
            $this->syncDirectoryFromProjectContact($clientId, $this->mergeProjectContact($project, $data));
        }

        $data['client_id'] = $clientId;
    }

    /** Ensure quotation shortcuts and legacy rows gain a directory link. */
    public function ensureProjectLinkedToDirectory(ClientProject $project): void
    {
        if ($project->client_id) {
            return;
        }

        $clientId = $this->createClientFromProjectContact([
            'client_name' => $project->getRawOriginal('client_name') ?? $project->client_name,
            'client_email' => $project->getRawOriginal('client_email') ?? $project->client_email,
            'client_email_secondary' => $project->getRawOriginal('client_email_secondary') ?? $project->client_email_secondary,
            'client_phone' => $project->getRawOriginal('client_phone') ?? $project->client_phone,
            'client_address' => $project->getRawOriginal('client_address') ?? $project->client_address,
        ]);

        if ($clientId) {
            $project->forceFill(['client_id' => $clientId])->saveQuietly();
        }
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

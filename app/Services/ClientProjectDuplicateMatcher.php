<?php

namespace App\Services;

use App\Models\ClientProject;
use Illuminate\Support\Collection;

/**
 * Finds existing client projects that look like the same job as a draft
 * create form: same client or site, plus a similar project name.
 *
 * Name matching is token-based (word overlap with light fuzzy matching)
 * so "Front deck" and "Front Deck Repair" collide, but "Deck" and
 * "Kitchen" for the same client do not.
 */
class ClientProjectDuplicateMatcher
{
    public const NAME_SIMILARITY_THRESHOLD = 0.72;

    public const MAX_RESULTS = 5;

    /** @var list<string> */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'of', 'for', 'and', 'at', 'in', 'on', 'to', 'with',
        'project', 'renovation', 'reno', 'job', 'work', 'quote',
    ];

    /**
     * Directional words that should not be the only reason two names match
     * ("Front patio" vs "Front deck").
     *
     * @var list<string>
     */
    private const WEAK_WORDS = [
        'new', 'old', 'my', 'our', 'small', 'large', 'front', 'back', 'rear',
        'left', 'right', 'side', 'main', 'first', 'second',
    ];

    /**
     * @return Collection<int, ClientProject>
     */
    public function findCandidates(
        string $projectName,
        ?int $clientId = null,
        ?string $clientName = null,
        ?string $clientEmail = null,
        ?string $clientPhone = null,
        ?string $clientAddress = null,
        ?string $placeId = null,
        ?int $excludeId = null,
    ): Collection {
        $projectName = trim($projectName);
        if ($projectName === '') {
            return collect();
        }

        $clientId = $clientId && $clientId > 0 ? $clientId : null;
        $emailNorm = strtolower(trim((string) $clientEmail));
        $phoneDigits = $this->phoneLast10($clientPhone);
        $nameNorm = strtolower(trim((string) $clientName));
        $placeId = trim((string) $placeId);
        $address = trim((string) $clientAddress);

        $hasClientSignal = $clientId !== null
            || $emailNorm !== ''
            || $phoneDigits !== null
            || $nameNorm !== ''
            || $placeId !== ''
            || strlen($address) >= 8;

        if (! $hasClientSignal) {
            return collect();
        }

        $candidates = ClientProject::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($clientId, $emailNorm, $phoneDigits, $nameNorm, $placeId, $address) {
                $q->whereRaw('1 = 0');
                if ($clientId !== null) {
                    $q->orWhere('client_id', $clientId);
                }
                if ($emailNorm !== '') {
                    $q->orWhereRaw('LOWER(TRIM(client_email)) = ?', [$emailNorm])
                        ->orWhereRaw('LOWER(TRIM(client_email_secondary)) = ?', [$emailNorm]);
                }
                if ($phoneDigits !== null) {
                    $q->orWhere('client_phone', $phoneDigits)
                        ->orWhereRaw(
                            "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), '+', ''), 10) = ?",
                            [$phoneDigits]
                        );
                }
                if ($nameNorm !== '') {
                    $q->orWhereRaw('LOWER(TRIM(client_name)) = ?', [$nameNorm]);
                }
                if ($placeId !== '') {
                    $q->orWhere('client_address_place_id', $placeId);
                }
                if (strlen($address) >= 8) {
                    $needle = $this->likeEscape($this->addressSearchNeedle($address));
                    $q->orWhereRaw('LOWER(client_address) LIKE ?', ['%'.$needle.'%']);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        return $candidates
            ->filter(function (ClientProject $existing) use (
                $projectName, $clientId, $emailNorm, $phoneDigits, $nameNorm, $placeId, $address
            ) {
                if (! $this->sameClientOrSite($existing, $clientId, $emailNorm, $phoneDigits, $nameNorm, $placeId, $address)) {
                    return false;
                }

                return $this->nameSimilarity($projectName, (string) $existing->name) >= self::NAME_SIMILARITY_THRESHOLD;
            })
            ->sortByDesc(fn (ClientProject $p) => $this->nameSimilarity($projectName, (string) $p->name))
            ->take(self::MAX_RESULTS)
            ->values();
    }

    public function nameSimilarity(string $a, string $b): float
    {
        $na = $this->normalizeName($a);
        $nb = $this->normalizeName($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }

        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $shorter = count($ta) <= count($tb) ? $ta : $tb;
        $longer = count($ta) <= count($tb) ? $tb : $ta;
        $matched = [];
        $used = [];

        foreach ($shorter as $token) {
            foreach ($longer as $i => $other) {
                if (isset($used[$i])) {
                    continue;
                }
                if ($this->tokensSimilar($token, $other)) {
                    $matched[] = $token;
                    $used[$i] = true;
                    break;
                }
            }
        }

        if ($matched === []) {
            return 0.0;
        }

        $strong = array_filter($matched, fn (string $t) => ! in_array($t, self::WEAK_WORDS, true));
        if ($strong === []) {
            return 0.0;
        }

        $coverage = count($matched) / count($shorter);
        $union = count($ta) + count($tb) - count($matched);
        $jaccard = $union > 0 ? count($matched) / $union : 0.0;

        return max($coverage, $jaccard);
    }

    public function addressesLikelySame(?string $a, ?string $b): bool
    {
        $na = $this->normalizeAddress($a);
        $nb = $this->normalizeAddress($b);
        if ($na === '' || $nb === '' || strlen($na) < 8 || strlen($nb) < 8) {
            return false;
        }
        if ($na === $nb) {
            return true;
        }

        $short = strlen($na) <= strlen($nb) ? $na : $nb;
        $long = strlen($na) <= strlen($nb) ? $nb : $na;
        if (strlen($short) >= 12 && str_starts_with($long, $short)) {
            return true;
        }

        $coreA = $this->addressSearchNeedle($a ?? '');
        $coreB = $this->addressSearchNeedle($b ?? '');
        if (strlen($coreA) >= 8 && $coreA === $coreB) {
            return true;
        }

        similar_text($na, $nb, $percent);

        return $percent >= 82.0;
    }

    public function normalizeName(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = preg_replace('/[^a-z0-9\s]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Payload for the create-form warning list / live JSON check.
     *
     * @return array{id:int,name:string,reference:string,status:string,client_name:?string,client_address:?string,url:string}
     */
    public function toWarningRow(ClientProject $project): array
    {
        return [
            'id' => (int) $project->id,
            'name' => (string) $project->name,
            'reference' => $project->referenceCode(),
            'status' => $project->statusLabel(),
            'client_name' => $project->client_name,
            'client_address' => $project->client_address,
            'url' => route('admin.client-projects.show', $project),
        ];
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        $norm = $this->normalizeName($name);
        if ($norm === '') {
            return [];
        }

        $words = explode(' ', $norm);

        return array_values(array_filter($words, function (string $w) {
            return strlen($w) >= 2 && ! in_array($w, self::STOP_WORDS, true);
        }));
    }

    private function tokensSimilar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $sa = $this->lightStem($a);
        $sb = $this->lightStem($b);
        if ($sa === $sb && strlen($sa) >= 3) {
            return true;
        }

        $min = min(strlen($sa), strlen($sb));
        $max = max(strlen($sa), strlen($sb));
        if ($min >= 3 && $max <= $min + 2 && (str_starts_with($sa, $sb) || str_starts_with($sb, $sa))) {
            return true;
        }

        $rawMin = min(strlen($a), strlen($b));
        if ($rawMin >= 4 && levenshtein($a, $b) <= 1) {
            return true;
        }

        if ($rawMin >= 4) {
            similar_text($a, $b, $percent);
            if ($percent >= 85.0) {
                return true;
            }
        }

        return false;
    }

    private function lightStem(string $word): string
    {
        if (strlen($word) > 5 && str_ends_with($word, 'ing')) {
            return substr($word, 0, -3);
        }
        if (strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3).'y';
        }
        if (strlen($word) > 4 && str_ends_with($word, 'es')) {
            return substr($word, 0, -2);
        }
        if (strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function sameClientOrSite(
        ClientProject $existing,
        ?int $clientId,
        string $emailNorm,
        ?string $phoneDigits,
        string $nameNorm,
        string $placeId,
        string $address,
    ): bool {
        if ($clientId !== null && (int) $existing->client_id === $clientId) {
            return true;
        }

        if ($emailNorm !== '') {
            $existingEmail = strtolower(trim((string) $existing->client_email));
            $existingSecondary = strtolower(trim((string) $existing->client_email_secondary));
            if ($existingEmail === $emailNorm || $existingSecondary === $emailNorm) {
                return true;
            }
        }

        if ($phoneDigits !== null && $this->phoneLast10($existing->client_phone) === $phoneDigits) {
            return true;
        }

        if ($nameNorm !== '' && strtolower(trim((string) $existing->client_name)) === $nameNorm) {
            return true;
        }

        if ($placeId !== '' && (string) $existing->client_address_place_id === $placeId) {
            return true;
        }

        return $this->addressesLikelySame($address, $existing->client_address);
    }

    private function phoneLast10(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            return null;
        }

        return $digits;
    }

    private function normalizeAddress(?string $address): string
    {
        $s = mb_strtolower(trim((string) $address));
        if ($s === '') {
            return '';
        }

        $s = preg_replace('/\b(unit|apt|apartment|suite)\s*[a-z0-9-]+\b/u', ' ', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9\s]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function addressSearchNeedle(string $address): string
    {
        $norm = $this->normalizeAddress($address);
        $words = array_slice(explode(' ', $norm), 0, 3);

        return implode(' ', $words);
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

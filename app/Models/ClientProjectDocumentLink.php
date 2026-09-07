<?php

namespace App\Models;

use App\Services\ClientProjectDocumentLinkService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectDocumentLink extends Model
{
    public const KIND_WORD = 'word';

    public const KIND_EXCEL = 'excel';

    public const KIND_PPT = 'ppt';

    public const KIND_OTHER = 'other';

    protected $fillable = [
        'client_project_id',
        'url',
        'title',
        'kind',
        'sort_order',
    ];

    protected $casts = [
        'client_project_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function resolvedKind(): string
    {
        $kind = (string) $this->getAttribute('kind');
        if (in_array($kind, [self::KIND_WORD, self::KIND_EXCEL, self::KIND_PPT, self::KIND_OTHER], true)) {
            return $kind;
        }

        return self::KIND_OTHER;
    }

    public function kindLabel(): string
    {
        return match ($this->resolvedKind()) {
            self::KIND_WORD => 'Word',
            self::KIND_EXCEL => 'Excel',
            self::KIND_PPT => 'PowerPoint',
            default => 'Link',
        };
    }

    public function iconClass(): string
    {
        return match ($this->resolvedKind()) {
            self::KIND_WORD => 'fa-file-word-o',
            self::KIND_EXCEL => 'fa-file-excel-o',
            self::KIND_PPT => 'fa-file-powerpoint-o',
            default => 'fa-external-link',
        };
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        return ClientProjectDocumentLinkService::suggestTitle((string) $this->url);
    }
}

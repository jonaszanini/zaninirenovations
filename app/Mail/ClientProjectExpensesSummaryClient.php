<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Sends the client a table of project expenses and attaches receipt files (WebP or PDF) when present.
 */
class ClientProjectExpensesSummaryClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Project expenses: '.$this->project->name.' — '.config('mail.from.name', 'Zanini Renovations');

        $this->subject($subject)
            ->view('emails.client_projects.client_expenses_summary', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ]);

        foreach ($this->project->expenses as $expense) {
            if (! filled($expense->receipt_path)) {
                continue;
            }
            $path = ltrim(str_replace('\\', '/', (string) $expense->receipt_path), '/');
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }
            $as = $expense->receiptAttachmentBasename();
            // Third arg is the attachment display name; fourth is options (not a single options array as arg 3).
            $this->attachFromStorageDisk('public', $path, $as, ['mime' => $expense->receiptAttachmentMime()]);
        }

        return $this;
    }
}

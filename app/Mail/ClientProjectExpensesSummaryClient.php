<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
            $absolute = \App\Support\PrivateAppFile::absolutePath($path);
            if ($absolute === null) {
                continue;
            }
            $as = $expense->receiptAttachmentBasename();
            $this->attach($absolute, ['as' => $as, 'mime' => $expense->receiptAttachmentMime()]);
        }

        return $this;
    }
}

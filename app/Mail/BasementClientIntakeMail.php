<?php

namespace App\Mail;

use App\Models\BasementQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BasementClientIntakeMail extends Mailable
{
    use Queueable, SerializesModels;

    public BasementQuotation $quotation;
    public string $formUrl;
    public string $clientName;

    public function __construct(BasementQuotation $quotation, string $formUrl, string $clientName)
    {
        $this->quotation = $quotation;
        $this->formUrl = $formUrl;
        $this->clientName = $clientName;
    }

    public function build(): self
    {
        $label = $this->quotation->displayTitle();

        return $this->subject('A few questions about your '.$label)
            ->view('emails.client_projects.client_basement_intake', [
                'quotation' => $this->quotation,
                'formUrl' => $this->formUrl,
                'clientName' => $this->clientName,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Support\BusinessContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation sent to the client after they accept a quote from the
 * signed link used by the quotation email and PDF.
 */
class ClientProjectQuoteAcceptedClient extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientProjectQuotation $quotation,
        public ClientProject $project,
    ) {
    }

    public function subjectLine(): string
    {
        return 'Thank you for choosing Zanini Renovations, quote '.$this->quotation->displayQuoteNumber();
    }

    /**
     * First word of the client name. Blank or punctuation-only names use "there".
     */
    public function clientFirstName(): string
    {
        $name = trim((string) $this->project->client_name);
        if ($name === '') {
            return 'there';
        }

        $first = preg_split('/\s+/u', $name, 2)[0] ?? '';
        $first = trim((string) $first, " \t\n\r\0\x0B.,");

        return $first !== '' ? $first : 'there';
    }

    public function businessEmail(): string
    {
        return BusinessContact::email();
    }

    public function businessPhone(): string
    {
        return trim(BusinessContact::phoneDisplay());
    }

    /**
     * Question sentence. The call clause is left out when no phone is configured.
     */
    public static function questionSentence(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return 'If you have any questions in the meantime, simply reply to this email.';
        }

        return 'If you have any questions in the meantime, simply reply to this email or call us at '.$phone.'.';
    }

    public function questionLine(): string
    {
        return self::questionSentence($this->businessPhone());
    }

    public function build(): self
    {
        $message = $this->subject($this->subjectLine());

        $replyTo = $this->businessEmail();
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $message->replyTo($replyTo, BusinessContact::name());
        }

        return $message->view('emails.client_projects.client_quote_accepted', [
            'quotation' => $this->quotation,
            'project' => $this->project,
            'firstName' => $this->clientFirstName(),
            'quoteNumber' => $this->quotation->displayQuoteNumber(),
            'projectName' => (string) $this->project->name,
            'questionLine' => $this->questionLine(),
            'businessEmail' => $this->businessEmail(),
        ]);
    }
}

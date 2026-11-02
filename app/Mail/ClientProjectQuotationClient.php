<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use App\Models\ClientProjectQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the client with the freshly generated quotation/estimate
 * PDF attached, plus the project images selected for that PDF.
 *
 * Subject includes project number (PRJ…) and quote number (SQ… / QUO…) so
 * replies can be matched.
 */
class ClientProjectQuotationClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;
    public ClientProjectQuotation $quotation;
    public string $pdfBytes;
    public string $pdfFilename;
    public string $acceptUrl;
    public ?string $customSubject = null;
    public ?string $customMessage = null;

    public int $photoAttachmentCount = 0;

    public function __construct(
        ClientProject $project,
        ClientProjectQuotation $quotation,
        string $pdfBytes,
        string $pdfFilename = 'quotation.pdf',
        ?string $acceptUrl = null,
        ?string $customSubject = null,
        ?string $customMessage = null,
    ) {
        $this->project = $project;
        $this->quotation = $quotation;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
        $this->acceptUrl = $acceptUrl ?? $quotation->signedAcceptUrl();
        $this->customSubject = $customSubject;
        $this->customMessage = $customMessage;
    }

    public static function subjectFor(ClientProject $project, ClientProjectQuotation $quotation): string
    {
        return sprintf(
            '%s %s · %s – Zanini Renovations',
            $quotation->typeLabel(),
            $quotation->displayQuoteNumber(),
            $project->referenceCode()
        );
    }

    public function build(): self
    {
        $photos = $this->attachablePdfPhotos();
        $this->photoAttachmentCount = count($photos);

        $mailSubject = filled($this->customSubject)
            ? $this->customSubject
            : self::subjectFor($this->project, $this->quotation);

        $message = $this->subject($mailSubject)
            ->view('emails.client_projects.client_quotation', [
                'project' => $this->project,
                'quotation' => $this->quotation,
                'acceptUrl' => $this->acceptUrl,
                'customMessage' => $this->customMessage,
                'photoAttachmentCount' => $this->photoAttachmentCount,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);

        $usedNames = [];
        foreach ($photos as [$photo, $absolute]) {
            $message->attach($absolute, [
                'as' => $this->attachmentName($photo, $usedNames),
                'mime' => $this->imageMime($absolute),
            ]);
        }

        return $message;
    }

    /**
     * Selected PDF images that still exist on disk, in the saved display order.
     *
     * @return list<array{0: ClientProjectPhoto, 1: string}>
     */
    private function attachablePdfPhotos(): array
    {
        $rows = [];
        foreach ($this->quotation->pdfPhotosForRendering($this->project) as $photo) {
            $absolute = $photo->absolutePathForPdf();
            if ($absolute === null) {
                continue;
            }
            $rows[] = [$photo, $absolute];
        }

        return $rows;
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    private function attachmentName(ClientProjectPhoto $photo, array &$usedNames): string
    {
        $name = basename(str_replace('\\', '/', $photo->displayName()));
        $name = preg_replace('/[^\w.\- ()]+/u', '-', (string) $name) ?? '';
        $name = trim($name, ". \t");
        if ($name === '') {
            $name = 'photo-'.$photo->id.'.webp';
        }

        $key = strtolower($name);
        if (isset($usedNames[$key])) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $name = $base.'-'.$photo->id.($ext !== '' ? '.'.$ext : '');
            $key = strtolower($name);
        }
        $usedNames[$key] = true;

        return $name;
    }

    private function imageMime(string $absolute): string
    {
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }
}

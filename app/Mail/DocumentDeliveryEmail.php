<?php

namespace App\Mail;

use App\Models\ClientAppliedDocument;
use App\Models\Comment;
use App\Support\MailEngineTrait;
use App\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentDeliveryEmail extends Mailable
{
    use SerializesModels, MailEngineTrait;

    protected ClientAppliedDocument $document;
    protected User $sender;
    protected string $function;
    protected $comment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ClientAppliedDocument$document, User $sender, string $function, $comment = null)
    {
        $this->document = $document;
        $this->sender = $sender;
        $this->function = $function;
        $this->comment = $comment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return call_user_func(array($this, $this->function));
    }

    private function createdByInternal()
    {
        $appUrl = config('app.customer_url');
        $documentId = $this->document->id;
        if ($documentId) {
            $document_link = url("$appUrl/quan-ly-nop-ho-so/$documentId/chi-tiet");
        } else {
            $document_link = url("$appUrl/quan-ly-nop-ho-so");
        }

        $headline = "VPO đã gửi đến bạn một hồ sơ mới";
        $predefinedConfig = [
            "headline" => $headline,
            'document_link' => $document_link,
            'comment' => $this->document->detail ?? "",
        ];

        $subject = $this->document->description;
        $mail = $this->subject($subject);

        return $mail->markdown('emails.appliedDocumentCreated', $predefinedConfig);
    }

    private function createdByCustomer()
    {
        $headline = "Người gửi: {$this->document->client->code} _ {$this->sender->name}";
        $document_link = "Hồ sơ: {$this->document->id} _ {$this->document->description}";
        $predefinedConfig = [
            "headline" => $headline,
            'document_link' => $document_link,
            'comment' => $this->document->detail ?? "",
        ];

        $subject = "Hồ sơ mới đã được gửi từ khách hàng {$this->document->client->code}";
        $mail = $this->subject($subject);

        return $mail->markdown('emails.appliedDocumentCreated', $predefinedConfig);
    }

    private function commentByInternal()
    {
        $predefinedConfig = [
            "status" => $this->document->status,
            'comment' => $this->comment instanceof Comment ? $this->comment->message : "",
        ];

        $subject = "[{$this->document->client->code}_{$this->document->client->company_name}] Hồ sơ {$this->document->id} đã được cập nhật";
        $mail = $this->subject($subject);

        return $mail->markdown('emails.appliedDocumentUpdated', $predefinedConfig);
    }

    private function commentByCustomer()
    {
        $predefinedConfig = [
            "status" => $this->document->status,
            'comment' => $this->comment instanceof Comment ? $this->comment->message : "",
        ];

        $subject = "Hồ sơ {$this->document->id} đã được cập nhật";
        $mail = $this->subject($subject);

        return $mail->markdown('emails.appliedDocumentUpdated', $predefinedConfig);
    }
}

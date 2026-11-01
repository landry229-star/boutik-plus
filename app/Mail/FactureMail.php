<?php

namespace App\Mail;

use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FactureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Facture $facture) {}

    public function build()
    {
        return $this
            ->subject('Votre facture '.$this->facture->numero)
            ->view('emails.facture');
    }
}

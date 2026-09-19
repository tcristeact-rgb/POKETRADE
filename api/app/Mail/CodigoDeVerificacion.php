<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// El correo con el código de 6 dígitos. A propósito NO implementa ShouldQueue:
// en Render no hay worker y un correo encolado no saldría nunca. El idioma lo
// fija quien envía con ->locale(); asunto y textos salen de lang/*/correo.php.
class CodigoDeVerificacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $codigo)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('correo.verificacion.asunto', ['codigo' => $this->codigo]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'correo.codigo-verificacion',
            text: 'correo.codigo-verificacion-texto',
            with: ['minutos' => \App\Services\VerificacionDeCorreo::MINUTOS_DE_VALIDEZ],
        );
    }
}

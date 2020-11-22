<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRecordErrorsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    { 
        $file = fopen('errors.csv', 'w');
        fputcsv($file, array_keys($this->data));
        fputcsv($file, array_values($this->data));
        return $this->subject("Accubits laravel test Record Errors (Vidya)")->view('email.error')->attach('errors.csv');
    }
}

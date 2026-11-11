<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupArchiveMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $zipFilePath,
        public string $zipFileName,
        public int $year,
        public string $operatorName,
        public int $txCount
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Cadangan Data KeuDPRD Banyumas TA {$this->year} - ".date('d M Y H:i'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $body = "
            <div style='font-family: sans-serif; color: #1e293b; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #04331a; margin-top: 0;'>Arsip Cadangan Data Keuangan DPRD Banyumas</h2>
                <p>Halo, berikut terlampir berkas arsip cadangan (ZIP) lengkap untuk <b>Tahun Anggaran {$this->year}</b>.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li><b>Nama Berkas:</b> {$this->zipFileName}</li>
                        <li><b>Total Transaksi:</b> ".number_format($this->txCount, 0, ',', '.').' mutasi</li>
                        <li><b>Waktu Pembuatan:</b> '.date('d F Y, H:i:s')."</li>
                        <li><b>Operator:</b> {$this->operatorName}</li>
                    </ul>
                </div>
                <p>Berkas ZIP terlampir memuat salinan asli Excel <b>BKU Per PPTK (20 Sheet)</b> dan <b>Buku Bank (10 Bulan)</b> dengan seluruh rumus dan keterkaitan sel yang utuh, beserta berkas dump database SQL dan JSON.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;' />
                <p style='font-size: 12px; color: #64748b;'>Sekretariat DPRD Kabupaten Banyumas &bull; KeuDPRD TA {$this->year}</p>
            </div>
        ";

        return new Content(
            htmlString: $body,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->zipFilePath)
                ->as($this->zipFileName)
                ->withMime('application/zip'),
        ];
    }
}

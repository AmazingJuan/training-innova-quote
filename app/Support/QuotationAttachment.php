<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The documents a client attaches to a quotation request. They are not kept:
 * each file only waits on a private disk until the notification to management
 * carries it as an attachment, and is deleted as soon as that e-mail is sent
 * (or definitely fails). See App\Jobs\NotifyManagementOfQuotation.
 *
 * Nothing else reads these files, so anything left behind (a worker that never
 * ran, a lost job) is swept by `adjuntos:clean-cotizaciones`.
 *
 * @phpstan-type KeptFile array{path: string, name: string, mime: string|null}
 */
final class QuotationAttachment
{
    /** Files per request, size of each one (KB) and accepted extensions. Mirrored in quotation-wizard/documents.ts. */
    public const MAX_FILES = 5;

    public const MAX_KILOBYTES = 10240;

    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];

    /** Private disk (not web-reachable) and folder where a file waits for its e-mail. */
    public const DISK = 'local';

    public const DIRECTORY = 'adjuntos-cotizacion';

    /**
     * How long a file may wait before the sweeper removes it. Well above any
     * reasonable queue delay or retry, so it never deletes a pending attachment.
     */
    public const RETENTION_HOURS = 24;

    /**
     * Put the uploaded files where the notification can read them.
     *
     * @param  list<array{question?: string, file: UploadedFile}>  $documents
     * @return list<KeptFile>
     */
    public static function keep(array $documents): array
    {
        $kept = [];

        foreach ($documents as $document) {
            $file = $document['file'];
            // Read before storing: moving the upload leaves nothing to inspect.
            $name = $file->getClientOriginalName();
            $mime = $file->getMimeType();

            $kept[] = [
                'path' => $file->store(self::DIRECTORY, self::DISK),
                'name' => $name,
                'mime' => $mime,
            ];
        }

        return $kept;
    }

    /**
     * Delete the files of a request. Safe to call twice: a missing file is not an error.
     *
     * @param  list<KeptFile>  $files
     */
    public static function discard(array $files): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ($files as $file) {
            if (($file['path'] ?? '') !== '') {
                $disk->delete($file['path']);
            }
        }
    }

    /** Files still waiting that are older than the retention, with the number of them. */
    public static function sweep(): int
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::DIRECTORY)) {
            return 0;
        }

        $cutoff = now()->subHours(self::RETENTION_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if ($disk->lastModified($path) >= $cutoff) {
                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        return $deleted;
    }
}

<?php

namespace App\Services;

use App\Models\PmTicketAttachment;
use App\Models\PmTicketComment;
use App\Models\PmWorkItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PmsAttachmentService
{
    private const IMAGE_MIMES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'],
    ];

    private const DOCUMENT_MIMES = [
        'pdf' => ['application/pdf'], 'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
    ];

    public function validateFiles(array $files, string $category = 'evidence'): void
    {
        if (! in_array($category, ['evidence', 'test_case'], true)) {
            throw new \InvalidArgumentException('Unknown attachment category.');
        }
        if (count($files) > (int) config('pms.attachments.max_files_per_request', 10)) {
            throw ValidationException::withMessages(['attachments' => 'Upload no more than 10 files at once.']);
        }
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw ValidationException::withMessages(['attachments' => 'An uploaded file was incomplete or invalid.']);
            }
            $extension = strtolower($file->getClientOriginalExtension());
            if ($category === 'test_case' && ! in_array($extension, config('pms.test_cases.allowed_extensions'), true)) {
                throw ValidationException::withMessages(['test_cases' => 'Choose a supported test case document or image.']);
            }
            $allowed = self::IMAGE_MIMES[$extension] ?? self::DOCUMENT_MIMES[$extension] ?? null;
            $mime = $file->getMimeType();
            if (! $allowed || ! in_array($mime, $allowed, true)) {
                throw ValidationException::withMessages(['attachments' => "{$file->getClientOriginalName()} is not an approved file type."]);
            }
            $limit = isset(self::IMAGE_MIMES[$extension]) ? config('pms.attachments.image_max_kb', 10240)
                : ($extension === 'pdf' ? config('pms.attachments.pdf_max_kb', 25600) : config('pms.attachments.other_max_kb', 51200));
            if ($file->getSize() > (int) $limit * 1024) {
                throw ValidationException::withMessages(['attachments' => "{$file->getClientOriginalName()} exceeds the allowed size."]);
            }
            if ($category === 'test_case' && $file->getSize() > (int) config('pms.test_cases.max_file_kb', 20480) * 1024) {
                throw ValidationException::withMessages(['test_cases' => 'A test case file exceeds the configured size limit.']);
            }
            $path = $file->getRealPath();
            if (isset(self::IMAGE_MIMES[$extension]) && ! @getimagesize($path)) {
                throw ValidationException::withMessages(['attachments' => 'The image content is invalid.']);
            }
            if ($extension === 'pdf' && file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                throw ValidationException::withMessages(['attachments' => 'The PDF content is invalid.']);
            }
            if (in_array($extension, ['zip', 'docx', 'xlsx', 'pptx'], true)) {
                $zip = new ZipArchive();
                if ($zip->open($path) !== true) {
                    throw ValidationException::withMessages(['attachments' => 'The archive content is invalid.']);
                }
                $component = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension] ?? null;
                $valid = ! $component || ($zip->locateName('[Content_Types].xml') !== false && $zip->locateName($component) !== false);
                $zip->close();
                if (! $valid) {
                    throw ValidationException::withMessages(['attachments' => 'The Office document content is invalid.']);
                }
            }
        }
    }

    public function storeMany(PmWorkItem $issue, User $actor, array $files, ?PmTicketComment $comment = null, ?array &$storedPaths = null, string $category = 'evidence', ?int $replacesId = null): array
    {
        $this->validateFiles($files, $category);
        $stored = [];
        try {
            foreach ($files as $file) {
                $path = $file->store('pms/tickets/'.$issue->id, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['attachments' => 'A file could not be saved.']);
                }
                $stored[] = $path;
                if ($storedPaths !== null) {
                    $storedPaths[] = $path;
                }
                $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255);
                $version = $category === 'test_case'
                    ? max((int) PmTicketAttachment::where('issue_id', $issue->id)->where('category', 'test_case')
                        ->where('original_name', $name)->max('version'),
                        $replacesId ? (int) PmTicketAttachment::whereKey($replacesId)->value('version') : 0) + 1 : 1;
                PmTicketAttachment::create([
                    'issue_id' => $issue->id, 'comment_id' => $comment?->id, 'uploaded_by' => $actor->id,
                    'original_name' => $name, 'category' => $category, 'version' => $version,
                    'replaces_attachment_id' => $replacesId,
                    'storage_path' => $path, 'extension' => strtolower($file->getClientOriginalExtension()),
                    'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored);
            $storedPaths = [];
            throw $exception;
        }

        return $stored;
    }
}

<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\TradingJournal;
use App\Models\TradingJournalAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JournalAttachmentController extends MobileController
{
    public function store(Request $request, TradingJournal $journal): JsonResponse
    {
        $this->ensureOwner($request, $journal);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $file = $data['attachment'];
        $path = $file->storePublicly('trading-journals/'.$request->user()->id, 'public');
        $attachment = TradingJournalAttachment::create([
            'trading_journal_id' => $journal->id,
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return $this->success($attachment, 'Journal attachment uploaded.', 201);
    }

    public function destroy(Request $request, TradingJournal $journal, TradingJournalAttachment $attachment): JsonResponse
    {
        $this->ensureOwner($request, $journal);
        abort_unless((int) $attachment->trading_journal_id === (int) $journal->id, 404);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return $this->success(null, 'Journal attachment deleted.');
    }

    private function ensureOwner(Request $request, TradingJournal $journal): void
    {
        abort_unless((int) $journal->user_id === (int) $request->user()->id, 404);
    }
}

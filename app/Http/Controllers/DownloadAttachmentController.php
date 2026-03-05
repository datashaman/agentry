<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Bug;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttachmentController extends Controller
{
    /**
     * Stream the attachment file for download.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeDownload($request, $attachment);

        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download(
            $attachment->path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }

    private function authorizeDownload(Request $request, Attachment $attachment): void
    {
        if (! $request->user()) {
            abort(403);
        }

        $workItem = $attachment->workItem;
        if (! $workItem) {
            abort(404);
        }

        $project = $workItem instanceof Story
            ? $workItem->epic?->project
            : ($workItem instanceof Bug ? $workItem->project : null);
        if (! $project) {
            abort(404);
        }

        $org = $request->user()->currentOrganization();
        if (! $org || $project->organization_id !== $org->id) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillMdExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkillExportController extends Controller
{
    public function __invoke(Request $request, Skill $skill, SkillMdExporter $exporter): StreamedResponse
    {
        $org = $request->user()->currentOrganization();

        if (! $org || $skill->organization_id !== $org->id) {
            abort(403);
        }

        $content = $exporter->export($skill);
        $filename = 'SKILL.md';

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }
}

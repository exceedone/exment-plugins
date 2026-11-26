<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The attachments of an issue edited where they are, with their versions (ISS02938):
 *
 *   GET  {id}/issue/{issue_id}/file/{doc}/versions           the versions, newest first
 *   POST {id}/issue/{issue_id}/file/{doc}/restore/{version}  this version becomes the newest
 *   GET  {id}/issue/{issue_id}/file/{doc}/text               the text, its encoding, the lock
 *   POST {id}/issue/{issue_id}/file/{doc}/text               save the edited text (a new version)
 *   POST {id}/issue/{issue_id}/file/{doc}/lock               take / renew / let go (release=1)
 *
 * All 404 while the feature is off; reading needs the issue readable, changing it editable.
 */
trait PortalAttachTrait
{
    public function attachVersions(Request $request, $id, $issue_id, $doc)
    {
        [$project, $issue, $document] = $this->attachTarget($id, $issue_id, $doc);
        $out = [];
        $current = PortalAttachEdit::numbers([$document->id])[intval($document->id)] ?? 1;
        $out[] = [
            'no' => $current, 'current' => true,
            'name' => strval(array_get($document->value, 'document_name', '')),
            'by' => intval($document->updated_user_id ?: $document->created_user_id),
            'at' => $this->fmtAt($document->updated_at ?? $document->created_at),
            'url' => admin_url('files', [strval(array_get($document->value, 'file_uuid', ''))]),
        ];
        foreach (call_user_func([PortalAttachEdit::VERSIONS, 'versions'], intval($document->id)) as $v) {
            $out[] = [
                'id' => intval($v->id), 'no' => intval($v->version_no), 'current' => false,
                'name' => strval($v->document_name),
                'by' => intval($v->created_user_id),
                'at' => $this->fmtAt($v->created_at),
                'url' => admin_url('files', [strval($v->file_uuid)]),
            ];
        }

        return response()->json([
            'ok' => true, 'versions' => $out,
            'edit' => !$this->projectIsArchived($project) && PortalAttachEdit::mayEdit($issue, $document),
            'users' => $this->usersFor(array_column($out, 'by')),
        ]);
    }

    public function attachRestore(Request $request, $id, $issue_id, $doc, $version)
    {
        [$project, $issue, $document] = $this->attachTarget($id, $issue_id, $doc, true);
        $v = \Exceedone\Exment\Model\DocumentVersion::where('document_id', $document->id)->where('id', intval($version))->first();
        if (!isset($v)) {
            return $this->messageError(404, 'not_found');
        }
        $file = \Exceedone\Exment\Model\File::getData(strval($v->file_uuid));
        $bytes = isset($file) ? \Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'))->get($file->path) : null;
        if ($bytes === null) {
            return $this->messageError(404, 'not_found');
        }
        try {
            PortalAttachEdit::replaceWith($issue, $document, strval($bytes), strval($v->document_name));
        } catch (\Throwable $e) {
            Log::error('pm_portal: an attachment could not go back to a version: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'attach_failed');
        }
        PortalAttachEdit::note($issue, strval(PortalDefinition::trans('attach.note_restore', ['name' => strval($v->document_name), 'no' => intval($v->version_no)])));

        return $this->issueDetailResponse($project, $issue->id);
    }

    public function attachText(Request $request, $id, $issue_id, $doc)
    {
        [$project, $issue, $document] = $this->attachTarget($id, $issue_id, $doc);
        $name = strval(array_get($document->value, 'document_name', ''));
        if (PortalAttachEdit::kindOf($name) !== 'text') {
            return $this->messageError(422, 'attach_not_text');
        }
        $read = PortalAttachEdit::readText(PortalAttachEdit::fileOf($document));
        if (is_string($read)) {
            return $this->messageError(422, 'attach_' . $read);
        }
        $edit = !$this->projectIsArchived($project) && PortalAttachEdit::mayEdit($issue, $document);

        return response()->json([
            'ok' => true, 'name' => $name, 'text' => $read['text'], 'encoding' => $read['encoding'], 'hash' => $read['hash'],
            'edit' => $edit, 'lock' => PortalAttachEdit::holder(intval($document->id)),
            'no' => PortalAttachEdit::numbers([$document->id])[intval($document->id)] ?? 1,
        ]);
    }

    public function attachTextSave(Request $request, $id, $issue_id, $doc)
    {
        [$project, $issue, $document] = $this->attachTarget($id, $issue_id, $doc, true);
        $name = strval(array_get($document->value, 'document_name', ''));
        if (PortalAttachEdit::kindOf($name) !== 'text') {
            return $this->messageError(422, 'attach_not_text');
        }
        $me = intval(\Exment::getUserId());
        $lock = PortalAttachEdit::holder(intval($document->id));
        if (isset($lock) && $lock['by'] !== $me) {
            return $this->messageError(409, 'attach_locked', ['name' => $lock['name']]);
        }
        $read = PortalAttachEdit::readText(PortalAttachEdit::fileOf($document));
        if (is_string($read)) {
            return $this->messageError(422, 'attach_' . $read);
        }
        // someone saved it in between
        if (strval($request->input('hash')) !== $read['hash']) {
            return $this->messageError(409, 'attach_stale');
        }
        $text = strval($request->input('text', ''));
        $bytes = PortalAttachEdit::encodeText($text, $read['encoding']);
        if (strlen($bytes) > PortalAttachEdit::MAX_TEXT_BYTES) {
            return $this->messageError(422, 'attach_too_big');
        }
        if ($bytes === strval(\Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'))->get(PortalAttachEdit::fileOf($document)->path))) {
            PortalAttachEdit::unlock(intval($document->id), $me);

            return $this->issueDetailResponse($project, $issue->id);
        }
        try {
            PortalAttachEdit::replaceWith($issue, $document, $bytes, $name);
        } catch (\Throwable $e) {
            Log::error('pm_portal: an edited text attachment could not be saved: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'attach_failed');
        }
        PortalAttachEdit::unlock(intval($document->id), $me);
        $no = PortalAttachEdit::numbers([$document->id])[intval($document->id)] ?? 1;
        PortalAttachEdit::note($issue, strval(PortalDefinition::trans('attach.note_edit', ['name' => $name, 'no' => $no])));

        return $this->issueDetailResponse($project, $issue->id);
    }

    public function attachLock(Request $request, $id, $issue_id, $doc)
    {
        [$project, $issue, $document] = $this->attachTarget($id, $issue_id, $doc, true);
        $me = intval(\Exment::getUserId());
        if ($request->boolean('release')) {
            PortalAttachEdit::unlock(intval($document->id), $me);

            return response()->json(['ok' => true, 'mine' => false]);
        }
        $user = \Exment::user();
        $name = $user && $user->base_user ? strval($user->base_user->getLabel()) : '';

        return response()->json(['ok' => true] + PortalAttachEdit::lock(intval($document->id), $me, $name));
    }

    /**
     * The project, the issue and its attachment, while the feature is on; 404 otherwise, 403 when
     * changing it is asked and the user may not.
     *
     * @return array{0: mixed, 1: mixed, 2: mixed}
     */
    protected function attachTarget($id, $issue_id, $doc, bool $change = false): array
    {
        if (!PortalAttachEdit::enabled()) {
            abort(404);
        }
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        $document = PortalAttachEdit::document($issue, $doc);
        if (!isset($document)) {
            abort(404);
        }
        if ($change) {
            if ($this->projectIsArchived($project)) {
                abort(response()->json(['ok' => false, 'error' => 'archived', 'message' => strval(PortalDefinition::trans('message.archived'))], 403));
            }
            if (!PortalAttachEdit::mayEdit($issue, $document)) {
                abort(response()->json(['ok' => false, 'error' => 'no_permission', 'message' => strval(PortalDefinition::trans('message.no_permission'))], 403));
            }
        }

        return [$project, $issue, $document];
    }
}

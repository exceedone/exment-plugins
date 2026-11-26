<?php

namespace App\Plugins\ExmentGroupware\Chat;

use App\Plugins\ExmentGroupware\Mail\Accounts;
use Illuminate\Http\Request;

/**
 * The chat's page and its API (ISS02945): plugins/exment_groupware/chat and chat/api/*.
 */
class ChatPage
{
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    protected function uid(): int
    {
        $id = Accounts::currentUserId();
        if ($id === null) {
            abort(403);
        }

        return $id;
    }

    protected function url(string $path = ''): string
    {
        return $this->plugin->getFullUrl(trim('chat/' . $path, '/'));
    }

    protected function json($fn)
    {
        try {
            $data = $fn();

            return response()->json(array_merge(['ok' => true], is_array($data) ? $data : []));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('[ExmentGroupware] chat: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['ok' => false, 'message' => 'エラーが起きました。しばらくしてからやり直してください。'], 500);
        }
    }

    public function page()
    {
        $uid = $this->uid();
        $view = view('exment_exment_groupware::chat', [
            'boot' => [
                'me' => $uid,
                'users' => (object) ChatService::users(),
                'rooms' => ChatService::rooms($uid),
                'open' => intval(request()->query('room', 0)) ?: null,
                'top' => ChatService::topId($uid),
                'url' => [
                    'rooms' => $this->url('api/rooms'),
                    'room' => $this->url('api/room/__ID__'),
                    'messages' => $this->url('api/room/__ID__/messages'),
                    'post' => $this->url('api/room/__ID__/post'),
                    'read' => $this->url('api/room/__ID__/read'),
                    'members' => $this->url('api/room/__ID__/members'),
                    'direct' => $this->url('api/direct/__ID__'),
                    'poll' => $this->url('api/poll'),
                    'file' => $this->url('file/__ID__'),
                ],
                'max_file' => ChatService::MAX_FILE_BYTES,
            ],
        ]);
        foreach (['ExmentAdminCore\Admin\Layout\Content', 'Encore\Admin\Layout\Content'] as $class) {
            if (class_exists($class)) {
                $content = new $class();
                $content->row($view->render());

                return response($content->header('チャット')->headericon('fa-comments'));
            }
        }

        return $view;
    }

    public function apiRooms()
    {
        return $this->json(function () {
            return ['rooms' => ChatService::rooms($this->uid())];
        });
    }

    public function postRoom(Request $request)
    {
        return $this->json(function () use ($request) {
            $id = ChatService::createGroup($this->uid(), strval($request->input('name')), (array) $request->input('members', []));

            return ['room' => $id, 'rooms' => ChatService::rooms($this->uid())];
        });
    }

    public function apiRoom($id)
    {
        return $this->json(function () use ($id) {
            $uid = $this->uid();
            $room = ChatService::room($uid, intval($id));
            $me = ChatService::member($uid, intval($id));

            return ['room' => [
                'id' => intval($room->id), 'kind' => $room->kind, 'name' => strval($room->name ?? ''),
                'owner' => $me->role === 'owner', 'members' => array_keys(ChatService::memberNames(intval($id))),
            ]];
        });
    }

    public function postDirect($id)
    {
        return $this->json(function () use ($id) {
            return ['room' => ChatService::direct($this->uid(), intval($id)), 'rooms' => ChatService::rooms($this->uid())];
        });
    }

    public function postMembers(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            ChatService::setMembers($this->uid(), intval($id), (array) $request->input('add', []), (array) $request->input('remove', []));

            return ['rooms' => ChatService::rooms($this->uid())];
        });
    }

    public function apiMessages(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $before = intval($request->query('before', 0)) ?: null;

            return ['messages' => ChatService::messages($this->uid(), intval($id), $before)];
        });
    }

    public function postMessage(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $uid = $this->uid();
            ChatService::room($uid, intval($id));
            $files = [];
            $uploads = $request->file('files', []);
            foreach (array_slice(is_array($uploads) ? $uploads : [$uploads], 0, ChatService::MAX_FILES) as $up) {
                if (!$up instanceof \Illuminate\Http\UploadedFile || !$up->isValid()) {
                    continue;
                }
                if ($up->getSize() > ChatService::MAX_FILE_BYTES) {
                    throw new \InvalidArgumentException('25 MB をこえるファイルは送れません。');
                }
                // kept on Exment's disk but not as an Exment file: Exment's /files/{uuid} gives a
                // file without a parent row to anyone signed in, so these are served by the chat only
                $name = ChatText::cleanName($up->getClientOriginalName()) ?: 'file';
                $key = bin2hex(random_bytes(16));
                $path = 'gw_chat/' . intval($id) . '/' . $key;
                \Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'))->putFileAs('gw_chat/' . intval($id), $up, $key);
                $files[] = ['uuid' => $key, 'name' => $name, 'size' => intval($up->getSize()), 'path' => $path];
            }

            return ['message' => ChatService::post($uid, intval($id), strval($request->input('body', '')), $files)];
        });
    }

    public function postRead(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            ChatService::markRead($this->uid(), intval($id), intval($request->input('upto')));

            return [];
        });
    }

    public function apiPoll(Request $request)
    {
        return $this->json(function () use ($request) {
            $open = intval($request->query('room', 0)) ?: null;

            return ChatService::poll($this->uid(), intval($request->query('after', 0)), $open);
        });
    }

    /** A file of the chat, for the members of its room only. */
    public function file($uuid)
    {
        $f = ChatService::fileFor($this->uid(), strval($uuid));
        $path = isset($f) ? strval($f['path'] ?? '') : '';
        // only a path of the chat's own folder (the message was written by the chat)
        if ($path === '' || !preg_match('#^gw_chat/\d+/[0-9a-f]{32}$#', $path)) {
            abort(404);
        }
        $disk = \Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'));
        if (!$disk->exists($path)) {
            abort(404);
        }
        $file = (object) ['path' => $path];
        $name = strval($f['name'] ?? 'file');
        $inline = (bool) preg_match('/\.(png|jpe?g|gif|webp|pdf)$/i', $name);

        return response($disk->get($file->path), 200, [
            'Content-Type' => $disk->mimeType($file->path) ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

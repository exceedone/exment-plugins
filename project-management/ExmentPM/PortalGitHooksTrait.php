<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Http\Request;

/**
 * The project settings' 「Git とつなぐ（Webhook）」 (ISS02939 item 5): the manager of a project
 * connects its repositories. GET {id}/git_hooks, POST {id}/git_hooks (connect),
 * POST {id}/git_hooks/{hook}/secret (a new secret), /update, /delete.
 */
trait PortalGitHooksTrait
{
    protected function gitHookProject($id)
    {
        if (!PortalGitHooks::enabled()) {
            abort(404);
        }
        $project = $this->findProject($id);
        if (!($project->enableEdit() === true && PortalMembership::canManage($project))) {
            abort(response()->json(['ok' => false, 'message' => strval(PortalDefinition::trans('message.no_permission'))], 403));
        }

        return $project;
    }

    protected function gitHookJson($fn)
    {
        try {
            return response()->json(array_merge(['ok' => true], $fn()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function gitHooks(Request $request, $id)
    {
        $project = $this->gitHookProject($id);

        return $this->gitHookJson(function () use ($project) {
            return ['hooks' => PortalGitHooks::list(intval($project->id))];
        });
    }

    public function postGitHook(Request $request, $id)
    {
        $project = $this->gitHookProject($id);

        return $this->gitHookJson(function () use ($request, $project) {
            return PortalGitHooks::create(intval($project->id), strval($request->input('provider')), strval($request->input('repo')),
                boolval($request->input('close_on_merge', false)), intval(\Exment::getUserId()));
        });
    }

    public function postGitHookSecret(Request $request, $id, $hook)
    {
        $project = $this->gitHookProject($id);

        return $this->gitHookJson(function () use ($project, $hook) {
            return PortalGitHooks::newSecret(intval($project->id), intval($hook));
        });
    }

    public function postGitHookUpdate(Request $request, $id, $hook)
    {
        $project = $this->gitHookProject($id);

        return $this->gitHookJson(function () use ($request, $project, $hook) {
            return PortalGitHooks::update(intval($project->id), intval($hook), boolval($request->input('close_on_merge', false)));
        });
    }

    public function postGitHookDelete(Request $request, $id, $hook)
    {
        $project = $this->gitHookProject($id);

        return $this->gitHookJson(function () use ($project, $hook) {
            return PortalGitHooks::remove(intval($project->id), intval($hook));
        });
    }
}

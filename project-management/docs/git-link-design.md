# ExmentPM: Git linked to the issues (ISS02939 item 5)

A project connects one or more repositories (GitHub, GitLab, Gitea). Their **webhooks** tell
ExmentPM of commits and pull / merge requests; those that name an issue (its key, like
`EXMENT_AI-439`, or its alias) show on the issue's timeline. A merged PR that says
「fixes KEY」 / 「closes KEY」 can move the issue to its project's done status (a project setting,
off at first). Step 1 calls no Git host: webhooks in only.

Off at first: a site feature `exment_pm.git_link` (Feature management) and, per project, the
existing 「Git」 switch of the features tab plus a 「Git とつなぐ」 section in the settings tab.

## Tables

### pm_portal_git_hooks (one per connected repository)

| column | | |
|---|---|---|
| id | bigint | |
| project_id | bigint | index |
| provider | string(8) | `github` / `gitlab` / `gitea` |
| repo | string(200) | `owner/name` as the host says it (checked on each delivery) |
| hook_key | char(32) | random, in the webhook address `/pm-git/hook/<hook_key>` (not a secret: it names the hook) |
| secret_enc | text | the webhook secret, **encrypted** (Exment's `EncryptedValue` with the data keys, else the app key) |
| secret_hash | char(64) | sha256 of the secret (GitLab compares the token as it is; the hash is enough there) |
| close_on_merge | bool, false | 「fixes / closes KEY」 on a merged PR moves the issue to done |
| created_by, timestamps, last_delivery_at, last_error | | |

GitHub and Gitea sign the body with the secret (HMAC-SHA256, `X-Hub-Signature-256` /
`X-Gitea-Signature`): checking that needs the secret itself, so it is kept encrypted, never
shown again after it is made (only 「作り直す」). GitLab sends the token in `X-Gitlab-Token`:
compared with the hash.

### pm_portal_git_events (what the timeline shows)

| column | | |
|---|---|---|
| id | bigint | |
| project_id, issue_id | bigint | index (issue_id, occurred_at) |
| hook_id | bigint | |
| kind | string(8) | `commit` / `pr` |
| ref | string(80) | the commit's sha, or the PR / MR number |
| title | string(300) | the commit's first line, or the PR's title |
| author | string(120) | as the host says it (no account lookups) |
| url | string(500) | https only, to the host of the hook's repository |
| branch | string(200) | |
| state | string(8) | commit: — / PR: `open` / `merged` / `closed` |
| occurred_at | datetime | |

Unique (hook_id, kind, ref, issue_id): a delivery sent again, or a PR edited, updates its row.

## The webhook

- `POST /pm-git/hook/<hook_key>`: no session, no CSRF, no login (a route of its own like the help
  desk's public ones). Unknown key 404; a bad signature or token 403; the event's repository
  not the hook's 403; more than 1 MB 413.
- Events read: GitHub `push`, `pull_request`; GitLab `Push Hook`, `Merge Request Hook`; Gitea
  `push`, `pull_request`. Others: 200 and ignored (`ping` too).
- Keys found in a commit message, a PR title / body / branch name: `<PROJECT KEY>-<number>` of
  this project (case-insensitive, whole words), the issue's own key of the key column (`ISS00001`: what the screen shows and 「ブランチ名をコピー」 copies) and the project's aliases. At most 20 issues per
  commit / PR, 200 commits per push. Issues of other projects are never touched.
- Text is kept as text (escaped where it is shown), links only https to the repository's host.
- Replays: the delivery's own id (`X-GitHub-Delivery`, `X-Gitea-Delivery`, GitLab's
  `X-Gitlab-Event-UUID`) is remembered for 24 hours; the same id again is answered 200 and not read.
- The path is one prefix, `/pm-git/hook/`, so a WAF in front of the site (AWS WAF on Exment SaaS)
  needs one exception for it: commit messages and links may look like what it refuses.
- Answered quickly: the work is small (a few inserts); a delivery that fails is logged on the hook
  (`last_error`, shown in the settings).

## 「fixes KEY」 on merge

With the hook's 「マージで処理済みにする」 on: a PR (MR) whose title or body says
`fixes|closes|resolves KEY` (英語の大小・日本語の「KEY を修正」 is not read) and that is merged moves
that issue to the first done status of its project (`is_done` of the status list), with a system
comment 「PR #12（owner/repo）のマージで処理済みにしました」. Never reopens; never touches an issue
already done.

## On the issue

- Timeline entries `tp: git`: an icon, 「コミット」 / 「プルリクエスト」, the title (a link), author,
  branch, state chip (open / merged / closed).
- 「ブランチ名をコピー」: `KEY-<the subject's ASCII words, lowercased, joined by ->` (only the key
  when the subject has none), at most 60 characters.

## Steps

1. Tables, the settings (connect a repository: provider, `owner/name`, the secret shown once and
   the webhook address; 「マージで処理済みにする」), the webhook (signature, events, keys), the
   timeline, the branch name button.
2. 「fixes KEY」 on merge.

Tests: plain PHP with fake deliveries (GitHub / GitLab / Gitea payloads of `*.invalid` hosts):
signatures right and wrong, key matching, other projects ignored, link checks, the slug.

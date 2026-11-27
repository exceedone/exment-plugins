# Groupware chat: design (ISS02945)

The chat of Exment Plugin Groupware, like Chatwork in what it does (not in how it looks):
rooms for a group or two people, messages with mentions and replies, who has read them, files,
reactions, "make it a task" / "make it an issue", search, unread counts and notices. Off at
first (Feature management, `exment_groupware.chat`).

The video calls (ISS02946) come after it and use its rooms and its delivery for their signals.

## Tables

Plain tables made by the plugin's migrations, like `gw_mail_accounts`. They are not Exment
custom tables: a chat is many small rows read in order, and Exment's custom value (JSON value,
revisions, global scopes) would be slow and heavy for that. Who may read a room is decided here
(membership), not by Exment's table permissions.

### gw_chat_rooms

| column | | |
|---|---|---|
| id | bigint | |
| kind | string(8) | `group` or `direct` (two people) |
| name | string(100) null | the room's name (a group); a direct room shows the other person |
| description | text null | |
| direct_key | string(64) null, unique | `"<smaller user id>:<larger user id>"`, so two people have one direct room |
| source_type | string(16) null | `organization` / `role_group`: a room made from them keeps its members in step |
| source_id | bigint null | |
| retention_days | int null | messages older than this are removed by the batch (null: kept) |
| created_by | bigint | Exment user id |
| archived_at | datetime null | read only, out of the list |
| last_message_id | bigint null | for the order of the list and a quick "anything new" |
| created_at, updated_at | | |

### gw_chat_members

| column | | |
|---|---|---|
| room_id, user_id | bigint | unique together; index on user_id |
| role | string(8) | `owner` (may change the room and its members) or `member` |
| muted | bool | no bell, mail or Teams from this room (the unread count still shows) |
| last_read_id | bigint, 0 | the newest message the person has seen |
| joined_at | datetime | messages before it are not shown to them (a room joined later) |

Read counts come from here: a message is read by the members (not its writer) whose
`last_read_id` reaches it. Unread counts: messages after `last_read_id` that are not one's own.

### gw_chat_messages

| column | | |
|---|---|---|
| id | bigint | index (room_id, id) |
| room_id, user_id | bigint | |
| body | mediumtext | Markdown, at most 20,000 characters |
| reply_to | bigint null | the message it answers (shown quoted) |
| mentions | json null | user ids, and `"all"` for @全員 |
| files | json null | `[{uuid, name, size}]`: Exment files, opened through the plugin (member check) |
| edited_at | datetime null | |
| deleted_at | datetime null | the body is emptied, the row stays ("deleted"), its history is kept |
| created_at | | |

### gw_chat_revisions

The history of an edit or a deletion: message_id, body (before), user_id, created_at. Seen by
the writer and an administrator.

### gw_chat_reactions (step 2)

message_id, user_id, emoji (string 16, from a fixed list); unique together.

### gw_chat_tasks (step 2)

message_id, assignee (user id), due (date null), done_at (null), created_by. ExmentPM present:
「課題にする」 makes an issue of a project instead, with the message's link (as the help desk
does: ExmentPM's tables read as Exment tables, no shared code).

## Delivery

- **Polling first**: `GET chat/api/poll?after=<id>` returns the new messages of the person's rooms
  after a message id, the changed read marks and the unread counts. Every 3 seconds while the
  page is seen, every 30 while hidden, nothing while offline, and none at all after 30 minutes
  of nothing done (until the person uses the page again), so a forgotten tab costs nothing. One indexed query on
  `gw_chat_messages (room_id, id)` for the person's rooms.
- **Later WebSocket** (Laravel Reverb and the like): the page talks to a small "transport" with
  two parts, `start(onEvent)` and `stop()`. Polling is one, a socket would be another; the
  server sends the same events. SaaS: polling works as it is (no new servers).

## Who may do what

- Only the members of a room read it, post in it, see its files. Every request checks the
  membership (no id guessing: a room or a file not one's own is 404).
- The owner names the room and changes its members; anyone leaves.
- An administrator sees the list of rooms (not their messages) and can archive one. Deleting
  a room and the retention purge are written to Exment's operation log.
- Guests (later: a customer from the help desk by a one-time, expiring link) have room in
  `gw_chat_members.guest_id` and `gw_chat_guests` (name, email, token hash, expiry); every post
  and call signal goes through the one membership check, with a limit per IP and per token.
- Mentions only of members; `@全員` only in a group room.

## Files

Pasted or dropped files are saved as Exment files (the core's File) and listed on the message.
They are opened through the plugin (`chat/file/{uuid}`), which checks the membership; pictures
inline, and Office files through the Groupware's Office (ibisOffice) like the file sharing.

## Notices (step 3)

The unread count on the menu and the bell; a mention (or a direct message) as a bell, and by
mail or Teams as the person chose in ExmentPM's notice settings when ExmentPM is there
(`pmportal.notify_by`, read as a user setting), never for a muted room.

## Steps (small PRs)

1. Tables, rooms (group / direct, from an organization or a role group), messages, mentions,
   unread and read counts, polling, files. The page: the list of rooms on the left, the
   messages on the right, the box to write at the bottom; a phone shows one at a time.
2. Replies, reactions, edit and delete with history, 「タスクにする」/「課題にする」, search
   (Meilisearch when the site has it).
3. Notices (bell, mail, Teams), muting, the retention batch.
4. WebSocket.

Tests: plain PHP for the parts with no database (mentions, direct keys, read counts, the
Markdown made safe), addresses `*.invalid` only.

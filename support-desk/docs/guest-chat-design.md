# Support Desk: chat and calls with customers (ISS02941)

Customers without an Exment login talk to the help desk in two ways:

1. **A chat window on a website**, one per 受付箱, pasted like the website forms.
2. **A call link** sent from a ticket (and asked for from the customer portal): voice and a
   shared screen, in the browser.

Both are off at first (Feature management: `exment_support_desk.web_chat`,
`exment_support_desk.guest_call`). Neither needs a new server: polling, as the Groupware chat.
The code lives in the help desk plugin (the plugins share no code; the call part follows the
Groupware's `ChatCall` in its own copy).

## The chat window

- **Embedding.** The 受付箱 page gives a script tag per box:
  `<script src="https://<site>/support/chat/<box key>.js" async></script>`. It puts a small
  button on the page that opens an iframe of `/support/chat/<box key>` (the window). The box
  key is random (not the id), kept on the box (`sd_category.chat_key`, schema 13).
- **Who.** The window asks for a name and an email (and the first message). That makes a
  **ticket** of the box, channel `chat` (new in `Its::CHANNELS`), with the email as the
  requester, and a **guest token** for the window (32 random bytes; only its sha256 is kept,
  in `sd_tokens`, kind `chat`, expiring after 24 hours without use). The token stays in the
  window's memory and `sessionStorage` of the iframe, never in a cookie: the iframe is on
  another site's page, where cookies may be blocked.
- **Messages.** The customer's lines go through `Tickets::customerWrote` (as a mail reply
  does: channel `chat`); the agents answer in the desk as usual (「お客様へ返信」), and those
  replies (not the notes, not the system lines) show in the window. The window polls
  `/support/chat/api/messages?after=` every 3 s while it is open (30 s hidden, none after 30
  minutes idle). The receipt mail goes to the email, with the usual ticket link
  (`/support/t/<token>`), so the talk can go on by mail.
- **Offline.** When no agent has been seen on the desk for 10 minutes (the desk's own
  heartbeat), the window says so before the first line: 「ただいま担当者がいません。メールで
  お返事します」; the ticket is made the same.
- **Agents.** The desk shows a chat ticket with a 「チャット」 chip and a ring of the bell on
  each new line (the desk's notices); a reply is sent at once to the window (and by mail only
  when the window has been closed for 10 minutes).
- **API.** `/support/chat/api/*`: no session and no CSRF (the window is on another site); the
  guest token in a header (`X-Chat-Token`) instead. Every call checks the token against its
  ticket.
- **Limits.** Starting a chat: 5 per IP per hour; lines: 30 per token per 10 minutes, 2,000
  characters each; no files in step 1 (links only). Laravel's RateLimiter, keyed by IP and by
  token hash.

## The call link

- **From a ticket.** 「通話のリンクを送る」 on the ticket makes a **call** (`sd_calls`: ticket,
  agent, token hash, expires_at = 30 minutes, joined_at, ended_at, notes) and sends the link
  `/support/call/<token>` to the requester by mail (and in the chat window when one is open).
- **One time.** The first browser that opens the link binds it (a second token kept only in
  that browser); another browser gets 「このリンクはもう使われています」. Expired links say so.
- **The call.** The guest page and the agent's window (from the ticket) connect with WebRTC,
  voice and a shared screen, signals through `/support/call/api/*` (token) and the desk's own
  API (login). The 「通話のサーバー（STUN / TURN）」 setting is the help desk's own, empty
  until snoro decides (as the Groupware's).
- **Afterwards.** The call's length goes to the ticket as a system line and into
  `time_spent`; the agent's notes (a box in the call window) as a note.
- **From the portal.** On `/support/t/<token>` and 「自分の依頼」, a customer may ask for a call
  (「通話をお願いする」): the agents see it on the ticket and send the link (no call starts by
  itself).

## Steps

1. Schema 13 (chat key, channel `chat`), the chat window (embed, start, lines, offline),
   the desk's side, limits.
2. The call link: `sd_calls`, the guest page, the agent's window, notes and time.
3. 「通話をお願いする」 from the portal.

Tests: plain PHP for the parts with no database (token, limits, the offline rule, the box
key); addresses `*.invalid` only.

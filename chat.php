<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}
// Generate a simple session token for the chat (per session)
if (!isset($_SESSION['chat_token'])) {
    $_SESSION['chat_token'] = bin2hex(random_bytes(16));
}
$username = $_SESSION['user'];
$token = $_SESSION['chat_token'];
$custom_css = null;
$blog_dir = __DIR__ . "/blog/" . $username;
if (is_dir($blog_dir) && file_exists($blog_dir . '/custom.css')) {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    if ($base === DIRECTORY_SEPARATOR) $base = '';
    $custom_css = $base . "/blog/" . $username . "/custom.css?v=" . filemtime($blog_dir . '/custom.css');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Chat</title>
    <link rel="stylesheet" type="text/css" href="css.css" />
    <?php if ($custom_css): ?>
      <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($custom_css) ?>" />
    <?php endif; ?>
</head>
<body>
<div id="header" style="width:100%;text-align:center;margin:20px 0 30px 0;">
  <a href="/" id="home-link" style="color:#4e5053;text-decoration:none;font-size:1em;">home</a>
</div>
<div id="chatbox">
    <div id="messages"></div>
    <div id="live-text"></div>
    <form id="chatform" autocomplete="off" onsubmit="return false;">
        <input id="input" type="text" maxlength="500" placeholder="Type a message..." autofocus autocomplete="off" />
        <button id="send" type="submit">Send</button>
    </form>
</div>
<div id="userlist">
    <h3>Users</h3>
    <ul id="userlist-ul" style="list-style:none;padding:0;margin:0;"></ul>
</div>
<script>
const username = <?= json_encode($username) ?>;
const token = <?= json_encode($token) ?>;
const ws = new WebSocket('ws://' + location.hostname + ':8765');
const messages = document.getElementById('messages');
const input = document.getElementById('input');
let liveTexts = {};
let users = [];

function escapeHTML(str) {
    return str.replace(/[&<>"']/g, function(tag) {
        const chars = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'};
        return chars[tag] || tag;
    });
}
// JS version of format_markdown and format_post
function formatMarkdown(text) {
    // Spoiler: ||text||
    text = text.replace(/\|\|(.+?)\|\|/gs, '<span class="spoiler">$1</span>');
    // Bold: **text** or __text__
    text = text.replace(/\*\*(.+?)\*\*/gs, '<b>$1</b>');
    text = text.replace(/__(.+?)__/gs, '<b>$1</b>');
    // Italic: *text* or _text_
    text = text.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/gs, '<i>$1</i>');
    text = text.replace(/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/gs, '<i>$1</i>');
    // Strikethrough: ~~text~~
    text = text.replace(/~~(.+?)~~/gs, '<s>$1</s>');
    // Heading: # text (at line start)
    text = text.replace(/^# (.+)$/gm, '<span>$1</span>');
    return text;
}
function formatPost(text) {
    text = escapeHTML(text);
    // [code], [url], [jis] blocks not implemented for chat for simplicity
    return formatMarkdown(text);
}
function pad2(n) { return n < 10 ? '0'+n : n; }
function formatTime(ts) {
    // Convert ms timestamp to JST regardless of user location
    const d = new Date(ts);
    // JST is UTC+9
    const jstHours = (d.getUTCHours() + 9) % 24;
    return pad2(jstHours) + ':' + pad2(d.getUTCMinutes()) + ':' + pad2(d.getUTCSeconds());
}
function isScrolledToBottom() {
    return messages.scrollHeight - messages.scrollTop - messages.clientHeight < 5;
}
function addMessage(user, text, isMe, ts) {
    // Remove any existing live message for this user
    const oldLive = document.getElementById('live-' + user);
    if (oldLive) oldLive.remove();
    const div = document.createElement('div');
    div.className = 'msg';
    let timeStr = ts ? `<span class='msg-ts'>[${formatTime(ts)}]</span> ` : '';
    div.innerHTML = timeStr + `<span class="${isMe ? 'me' : 'user'}">${user}</span>: ${formatPost(text)}`;
    const atBottom = isScrolledToBottom();
    messages.appendChild(div);
    if (atBottom) messages.scrollTop = messages.scrollHeight;
}
function updateLiveMessages() {
    // Remove all current live messages
    Array.from(messages.querySelectorAll('.live-msg')).forEach(e => e.remove());
    // Append all current live texts (including self) at the bottom
    const atBottom = isScrolledToBottom();
    for (const [user, text] of Object.entries(liveTexts)) {
        if (text) {
            const div = document.createElement('div');
            div.className = 'msg live-msg';
            div.id = 'live-' + user;
            div.innerHTML = `<span class='user'>${user}</span>: <span class='live'>${formatPost(text)}</span>`;
            messages.appendChild(div);
        }
    }
    if (atBottom) messages.scrollTop = messages.scrollHeight;
}
function updateUserList() {
    const ul = document.getElementById('userlist-ul');
    ul.innerHTML = '';
    users.forEach(u => {
        const li = document.createElement('li');
        li.textContent = u;
        ul.appendChild(li);
    });
}
ws.onopen = () => {
    ws.send(JSON.stringify({type: 'auth', username, token}));
};
ws.onmessage = (event) => {
    let data;
    try { data = JSON.parse(event.data); } catch { return; }
    if (data.type === 'auth_ok') {
        messages.innerHTML = '';
    } else if (data.type === 'message') {
        addMessage(data.username, data.text, data.username === username, data.ts);
        liveTexts[data.username] = '';
        updateLiveMessages();
    } else if (data.type === 'rtt') {
        liveTexts[data.username] = data.text;
        updateLiveMessages();
    } else if (data.type === 'userlist') {
        users = data.users;
        updateUserList();
    }
};
input.addEventListener('input', () => {
    liveTexts[username] = input.value;
    updateLiveMessages();
    ws.send(JSON.stringify({type: 'rtt', text: input.value}));
});
document.getElementById('chatform').addEventListener('submit', () => {
    const text = input.value.trim();
    if (text) {
        ws.send(JSON.stringify({type: 'message', text}));
        input.value = '';
        liveTexts[username] = '';
        updateLiveMessages();
    }
});
</script>
</body>
</html> 
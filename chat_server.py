import asyncio
import websockets
import json
import secrets
import re
import datetime
try:
    from zoneinfo import ZoneInfo
    tz_tokyo = ZoneInfo("Asia/Tokyo")
except ImportError:
    import pytz
    tz_tokyo = pytz.timezone("Asia/Tokyo")

# In-memory store for connected clients and their usernames
disconnected = object()
clients = {}  # websocket: {"username": str, "token": str}

# Simple token-based authentication (for demo; in production, use secure session)
# The client must send {"type": "auth", "username": ..., "token": ...} as the first message

message_cache = []  # List of dicts: {"type": "message", "username": ..., "text": ..., "ts": ...}
MAX_CACHE = 100

user_last_message = {}  # username: timestamp
RATE_LIMIT = 2  # seconds

def sanitize(text):
    # Remove HTML tags and scripts
    text = re.sub(r'<.*?>', '', text)
    return text

async def broadcast(message, exclude=None):
    """Send a message to all connected clients except 'exclude' websocket."""
    for ws in list(clients.keys()):
        if ws != exclude:
            try:
                await ws.send(message)
            except Exception:
                clients[ws] = disconnected

async def broadcast_userlist():
    userlist = sorted({info["username"] for ws, info in clients.items() if info is not disconnected})
    payload = json.dumps({"type": "userlist", "users": userlist})
    await broadcast(payload)

async def send_cache(websocket):
    for msg in message_cache:
        await websocket.send(json.dumps(msg))

# Change handler signature for websockets v11+
async def handler(websocket):
    # Authenticate first
    try:
        auth_msg = await asyncio.wait_for(websocket.recv(), timeout=5)
        auth = json.loads(auth_msg)
        if auth.get("type") != "auth" or not auth.get("username") or not auth.get("token"):
            await websocket.send(json.dumps({"type": "error", "message": "Auth required"}))
            return
        # Accept any non-empty username/token for demo
        username = sanitize(auth["username"])
        token = auth["token"]
        clients[websocket] = {"username": username, "token": token}
        await websocket.send(json.dumps({"type": "auth_ok", "username": username}))
        await broadcast_userlist()
        await send_cache(websocket)
    except Exception:
        await websocket.send(json.dumps({"type": "error", "message": "Auth failed"}))
        return

    try:
        async for msg in websocket:
            try:
                data = json.loads(msg)
            except Exception:
                continue
            if data.get("type") == "message":
                # Broadcast chat message
                now_utc = datetime.datetime.utcnow().replace(tzinfo=datetime.timezone.utc)
                try:
                    now_jst = now_utc.astimezone(tz_tokyo)
                except Exception:
                    now_jst = now_utc  # fallback
                ts = int(now_jst.timestamp() * 1000)
                uname = clients[websocket]["username"]
                last = user_last_message.get(uname, 0)
                if asyncio.get_event_loop().time() - last < RATE_LIMIT:
                    continue  # Ignore spam
                user_last_message[uname] = asyncio.get_event_loop().time()
                payload = {
                    "type": "message",
                    "username": uname,
                    "text": sanitize(data.get("text", "")),
                    "ts": ts
                }
                message_cache.append(payload)
                if len(message_cache) > MAX_CACHE:
                    message_cache.pop(0)
                await broadcast(json.dumps(payload))
            elif data.get("type") == "typing":
                # Broadcast typing event
                payload = json.dumps({
                    "type": "typing",
                    "username": clients[websocket]["username"]
                })
                await broadcast(payload, exclude=websocket)
            elif data.get("type") == "rtt":
                payload = json.dumps({
                    "type": "rtt",
                    "username": clients[websocket]["username"],
                    "text": sanitize(data.get("text", ""))
                })
                await broadcast(payload, exclude=websocket)
    finally:
        uname = clients[websocket]["username"] if websocket in clients else None
        clients.pop(websocket, None)
        await broadcast_userlist()
        if uname:
            # Remove live message for this user
            payload = json.dumps({"type": "rtt", "username": uname, "text": ""})
            await broadcast(payload)

async def main():
    async with websockets.serve(handler, "0.0.0.0", 80):
        print("Chat server running on ws://0.0.0.0:80")
        await asyncio.Future()  # run forever

if __name__ == "__main__":
    asyncio.run(main()) 
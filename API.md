# NexusChat - API Documentation

## Base URL
```
https://your-domain.com/api/
```

## Authentication
تمام endpoint‌ها نیاز به session فعال دارند (به‌جز `auth.php`).
ابتدا از `auth.php?action=login` لاگین کنید. session در cookie ذخیره می‌شود.

## Response Format
تمام پاسخ‌ها JSON هستند:
```json
{ "success": true, "data": ... }
{ "success": false, "message": "error message", "code": 400 }
```

---

## Auth API `/api/auth.php`

### Register
```http
POST /api/auth.php?action=register
Content-Type: application/x-www-form-urlencoded

username=mahan
display_name=ماهان
password=secret123
```

**Response:**
```json
{ "success": true, "user_id": 1, "redirect": "/index.php" }
```

**Errors:**
- `invalid_username` — نام کاربری باید ۳-۳۲ کاراکتر باشد
- `invalid_password` — رمز عبور حداقل ۶ کاراکتر
- `username_taken` — نام کاربری تکراری

### Login
```http
POST /api/auth.php?action=login
Content-Type: application/x-www-form-urlencoded

username=mahan
password=secret123
```

**Rate limit:** 10 درخواست در ساعت

### Logout
```http
GET /api/auth.php?action=logout
```

### Me
```http
GET /api/auth.php?action=me
```

---

## Chat API `/api/chats.php`

### List
```http
GET /api/chats.php?action=list
```

**Response:**
```json
{
  "success": true,
  "chats": [
    {
      "id": 1,
      "chat_name": "سارا محمدی",
      "other_avatar": "/uploads/avatars/2.webp",
      "other_online": true,
      "last_message": "سلام",
      "last_message_time": "2026-07-07 10:30:00",
      "unread_count": 3
    }
  ]
}
```

### Messages
```http
GET /api/chats.php?action=messages&chat_id=1&limit=50&before=123
```

### Send
```http
POST /api/chats.php?action=send&chat_id=1
Content-Type: application/x-www-form-urlencoded

content=سلام
type=text
reply_to=123
```

### Read
```http
POST /api/chats.php?action=read&chat_id=1
```

### React
```http
POST /api/chats.php?action=react
Content-Type: application/x-www-form-urlencoded

message_id=123
emoji=❤️
```

### Delete
```http
POST /api/chats.php?action=delete
Content-Type: application/x-www-form-urlencoded

message_id=123
```

### Typing
```http
POST /api/chats.php?action=typing
Content-Type: application/x-www-form-urlencoded

chat_id=1
```

---

## Wallet API `/api/wallet.php`

پشتیبانی از ۷ ارز: `IRR, USD, EUR, GBP, BTC, ETH, TON, USDT`

### List
```http
GET /api/wallet.php?action=list
```

### Balance
```http
GET /api/wallet.php?action=balance&wallet_id=1
```

### Topup
```http
POST /api/wallet.php?action=topup
Content-Type: application/x-www-form-urlencoded

wallet_id=1
amount=100000
```

### Transfer
```http
POST /api/wallet.php?action=transfer
Content-Type: application/x-www-form-urlencoded

currency=IRR
to=username_or_wallet
amount=50000
note=test
```

### Rate (Exchange Rate)
```http
GET /api/wallet.php?action=rate&from=USD&to=IRR&amount=100
```

**Response:**
```json
{ "success": true, "result": 42000000, "rate": 420000 }
```

### Exchange
```http
POST /api/wallet.php?action=exchange
Content-Type: application/x-www-form-urlencoded

from=USD
to=IRR
amount=100
```

### Bank Cards
```http
GET /api/wallet.php?action=cards
POST /api/wallet.php?action=add_card
    card_number=6037991234567890
    card_holder=MAHAN JAFARI
    bank_name=Melli
    expiry=12/28
```

### Crypto Send
```http
POST /api/wallet.php?action=crypto_send
Content-Type: application/x-www-form-urlencoded

currency=BTC
to_address=bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh
amount=0.01
```

### Escrow
```http
POST /api/wallet.php?action=escrow_create
    to_user=ali
    amount=5000000
    description=خرید لپتاپ
POST /api/wallet.php?action=escrow_release
    deal_id=1
```

### History
```http
GET /api/wallet.php?action=history&wallet_id=1
```

---

## Users API `/api/users.php`

### Search
```http
GET /api/users.php?action=search&q=mah
```

### Contacts
```http
GET /api/users.php?action=contacts
```

### Profile
```http
GET /api/users.php?action=profile&user_id=2
POST /api/users.php?action=update
    display_name=ماهان جعفری
    bio=توسعه‌دهنده
    avatar=/uploads/avatars/me.webp
```

### DND
```http
POST /api/users.php?action=set_dnd&minutes=60
```

### Theme
```http
POST /api/users.php?action=set_theme&theme=ocean
```

### Lookup
```http
GET /api/users.php?action=lookup&identifier=username
```

---

## Channels API `/api/channels.php`

### List
```http
GET /api/channels.php?action=list
```

### Create
```http
POST /api/channels.php?action=create
    username=tech_iran
    name=Tech Iran
    description=اخبار فناوری
    is_public=1
```

### Subscribe
```http
POST /api/channels.php?action=subscribe
    channel_id=1
```

### Posts
```http
GET /api/channels.php?action=posts&channel_id=1
POST /api/channels.php?action=post
    channel_id=1
    content=خبر جدید
```

---

## Bots API `/api/bots.php`

### List
```http
GET /api/bots.php?action=list
```

### Create
```http
POST /api/bots.php?action=create
    username=my_bot
    display_name=ربات من
    description=یه ربات تستی
```

### Command
```http
POST /api/bots.php?action=command
    bot_id=1
    command=/start
    args=hello
```

---

## Calls API `/api/calls.php`

### Start
```http
POST /api/calls.php?action=start
    chat_id=1
    type=video
```

### Accept / Reject / End
```http
POST /api/calls.php?action=accept&call_id=1
POST /api/calls.php?action=reject&call_id=1
POST /api/calls.php?action=end&call_id=1
```

### History
```http
GET /api/calls.php?action=history
```

---

## Stickers API `/api/stickers.php`

### Packs
```http
GET /api/stickers.php?action=packs
```

### Stickers in Pack
```http
GET /api/stickers.php?action=list&pack_id=1
```

---

## Polls API `/api/polls.php`

### Create
```http
POST /api/polls.php?action=create
    chat_id=1
    question=سوال
    is_multiple=0
    is_anonymous=0
    options[]=گزینه ۱
    options[]=گزینه ۲
    options[]=گزینه ۳
```

### Vote
```http
POST /api/polls.php?action=vote
    poll_id=1
    option_id=2
```

### Results
```http
GET /api/polls.php?action=results&poll_id=1
```

---

## Search API `/api/search.php`

### Messages
```http
GET /api/search.php?action=messages&q=سلام&chat_id=1&limit=20
```

### Saved
```http
GET /api/search.php?action=saved
POST /api/search.php?action=save
    message_id=123
```

### Global
```http
GET /api/search.php?action=global&q=keyword
```

---

## Link Preview API `/api/preview.php`

### Get Preview
```http
GET /api/preview.php?action=preview&url=https://github.com
```

**Supported sites:** YouTube, Aparat, Twitter, Instagram, GitHub, Spotify, TikTok, Wikipedia, Telegram, Vimeo

---

## Push API `/api/push.php`

### Subscribe
```http
POST /api/push.php?action=subscribe
    endpoint=https://fcm.googleapis.com/...
    keys[p256dh]=...
    keys[auth]=...
```

### Unsubscribe
```http
POST /api/push.php?action=unsubscribe
    endpoint=https://...
```

### VAPID Key
```http
GET /api/push.php?action=vapid_key
```

---

## Upload API `/api/upload.php`

### Upload File
```http
POST /api/upload.php
Content-Type: multipart/form-data

file=@photo.jpg
type=image
```

**Max size:** 50 MB
**Allowed:** images, videos, audio, PDF, ZIP, DOC

---

## Themes API `/api/themes.php`

### List
```http
GET /api/themes.php?action=list
```

### Save Custom
```http
POST /api/themes.php?action=save
    name=MyTheme
    primary=#8b5cf6
    secondary=#ec4899
    accent=#06b6d4
```

---

## Stats API `/api/stats.php`

### User Stats
```http
GET /api/stats.php?action=user
```

### Chat Stats
```http
GET /api/stats.php?action=chat&chat_id=1
```

---

## Pusher Auth `/api/pusher_auth.php`

### Auth
```http
POST /api/pusher_auth.php
Content-Type: application/x-www-form-urlencoded

socket_id=12345.67890
channel_name=private-user-1
```

---

## Error Codes

| Code | Meaning |
|------|---------|
| 200  | OK |
| 400  | Bad Request |
| 401  | Unauthorized |
| 403  | Forbidden |
| 404  | Not Found |
| 429  | Rate Limited |
| 500  | Server Error |

## Rate Limits

| Endpoint | Limit |
|----------|-------|
| Login | 10 / hour / IP |
| Register | 3 / hour / IP |
| Send message | 600 / hour / user |
| API general | 1000 / hour / user |

## WebSocket (Pusher)

Subscribe به:
- `private-user-{user_id}` — اعلان‌های شخصی
- `private-chat-{chat_id}` — پیام‌های چت
- `presence-chat-{chat_id}` — وضعیت آنلاین اعضا

### Events

| Event | Payload |
|-------|---------|
| `new-message` | `{ message: {...} }` |
| `typing` | `{ user_id, chat_id }` |
| `incoming-call` | `{ call_id, from_user, type }` |
| `user-online` | `{ user_id }` |
| `user-offline` | `{ user_id }` |
| `message-reacted` | `{ message_id, emoji, user_id }` |
| `message-deleted` | `{ message_id }` |

## Examples (cURL)

```bash
# Login
curl -X POST https://yourdomain.com/api/auth.php?action=login \
  -d "username=mahan&password=secret" \
  -c cookies.txt

# Get chats
curl https://yourdomain.com/api/chats.php?action=list -b cookies.txt

# Send message
curl -X POST https://yourdomain.com/api/chats.php?action=send \
  -d "chat_id=1&content=Hello&type=text" \
  -b cookies.txt

# Transfer money
curl -X POST https://yourdomain.com/api/wallet.php?action=transfer \
  -d "currency=IRR&to=sara&amount=50000&note=test" \
  -b cookies.txt
```

## Examples (JavaScript)

```js
// Login
const r = await fetch('/api/auth.php?action=login', {
  method: 'POST',
  body: new URLSearchParams({ username: 'mahan', password: 'secret' }),
  credentials: 'same-origin'
});
const { success, redirect } = await r.json();
if (success) location.href = redirect;

// Get chats
const chats = await (await fetch('/api/chats.php?action=list', { credentials: 'same-origin' })).json();
console.log(chats.chats);

// WebSocket
const pusher = new Pusher('key', { cluster: 'mt1', authEndpoint: '/api/pusher_auth.php' });
const channel = pusher.subscribe('private-user-1');
channel.bind('new-message', data => console.log(data.message));
```

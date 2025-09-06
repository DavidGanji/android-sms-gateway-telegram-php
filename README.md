خ

````markdown
# SMS Gateway → Telegram (PHP Webhook)

[English below](#english)

---

## 📌 معرفی (فارسی)

این ریپو یک **وبهوک PHP** برای پروژه  
[android_income_sms_gateway_webhook](https://github.com/bogkonstantin/android_income_sms_gateway_webhook)  
است که پیامک‌های دریافتی از اپ اندروید را گرفته و مستقیماً به تلگرام ارسال می‌کند.

### ✨ ویژگی‌ها
- دریافت JSON از اپ (فیلدهای `from, text, sentStamp, receivedStamp, sim`)  
- تبدیل زمان‌ها به UTC و زمان محلی (`LOCAL_TZ`)  
- ارسال پیام HTML به تلگرام  
- لاگ کامل درخواست‌ها و پاسخ تلگرام در پوشه `logs/`  

### ⚙️ راه‌اندازی
1. PHP 7.4+ و افزونه cURL را فعال کنید.  
2. فایل `sms.php` را روی سرور خود قرار دهید.  
3. فایل `.env` را از روی `.env.example` بسازید و مقادیر را تنظیم کنید:  

```ini
BOT_TOKEN=123456:ABCDEF-your-bot-token
CHAT_ID=123456789
SHARED_SECRET=david
LOCAL_TZ=Asia/Tehran
````

4. در اپ اندروید، آدرس وبهوک را روی دامنه خود ست کنید:

```
https://yourdomain.com/sms.php
```

### 📤 مثال تست (curl)

```bash
curl -X POST https://yourdomain.com/sms.php \
  -H "Content-Type: application/json" \
  -H "X-Shared-Secret: david" \
  -d '{"from":"12345","text":"سلام","sentStamp":1757166013390,"receivedStamp":1757166013391,"sim":"sim1"}'
```

---

## English

This repo provides a **PHP webhook** for
[android\_income\_sms\_gateway\_webhook](https://github.com/bogkonstantin/android_income_sms_gateway_webhook),
which forwards incoming SMS from the Android app directly to Telegram.

### ✨ Features

* Accepts JSON payload (`from, text, sentStamp, receivedStamp, sim`)
* Converts timestamps to UTC and local time (`LOCAL_TZ`)
* Sends formatted HTML messages to Telegram
* Logs requests and Telegram responses into `logs/`

### ⚙️ Setup

1. Use PHP 7.4+ with cURL enabled.
2. Deploy `sms.php` on your server.
3. Create `.env` file (from `.env.example`) and configure:

```ini
BOT_TOKEN=123456:ABCDEF-your-bot-token
CHAT_ID=123456789
SHARED_SECRET=david
LOCAL_TZ=Asia/Baku
```

4. Set the Webhook URL in the Android app:

```
https://yourdomain.com/sms.php
```

### 📤 Example (curl)

```bash
curl -X POST https://yourdomain.com/sms.php \
  -H "Content-Type: application/json" \
  -H "X-Shared-Secret: david" \
  -d '{"from":"12345","text":"hello","sentStamp":1757166013390,"receivedStamp":1757166013391,"sim":"sim1"}'
```

---

```



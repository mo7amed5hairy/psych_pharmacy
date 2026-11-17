# النسخ الاحتياطي والاستعادة

نسخة كاملة من **ملفات المشروع + قاعدة بيانات MySQL** تُنشأ من داخل التطبيق،
بدون Windows Task Scheduler وبدون cron خارجي.

## ما الذي يُنسخ

| البند | القيمة |
|---|---|
| مسار الحفظ الافتراضي | `C:/Backups/psych_pharmacy` (خارج مجلد المشروع) |
| اسم الملف | `backup-YYYY-MM-DD_HHMMSS.zip` |
| محتوى الأرشيف | `project/` ملفات المشروع، `database.sql`، `RESTORE.txt` |
| قاعدة البيانات | اتصال `DB_CONNECTION` الحالي (MySQL) |
| مدة الاحتفاظ | 14 يوماً (قابلة للتغيير) |
| المستبعد | `vendor`, `node_modules`, `.git`, `storage/logs`, `storage/framework`, `storage/app/backups`, `storage/app/backup-temp`, `public/hot`, `public/storage`, ملفات الكاش |

`vendor` و`node_modules` لا يُنسخان لأنهما يُعاد تثبيتهما من `composer.lock`
و`package-lock.json`. السجلات والكاش الكبير لا قيمة لهما في الاستعادة.

## التوقيت

- **تلقائياً:** أول زيارة يومية للتطبيق بعد الساعة **02:30** تُشغّل النسخ في الخلفية
  بعد إرسال الصفحة للمستخدم (`defer`). لا يؤثر على سرعة التصفح.
- إذا فشلت المحاولة، تُعاد المحاولة بعد ساعة على الأكثر، وليس مع كل طلب.
- **يدوياً:** زر «نسخة احتياطية الآن» في صفحة المسودات داخل «أدوات الصيانة التلقائية».
  الزر يظهر لحساب المالك `1001` فقط، وينفّذ النسخ فوراً.
- **من الطرفية:**

```bash
php artisan backup:project            # نسخة الآن
php artisan backup:project --if-due   # نسخة فقط إذا كانت اليومية مستحقة
```

## الإعدادات

القيم الافتراضية في `config/project_backup.php` وتُضبط من `.env` عند الحاجة:

```env
PROJECT_BACKUP_ENABLED=true
PROJECT_BACKUP_PATH=C:/Backups/psych_pharmacy
PROJECT_BACKUP_KEEP_DAYS=14
PROJECT_BACKUP_RUN_AFTER_HOUR=2
PROJECT_BACKUP_RUN_AFTER_MINUTE=30
PROJECT_BACKUP_MYSQLDUMP="C:/Program Files/MySQL/MySQL Server 9.6/bin/mysqldump.exe"
PROJECT_BACKUP_TIMEOUT=300
# PROJECT_BACKUP_EXTERNAL_PATH=E:/Backups/psych_pharmacy
```

- `PROJECT_BACKUP_EXTERNAL_PATH` اختياري: إن ضُبط تُنسخ أحدث نسخة أيضاً إلى قرص
  خارجي أو مجلد شبكة بعد نجاح النسخة المحلية.
- يجب أن يكون إصدار `mysqldump` مطابقاً لإصدار خادم MySQL (هنا 9.6).
- كلمة مرور قاعدة البيانات تُمرَّر عبر ملف خيارات مؤقت يُحذف فوراً، ولا تظهر في
  سطر الأوامر.

بعد تغيير الإعدادات:

```bash
php artisan config:clear
```

## الاستعادة (مختبرة)

1. فك ضغط الأرشيف في مجلد مؤقت:

```bash
php -r "$z=new ZipArchive; $z->open('C:/Backups/psych_pharmacy/backup-YYYY-MM-DD_HHMMSS.zip'); $z->extractTo('C:/restore-temp'); $z->close();"
```

2. انسخ محتويات `C:/restore-temp/project/` فوق مجلد المشروع على السيرفر
   (أو إلى مجلد مشروع جديد ثم اضبط Apache عليه).

3. أنشئ قاعدة بيانات فارغة بنفس الاسم والترميز إن كانت تالفة:

```sql
CREATE DATABASE pharmacy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. استورد ملف قاعدة البيانات:

```bash
"C:\Program Files\MySQL\MySQL Server 9.6\bin\mysql.exe" -u MKH -p pharmacy_db < C:\restore-temp\database.sql
```

أو من phpMyAdmin: اختر قاعدة البيانات ثم **Import** ثم ملف `database.sql`.

5. من مجلد المشروع:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan view:cache
```

6. تأكد من صلاحيات الكتابة على `storage/` و`bootstrap/cache/`.

7. تحقق من البيانات بعد الاستعادة:

```sql
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM medicines;
SELECT COUNT(*) FROM stocks;
SELECT COUNT(*) FROM invoices;
SELECT COUNT(*) FROM invoice_items;
SELECT COUNT(*) FROM dispensed_medicines;
SELECT COUNT(*) FROM drafts;
```

آخر اختبار استعادة (17 نوفمبر 2026): استُوردت النسخة في قاعدة
`pharmacy_restore_test` ثم طابقت أعداد الصفوف في الجداول الأساسية
(`users`, `unit_types`, `medicines`, `stocks`, `invoices`, `invoice_items`,
`dispensed_medicines`, `drafts`, `user_permissions`) ثم حُذفت قاعدة الاختبار.

## ملاحظات أمنية

- الأرشيف يحتوي `.env` وبيانات دخول قاعدة البيانات. احفظ مجلد النسخ بصلاحيات
  مقيدة ولا تضعه داخل `public` أو أي رابط عام، ولا ترفعه إلى Git.
- النسخة على نفس السيرفر لا تحمي من تلف القرص أو فقدان السيرفر بالكامل؛ اضبط
  `PROJECT_BACKUP_EXTERNAL_PATH` على قرص خارجي أو مجلد شبكة، أو انسخ أحدث ملف
  يدوياً خارج السيرفر دورياً.
- حالة آخر نسخة (ناجحة/فاشلة، الاسم، الحجم، سبب الفشل) تظهر في صفحة المسودات.
- قفل ملف واحد (`storage/app/backup-temp/project-backup.lock`) يمنع تشغيل نسختين
  في نفس الوقت حتى لو فتح عدة مستخدمين الصفحة معاً.

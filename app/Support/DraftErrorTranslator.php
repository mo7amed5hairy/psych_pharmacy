<?php

namespace App\Support;

/**
 * Keeps every message shown on the drafts recovery screen in Arabic.
 *
 * Most domain exceptions are already Arabic. English text can still appear
 * through the framework validator (the application locale is "en") or through
 * a raw PHP/database exception. This helper translates those messages without
 * changing the ones that are already readable.
 */
class DraftErrorTranslator
{
    /** @var array<string, string> */
    private const ATTRIBUTES = [
        'uuid' => 'معرّف المسودة',
        'type' => 'نوع المسودة',
        'revision' => 'رقم المراجعة',
        'payload' => 'بيانات المسودة',
        'draft' => 'المسودة',
        'items' => 'الأصناف',
        'items.*.medicine_id' => 'الدواء',
        'items.*.quantity' => 'الكمية',
        'medicines' => 'الأدوية المنصرفة',
        'medicines.*.medicine_id' => 'الصنف',
        'medicines.*.dispense_date' => 'التاريخ',
        'medicines.*.referral_number' => 'رقم التحويل',
        'medicines.*.quantity' => 'الكمية',
        'medicines.*.name' => 'اسم الصنف',
        'medicines.*.unit_type_id' => 'الوحدة',
        'medicines.*.price_hotline' => 'سعر الخط الساخن',
        'medicines.*.price_contract' => 'سعر الارتباط',
        'medicines.*.price_clinic' => 'سعر العيادة',
        'medicine_id' => 'الدواء',
        'stock_id' => 'سجل الرصيد',
        'quantity' => 'الكمية',
        'dispensed' => 'المنصرف',
        'stock_date' => 'تاريخ الرصيد',
        'referral_number' => 'رقم التحويل',
        'invoice_date' => 'التاريخ',
        'dispense_date' => 'التاريخ',
        'patient_name' => 'اسم المريض',
        'name' => 'الاسم',
        'unit_type_id' => 'الوحدة',
        'price_hotline' => 'سعر الخط الساخن',
        'price_contract' => 'سعر الارتباط',
        'price_clinic' => 'سعر العيادة',
        'from' => 'تاريخ من',
        'to' => 'تاريخ إلى',
        'month' => 'الشهر',
        'year' => 'السنة',
        'other' => 'الحقل الآخر',
    ];

    /**
     * Common Laravel validation lines. Group 1 is the attribute, group 2 (when
     * present) is the rule parameter.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '/\bThe ([\w.]+) field is required\./' => 'خانة :attribute مطلوبة.',
        '/\bThe ([\w.]+) field must be a string\./' => 'خانة :attribute يجب أن تكون نصاً.',
        '/\bThe ([\w.]+) field must be an array\./' => 'خانة :attribute يجب أن تكون قائمة.',
        '/\bThe ([\w.]+) field must be an integer\./' => 'خانة :attribute يجب أن تكون رقماً صحيحاً.',
        '/\bThe ([\w.]+) field must be numeric\./' => 'خانة :attribute يجب أن تكون رقماً.',
        '/\bThe ([\w.]+) field must be a number\./' => 'خانة :attribute يجب أن تكون رقماً.',
        '/\bThe ([\w.]+) field must be a valid date\./' => 'خانة :attribute يجب أن تكون تاريخاً صحيحاً.',
        '/\bThe ([\w.]+) field must be a valid UUID\./' => 'خانة :attribute يجب أن تكون معرّفاً صحيحاً.',
        '/\bThe ([\w.]+) field must be a valid uuid\./' => 'خانة :attribute يجب أن تكون معرّفاً صحيحاً.',
        '/\bThe ([\w.]+) field must not be greater than (\d+) characters\./' => 'نص :attribute يجب ألا يتجاوز :max حرفاً.',
        '/\bThe ([\w.]+) field must be at least (\d+) characters\./' => 'نص :attribute يجب أن يكون :min حرفاً على الأقل.',
        '/\bThe ([\w.]+) field must have at least (\d+) items\./' => 'يجب أن تحتوي :attribute على :min عنصر على الأقل.',
        '/\bThe ([\w.]+) field must have at least \d+ items\./' => 'يجب أن تحتوي :attribute على عنصر واحد على الأقل.',
        '/\bThe ([\w.]+) field must be at least ([\d.]+)\./' => 'قيمة :attribute يجب أن تكون :min على الأقل.',
        '/\bThe ([\w.]+) field must not be greater than ([\d.]+)\./' => 'قيمة :attribute يجب ألا تتجاوز :max.',
        '/\bThe ([\w.]+) field is invalid\./' => 'خانة :attribute غير صحيحة.',
        '/\bThe selected ([\w.]+) is invalid\./' => 'القيمة المختارة لـ :attribute غير صحيحة.',
        '/\bThe ([\w.]+) has already been taken\./' => 'القيمة :attribute مستخدمة مسبقاً.',
    ];

    public static function translate(mixed $message): string
    {
        if (is_array($message)) {
            $message = reset($message) ?: '';
        }

        $message = trim((string) $message);

        if ($message === '') {
            return self::fallback();
        }

        $translated = $message;

        foreach (self::PATTERNS as $pattern => $template) {
            $translated = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($template): string {
                    $result = str_replace(
                        ':attribute',
                        self::attribute($matches[1] ?? ''),
                        $template
                    );

                    if (isset($matches[2])) {
                        $result = str_replace(
                            [':min', ':max', ':value'],
                            $matches[2],
                            $result
                        );
                    }

                    return $result;
                },
                $translated
            ) ?? $translated;
        }

        // Messages generated while the temporary Arabic locale is active may
        // still carry the raw field name when it has no label in the lang file.
        $translated = preg_replace_callback(
            '/\b[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_*]+)+\b/',
            static fn (array $matches): string => self::attribute($matches[0]),
            $translated
        ) ?? $translated;

        return self::hasArabic($translated) ? trim($translated) : self::fallback();
    }

    /**
     * @param array<int|string, mixed> $messages
     * @return array<int|string, mixed>
     */
    public static function translateMany(array $messages): array
    {
        foreach ($messages as $key => $message) {
            $messages[$key] = is_array($message)
                ? self::translateMany($message)
                : self::translate($message);
        }

        return $messages;
    }

    private static function attribute(string $key): string
    {
        $normalized = preg_replace('/\[(\d+)\]/', '.$1', trim($key, " \t\n\r\0\x0B\"'[]"))
            ?: $key;

        if (isset(self::ATTRIBUTES[$normalized])) {
            return self::ATTRIBUTES[$normalized];
        }

        foreach (self::ATTRIBUTES as $pattern => $label) {
            if (!str_contains($pattern, '*')) {
                continue;
            }

            $regex = '#^' . str_replace('\*', '([^.]*)', preg_quote($pattern, '#')) . '\z#u';

            if (preg_match($regex, $normalized) === 1) {
                return $label;
            }
        }

        $segments = explode('.', $normalized);
        $last = (string) end($segments);

        return self::ATTRIBUTES[$last] ?? (str_replace('_', ' ', $last) ?: 'الحقل');
    }

    private static function hasArabic(string $value): bool
    {
        return preg_match('/\p{Arabic}/u', $value) === 1;
    }

    private static function fallback(): string
    {
        return 'حدث خطأ أثناء حفظ المسودة. يرجى مراجعة البيانات والمحاولة مرة أخرى.';
    }
}

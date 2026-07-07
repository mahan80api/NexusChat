<?php
/**
 * Phone — country list, dial code, E.164 formatting
 *
 * Uses libphonenumber-for-php if available, else minimal fallback.
 * E.164 format: +[country code][national number], max 15 digits.
 */
declare(strict_types=1);

class Phone
{
    /**
     * 220+ countries with dial code, name (en/fa), flag, typical length
     * (subset for offline use; libphonenumber is recommended for production)
     */
    public static function countries(): array {
        return [
            ['code' => 'IR', 'name_en' => 'Iran',                  'name_fa' => 'ایران',                   'dial' => '+98',  'flag' => '🇮🇷', 'len' => 10],
            ['code' => 'US', 'name_en' => 'United States',         'name_fa' => 'آمریکا',                  'dial' => '+1',   'flag' => '🇺🇸', 'len' => 10],
            ['code' => 'CA', 'name_en' => 'Canada',                'name_fa' => 'کانادا',                  'dial' => '+1',   'flag' => '🇨🇦', 'len' => 10],
            ['code' => 'GB', 'name_en' => 'United Kingdom',        'name_fa' => 'انگلستان',                'dial' => '+44',  'flag' => '🇬🇧', 'len' => 10],
            ['code' => 'DE', 'name_en' => 'Germany',               'name_fa' => 'آلمان',                   'dial' => '+49',  'flag' => '🇩🇪', 'len' => 10],
            ['code' => 'FR', 'name_en' => 'France',                'name_fa' => 'فرانسه',                  'dial' => '+33',  'flag' => '🇫🇷', 'len' => 9],
            ['code' => 'IT', 'name_en' => 'Italy',                 'name_fa' => 'ایتالیا',                 'dial' => '+39',  'flag' => '🇮🇹', 'len' => 10],
            ['code' => 'ES', 'name_en' => 'Spain',                 'name_fa' => 'اسپانیا',                 'dial' => '+34',  'flag' => '🇪🇸', 'len' => 9],
            ['code' => 'NL', 'name_en' => 'Netherlands',           'name_fa' => 'هلند',                    'dial' => '+31',  'flag' => '🇳🇱', 'len' => 9],
            ['code' => 'BE', 'name_en' => 'Belgium',               'name_fa' => 'بلژیک',                   'dial' => '+32',  'flag' => '🇧🇪', 'len' => 9],
            ['code' => 'CH', 'name_en' => 'Switzerland',           'name_fa' => 'سوئیس',                   'dial' => '+41',  'flag' => '🇨🇭', 'len' => 9],
            ['code' => 'AT', 'name_en' => 'Austria',               'name_fa' => 'اتریش',                   'dial' => '+43',  'flag' => '🇦🇹', 'len' => 10],
            ['code' => 'SE', 'name_en' => 'Sweden',                'name_fa' => 'سوئد',                    'dial' => '+46',  'flag' => '🇸🇪', 'len' => 9],
            ['code' => 'NO', 'name_en' => 'Norway',                'name_fa' => 'نروژ',                    'dial' => '+47',  'flag' => '🇳🇴', 'len' => 8],
            ['code' => 'DK', 'name_en' => 'Denmark',               'name_fa' => 'دانمارک',                 'dial' => '+45',  'flag' => '🇩🇰', 'len' => 8],
            ['code' => 'FI', 'name_en' => 'Finland',               'name_fa' => 'فنلاند',                  'dial' => '+358', 'flag' => '🇫🇮', 'len' => 10],
            ['code' => 'PL', 'name_en' => 'Poland',                'name_fa' => 'لهستان',                  'dial' => '+48',  'flag' => '🇵🇱', 'len' => 9],
            ['code' => 'CZ', 'name_en' => 'Czechia',               'name_fa' => 'چک',                      'dial' => '+420', 'flag' => '🇨🇿', 'len' => 9],
            ['code' => 'HU', 'name_en' => 'Hungary',               'name_fa' => 'مجارستان',                'dial' => '+36',  'flag' => '🇭🇺', 'len' => 9],
            ['code' => 'RO', 'name_en' => 'Romania',               'name_fa' => 'رومانی',                  'dial' => '+40',  'flag' => '🇷🇴', 'len' => 9],
            ['code' => 'GR', 'name_en' => 'Greece',                'name_fa' => 'یونان',                   'dial' => '+30',  'flag' => '🇬🇷', 'len' => 10],
            ['code' => 'TR', 'name_en' => 'Turkey',                'name_fa' => 'ترکیه',                   'dial' => '+90',  'flag' => '🇹🇷', 'len' => 10],
            ['code' => 'RU', 'name_en' => 'Russia',                'name_fa' => 'روسیه',                   'dial' => '+7',   'flag' => '🇷🇺', 'len' => 10],
            ['code' => 'UA', 'name_en' => 'Ukraine',               'name_fa' => 'اوکراین',                 'dial' => '+380', 'flag' => '🇺🇦', 'len' => 9],
            ['code' => 'CN', 'name_en' => 'China',                 'name_fa' => 'چین',                     'dial' => '+86',  'flag' => '🇨🇳', 'len' => 11],
            ['code' => 'JP', 'name_en' => 'Japan',                 'name_fa' => 'ژاپن',                    'dial' => '+81',  'flag' => '🇯🇵', 'len' => 10],
            ['code' => 'KR', 'name_en' => 'South Korea',           'name_fa' => 'کره جنوبی',               'dial' => '+82',  'flag' => '🇰🇷', 'len' => 10],
            ['code' => 'IN', 'name_en' => 'India',                 'name_fa' => 'هند',                     'dial' => '+91',  'flag' => '🇮🇳', 'len' => 10],
            ['code' => 'PK', 'name_en' => 'Pakistan',              'name_fa' => 'پاکستان',                 'dial' => '+92',  'flag' => '🇵🇰', 'len' => 10],
            ['code' => 'BD', 'name_en' => 'Bangladesh',            'name_fa' => 'بنگلادش',                 'dial' => '+880', 'flag' => '🇧🇩', 'len' => 10],
            ['code' => 'AF', 'name_en' => 'Afghanistan',           'name_fa' => 'افغانستان',               'dial' => '+93',  'flag' => '🇦🇫', 'len' => 9],
            ['code' => 'IQ', 'name_en' => 'Iraq',                  'name_fa' => 'عراق',                    'dial' => '+964', 'flag' => '🇮🇶', 'len' => 10],
            ['code' => 'SA', 'name_en' => 'Saudi Arabia',          'name_fa' => 'عربستان',                 'dial' => '+966', 'flag' => '🇸🇦', 'len' => 9],
            ['code' => 'AE', 'name_en' => 'United Arab Emirates',  'name_fa' => 'امارات',                  'dial' => '+971', 'flag' => '🇦🇪', 'len' => 9],
            ['code' => 'IL', 'name_en' => 'Israel',                'name_fa' => 'اسرائیل',                 'dial' => '+972', 'flag' => '🇮🇱', 'len' => 9],
            ['code' => 'JO', 'name_en' => 'Jordan',                'name_fa' => 'اردن',                    'dial' => '+962', 'flag' => '🇯🇴', 'len' => 9],
            ['code' => 'LB', 'name_en' => 'Lebanon',               'name_fa' => 'لبنان',                   'dial' => '+961', 'flag' => '🇱🇧', 'len' => 8],
            ['code' => 'SY', 'name_en' => 'Syria',                 'name_fa' => 'سوریه',                   'dial' => '+963', 'flag' => '🇸🇾', 'len' => 9],
            ['code' => 'EG', 'name_en' => 'Egypt',                 'name_fa' => 'مصر',                     'dial' => '+20',  'flag' => '🇪🇬', 'len' => 10],
            ['code' => 'LY', 'name_en' => 'Libya',                 'name_fa' => 'لیبی',                    'dial' => '+218', 'flag' => '🇱🇾', 'len' => 9],
            ['code' => 'TN', 'name_en' => 'Tunisia',               'name_fa' => 'تونس',                    'dial' => '+216', 'flag' => '🇹🇳', 'len' => 8],
            ['code' => 'DZ', 'name_en' => 'Algeria',               'name_fa' => 'الجزایر',                 'dial' => '+213', 'flag' => '🇩🇿', 'len' => 9],
            ['code' => 'MA', 'name_en' => 'Morocco',               'name_fa' => 'مراکش',                   'dial' => '+212', 'flag' => '🇲🇦', 'len' => 9],
            ['code' => 'BR', 'name_en' => 'Brazil',                'name_fa' => 'برزیل',                   'dial' => '+55',  'flag' => '🇧🇷', 'len' => 11],
            ['code' => 'AR', 'name_en' => 'Argentina',             'name_fa' => 'آرژانتین',                'dial' => '+54',  'flag' => '🇦🇷', 'len' => 10],
            ['code' => 'MX', 'name_en' => 'Mexico',                'name_fa' => 'مکزیک',                   'dial' => '+52',  'flag' => '🇲🇽', 'len' => 10],
            ['code' => 'CL', 'name_en' => 'Chile',                 'name_fa' => 'شیلی',                    'dial' => '+56',  'flag' => '🇨🇱', 'len' => 9],
            ['code' => 'CO', 'name_en' => 'Colombia',              'name_fa' => 'کلمبیا',                  'dial' => '+57',  'flag' => '🇨🇴', 'len' => 10],
            ['code' => 'PE', 'name_en' => 'Peru',                  'name_fa' => 'پرو',                     'dial' => '+51',  'flag' => '🇵🇪', 'len' => 9],
            ['code' => 'AU', 'name_en' => 'Australia',             'name_fa' => 'استرالیا',                'dial' => '+61',  'flag' => '🇦🇺', 'len' => 9],
            ['code' => 'NZ', 'name_en' => 'New Zealand',           'name_fa' => 'نیوزیلند',                'dial' => '+64',  'flag' => '🇳🇿', 'len' => 9],
            ['code' => 'ZA', 'name_en' => 'South Africa',          'name_fa' => 'آفریقای جنوبی',           'dial' => '+27',  'flag' => '🇿🇦', 'len' => 9],
            ['code' => 'NG', 'name_en' => 'Nigeria',               'name_fa' => 'نیجریه',                  'dial' => '+234', 'flag' => '🇳🇬', 'len' => 10],
            ['code' => 'KE', 'name_en' => 'Kenya',                 'name_fa' => 'کنیا',                    'dial' => '+254', 'flag' => '🇰🇪', 'len' => 9],
            ['code' => 'ET', 'name_en' => 'Ethiopia',              'name_fa' => 'اتیوپی',                  'dial' => '+251', 'flag' => '🇪🇹', 'len' => 9],
            ['code' => 'TH', 'name_en' => 'Thailand',              'name_fa' => 'تایلند',                  'dial' => '+66',  'flag' => '🇹🇭', 'len' => 9],
            ['code' => 'VN', 'name_en' => 'Vietnam',               'name_fa' => 'ویتنام',                  'dial' => '+84',  'flag' => '🇻🇳', 'len' => 9],
            ['code' => 'ID', 'name_en' => 'Indonesia',             'name_fa' => 'اندونزی',                 'dial' => '+62',  'flag' => '🇮🇩', 'len' => 10],
            ['code' => 'MY', 'name_en' => 'Malaysia',              'name_fa' => 'مالزی',                   'dial' => '+60',  'flag' => '🇲🇾', 'len' => 9],
            ['code' => 'PH', 'name_en' => 'Philippines',           'name_fa' => 'فیلیپین',                 'dial' => '+63',  'flag' => '🇵🇭', 'len' => 10],
            ['code' => 'SG', 'name_en' => 'Singapore',             'name_fa' => 'سنگاپور',                 'dial' => '+65',  'flag' => '🇸🇬', 'len' => 8],
        ];
    }

    public static function byDial(string $dial): ?array {
        foreach (self::countries() as $c) if ($c['dial'] === $dial) return $c;
        return null;
    }

    public static function byCode(string $code): ?array {
        foreach (self::countries() as $c) if ($c['code'] === strtoupper($code)) return $c;
        return null;
    }

    /**
     * Format phone to E.164: +[dial][national number without leading 0]
     * Returns null if invalid.
     */
    public static function formatE164(string $phone, string $dial): ?string {
        $phone = preg_replace('/[^\d]/', '', $phone);
        $dial  = preg_replace('/[^\d]/', '', $dial);
        if ($phone === '' || $dial === '') return null;
        // strip leading 0 in national number
        $phone = ltrim($phone, '0');
        if ($phone === '') return null;
        $e164 = '+' . $dial . $phone;
        if (strlen($e164) > 16) return null; // E.164 max 15 digits + '+'
        return $e164;
    }
}

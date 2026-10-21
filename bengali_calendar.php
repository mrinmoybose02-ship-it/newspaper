<?php
date_default_timezone_set('Asia/Kolkata');

/**
 * Bengali Calendar & Festival Auto-Set System
 */

class BengaliCalendar {
    private static $bn_digits  = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    private static $bn_months  = [1=>'বৈশাখ',2=>'জ্যৈষ্ঠ',3=>'আষাঢ়',4=>'শ্রাবণ',5=>'ভাদ্র',6=>'আশ্বিন',7=>'কার্তিক',8=>'অগ্রহায়ণ',9=>'পৌষ',10=>'মাঘ',11=>'ফাল্গুন',12=>'চৈত্র'];
    private static $bn_seasons = ['গ্রীষ্ম','বর্ষা','শরৎ','হেমন্ত','শীত','বসন্ত'];
    private static $bn_weekdays= ['রবিবার','সোমবার','মঙ্গলবার','বুধবার','বৃহস্পতিবার','শুক্রবার','শনিবার'];

    // ★★★ v6 সংশোধন (১৯-১০-২০২৬): আসল পঞ্জিকা (দ্রিক গণিত) অনুযায়ী বাংলা তারিখ ★★★
    // পুরোনো কোডে প্রতি মাসে নির্দিষ্ট ৩১/৩০ দিন ধরে হিসাব হতো, ফলে আসল পঞ্জিকার চেয়ে
    // তারিখ ১-২ দিন এগিয়ে দেখাত (যেমন ১৯-১০-২০২৬-এ "৩ কার্তিক" দেখাচ্ছিল,
    // সঠিক হলো "সোমবার, ১ কার্তিক, ১৪৩৩")।
    // এখন প্রতিটি বাংলা মাসের প্রকৃত শুরুর তারিখ (সংক্রান্তি ভিত্তিক) নিচের টেবিল থেকে আসে।
    // পঞ্জিকা-নিয়ম (গুপ্তপ্রেস/বিশুদ্ধসিদ্ধান্ত স্টাইল):
    //   সংক্রান্তি সূর্যাস্তের (~সন্ধ্যা ৬টা) আগে হলে → পরদিন নতুন মাস;
    //   সূর্যাস্তের পরে হলে → পরহিতে (সংক্রান্তির দিন +২) নতুন মাস।
    // উদাহরণ: তুলা সংক্রান্তি ১৭-১০-২০২৬ (সন্ধ্যা ৭:৪৮ IST, সূর্যাস্তের পরে) → ১ কার্তিক ১৪৩৩ = ১৯-১০-২০২৬ (সোমবার);
    //         মেষ সংক্রান্তি ১৪-০৪-২০২৭ (দুপুর ৩:২৩) → ১ বৈশাখ ১৪৩৪ = ১৫-০৪-২০২৭ (প্রকাশিত পঞ্জিকা মিল)।
    // টেবিলের বাইরের বছরগুলোতে পুরোনো নির্দিষ্ট হিসাব ফল-ব্যাক হিসেবে চলবে (fromFixedAlgo)।
    private static $bn_month_starts = [
        // বছর => [১ বৈশাখ, ১ জ্যৈষ্ঠ, ১ আষাঢ়, ১ শ্রাবণ, ১ ভাদ্র, ১ আশ্বিন, ১ কার্তিক, ১ অগ্রহায়ণ, ১ পৌষ, ১ মাঘ, ১ ফাল্গুন, ১ চৈত্র]
        '1430' => ['2023-04-15', '2023-05-16', '2023-06-17', '2023-07-18', '2023-08-18', '2023-09-18', '2023-10-19', '2023-11-18', '2023-12-17', '2024-01-16', '2024-02-14', '2024-03-15'],
        '1431' => ['2024-04-15', '2024-05-15', '2024-06-16', '2024-07-17', '2024-08-18', '2024-09-18', '2024-10-18', '2024-11-17', '2024-12-17', '2025-01-15', '2025-02-14', '2025-03-16'],
        '1432' => ['2025-04-15', '2025-05-16', '2025-06-16', '2025-07-17', '2025-08-18', '2025-09-18', '2025-10-18', '2025-11-17', '2025-12-17', '2026-01-15', '2026-02-14', '2026-03-16'],
        '1433' => ['2026-04-15', '2026-05-16', '2026-06-16', '2026-07-18', '2026-08-18', '2026-09-18', '2026-10-19', '2026-11-18', '2026-12-17', '2027-01-16', '2027-02-14', '2027-03-16'],
        '1434' => ['2027-04-15', '2027-05-16', '2027-06-17', '2027-07-18', '2027-08-18', '2027-09-18', '2027-10-19', '2027-11-18', '2027-12-17', '2028-01-16', '2028-02-14', '2028-03-15'],
        '1435' => ['2028-04-15', '2028-05-16', '2028-06-16', '2028-07-17', '2028-08-18', '2028-09-18', '2028-10-18', '2028-11-17', '2028-12-17', '2029-01-15', '2029-02-14', '2029-03-16'],
        '1436' => ['2029-04-15', '2029-05-16', '2029-06-16', '2029-07-17', '2029-08-18', '2029-09-18', '2029-10-18', '2029-11-17', '2029-12-17', '2030-01-15', '2030-02-14', '2030-03-16'],
        '1437' => ['2030-04-15', '2030-05-16', '2030-06-16', '2030-07-18', '2030-08-18', '2030-09-18', '2030-10-19', '2030-11-18', '2030-12-17', '2031-01-16', '2031-02-14', '2031-03-16'],
        '1438' => ['2031-04-15', '2031-05-16', '2031-06-17', '2031-07-18', '2031-08-18', '2031-09-18', '2031-10-19', '2031-11-18', '2031-12-17', '2032-01-16', '2032-02-14', '2032-03-15'],
        '1439' => ['2032-04-15', '2032-05-16', '2032-06-16', '2032-07-17', '2032-08-18', '2032-09-18', '2032-10-18', '2032-11-17', '2032-12-17', '2033-01-15', '2033-02-14', '2033-03-16'],
        '1440' => ['2033-04-15', '2033-05-16', '2033-06-16', '2033-07-18', '2033-08-18', '2033-09-18', '2033-10-18', '2033-11-17', '2033-12-17', '2034-01-15', '2034-02-14', '2034-03-16'],
        '1441' => ['2034-04-15', '2034-05-16', '2034-06-16', '2034-07-18', '2034-08-18', '2034-09-18', '2034-10-19', '2034-11-18', '2034-12-17', '2035-01-16', '2035-02-14', '2035-03-16'],
        '1442' => ['2035-04-15', '2035-05-16', '2035-06-17', '2035-07-18', '2035-08-18', '2035-09-18', '2035-10-19', '2035-11-18', '2035-12-17', '2036-01-16', '2036-02-14', '2036-03-15'],
    ];

    public static function toBengaliDate($date = null) {
        if ($date === null) $date = time();
        if (is_string($date)) $date = strtotime($date);
        if (is_numeric($date) && $date > 100000000000) $date = (int)($date/1000);

        $ymd = date('Y-m-d', $date);

        // প্রথমে দ্রিক পঞ্জিকার সংক্রান্তি-টেবিল, না পেলে পুরোনো নির্দিষ্ট হিসাব (ফল-ব্যাক)
        $conv = self::fromSankrantiTable($ymd);
        if ($conv === null) $conv = self::fromFixedAlgo($ymd);

        $b_day = $conv['day']; $b_month = $conv['month']; $b_year = $conv['year'];

        return [
            'day'    => $b_day, 'month' => $b_month, 'year' => $b_year,
            'day_bn' => self::toBengaliNumber($b_day),
            'month_bn' => self::$bn_months[$b_month],
            'year_bn'  => self::toBengaliNumber($b_year),
            'weekday_bn' => self::$bn_weekdays[(int)date('w', $date)],
            'season_bn'  => self::$bn_seasons[(int)(($b_month - 1) / 2)],
            'formatted' => self::toBengaliNumber($b_day) . ' ' . self::$bn_months[$b_month] . ', ' . self::toBengaliNumber($b_year),
            'formatted_full' => self::$bn_weekdays[(int)date('w', $date)] . ', ' . self::toBengaliNumber($b_day) . ' ' . self::$bn_months[$b_month] . ', ' . self::toBengaliNumber($b_year),
            'gregorian' => date('Y-m-d', $date)
        ];
    }

    // দ্রিক পঞ্জিকার সংক্রান্তি-টেবিল থেকে বাংলা তারিখ বের করা (টেবিলের সীমার বাইরে হলে null)
    private static function fromSankrantiTable($ymd) {
        $years = array_keys(self::$bn_month_starts);
        $count = count($years);
        for ($i = 0; $i < $count; $i++) {
            $yb        = $years[$i];
            $starts    = self::$bn_month_starts[$yb];
            $nextStart = ($i + 1 < $count) ? self::$bn_month_starts[$years[$i + 1]][0] : null;
            if ($ymd >= $starts[0] && ($nextStart === null || $ymd < $nextStart)) {
                $bMonth = 1; $bStart = $starts[0];
                for ($m = 1; $m < 12; $m++) {
                    if ($ymd >= $starts[$m]) { $bMonth = $m + 1; $bStart = $starts[$m]; }
                }
                $bDay = (int)(((int)strtotime($ymd) - (int)strtotime($bStart)) / 86400) + 1;
                return ['day' => $bDay, 'month' => $bMonth, 'year' => (int)$yb];
            }
        }
        return null;
    }

    // পুরোনো নির্দিষ্ট হিসাব (ফল-ব্যাক): ১ বৈশাখ ১৪৩৩ = ১৫-০৪-২০২৬ ধরে ৩১/৩০ দিনের গণনা
    private static function fromFixedAlgo($ymd) {
        $epoch     = strtotime('2026-04-15'); // ১ বৈশাখ ১৪৩৩
        $days_diff = (int)floor((strtotime($ymd) - $epoch) / 86400);

        $b_year = 1433; $b_month = 1; $b_day = 1;

        if ($days_diff >= 0) {
            $remaining = $days_diff;
            while ($remaining > 0) {
                $days_in_month = self::daysInBengaliMonth($b_month, $b_year);
                if ($remaining >= $days_in_month) {
                    $remaining -= $days_in_month;
                    $b_month++;
                    if ($b_month > 12) { $b_month = 1; $b_year++; }
                } else {
                    $b_day = 1 + $remaining;
                    $remaining = 0;
                }
            }
        } else {
            $b_year = 1433; $b_month = 1; $b_day = 1;
            $remaining = abs($days_diff);
            while ($remaining > 0) {
                if ($remaining >= $b_day) {
                    $remaining -= $b_day;
                    $b_month--;
                    if ($b_month < 1) { $b_month = 12; $b_year--; }
                    $b_day = self::daysInBengaliMonth($b_month, $b_year);
                } else {
                    $b_day -= $remaining;
                    $remaining = 0;
                }
            }
        }

        return ['day' => $b_day, 'month' => $b_month, 'year' => $b_year];
    }

    public static function daysInBengaliMonth($month, $year) {
        if ($month == 11) {
            $g_year = $year + 593;
            if ((($g_year % 4 == 0) && ($g_year % 100 != 0)) || ($g_year % 400 == 0)) return 31;
            return 30;
        }
        return ($month <= 5) ? 31 : 30;
    }

    public static function toBengaliNumber($num) {
        $str = (string)$num; $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $ch = $str[$i];
            if (ctype_digit($ch) && isset(self::$bn_digits[(int)$ch])) $result .= self::$bn_digits[(int)$ch];
            else $result .= $ch;
        }
        return $result;
    }
}

class BengaliFestivalCalendar {

    private static $lunarFestivals = [
        // 2026
        ['name'=>'সরস্বতী পূজা','name_en'=>'Saraswati Puja','start'=>'2026-02-01','end'=>'2026-02-01','logo'=>'saraswati.png','priority'=>75],
        ['name'=>'দোলযাত্রা','name_en'=>'Dol Yatra','start'=>'2026-03-03','end'=>'2026-03-03','logo'=>'dol_yatra.png','priority'=>75],
        ['name'=>'দোল পূর্ণিমা','name_en'=>'Dol Purnima','start'=>'2026-03-03','end'=>'2026-03-03','logo'=>'dol_purnima.png','priority'=>75],
        ['name'=>'বুদ্ধ পূর্ণিমা','name_en'=>'Buddha Purnima','start'=>'2026-05-31','end'=>'2026-05-31','logo'=>'buddha_purnima.png','priority'=>65],
        ['name'=>'রথযাত্রা','name_en'=>'Rath Yatra','start'=>'2026-07-03','end'=>'2026-07-03','logo'=>'rath_yatra.png','priority'=>75],
        ['name'=>'জন্মাষ্টমী','name_en'=>'Janmashtami','start'=>'2026-09-04','end'=>'2026-09-04','logo'=>'janmashtami.png','priority'=>70],
        ['name'=>'মহালয়া','name_en'=>'Mahalaya','start'=>'2026-10-10','end'=>'2026-10-10','logo'=>'mahalaya.png','priority'=>85],
        ['name'=>'মা দুর্গা অষ্টমী','name_en'=>'Durga Ashtami','start'=>'2026-10-19','end'=>'2026-10-19','logo'=>'durga_ashtami.png','priority'=>95],
        ['name'=>'মা দুর্গা নবমী','name_en'=>'Durga Nabami','start'=>'2026-10-20','end'=>'2026-10-20','logo'=>'durga_nabami.png','priority'=>95],
        ['name'=>'বিজয়া দশমী','name_en'=>'Bijoya Dashami','start'=>'2026-10-21','end'=>'2026-10-21','logo'=>'bijoya_dashami.png','priority'=>98],
        ['name'=>'দুর্গাপূজা','name_en'=>'Durga Puja','start'=>'2026-10-19','end'=>'2026-10-21','logo'=>'durga_puja.png','priority'=>90],
        ['name'=>'কোজাগরী লক্ষ্মী পূজা','name_en'=>'Kojagari Lakshmi Puja','start'=>'2026-10-25','end'=>'2026-10-25','logo'=>'lakshmi_puja.png','priority'=>85],
        ['name'=>'কালী পূজা','name_en'=>'Kali Puja','start'=>'2026-11-08','end'=>'2026-11-08','logo'=>'kali_puja.png','priority'=>90],
        ['name'=>'দীপাবলি','name_en'=>'Diwali','start'=>'2026-11-08','end'=>'2026-11-08','logo'=>'diwali.png','priority'=>90],
        ['name'=>'ভাই ফোঁটা','name_en'=>'Bhai Phonta','start'=>'2026-11-11','end'=>'2026-11-11','logo'=>'bhai_phonta.png','priority'=>75],

        // 2027
        ['name'=>'সরস্বতী পূজা','name_en'=>'Saraswati Puja','start'=>'2027-02-11','end'=>'2027-02-11','logo'=>'saraswati.png','priority'=>75],
        ['name'=>'দোলযাত্রা','name_en'=>'Dol Yatra','start'=>'2027-03-22','end'=>'2027-03-22','logo'=>'dol_yatra.png','priority'=>75],
        ['name'=>'রথযাত্রা','name_en'=>'Rath Yatra','start'=>'2027-06-24','end'=>'2027-06-24','logo'=>'rath_yatra.png','priority'=>75],
        ['name'=>'জন্মাষ্টমী','name_en'=>'Janmashtami','start'=>'2027-08-25','end'=>'2027-08-25','logo'=>'janmashtami.png','priority'=>70],
        ['name'=>'মহালয়া','name_en'=>'Mahalaya','start'=>'2027-09-29','end'=>'2027-09-29','logo'=>'mahalaya.png','priority'=>85],
        ['name'=>'মা দুর্গা অষ্টমী','name_en'=>'Durga Ashtami','start'=>'2027-10-08','end'=>'2027-10-08','logo'=>'durga_ashtami.png','priority'=>95],
        ['name'=>'মা দুর্গা নবমী','name_en'=>'Durga Nabami','start'=>'2027-10-09','end'=>'2027-10-09','logo'=>'durga_nabami.png','priority'=>95],
        ['name'=>'বিজয়া দশমী','name_en'=>'Bijoya Dashami','start'=>'2027-10-10','end'=>'2027-10-10','logo'=>'bijoya_dashami.png','priority'=>98],
        ['name'=>'দুর্গাপূজা','name_en'=>'Durga Puja','start'=>'2027-10-08','end'=>'2027-10-10','logo'=>'durga_puja.png','priority'=>90],
        ['name'=>'কোজাগরী লক্ষ্মী পূজা','name_en'=>'Kojagari Lakshmi Puja','start'=>'2027-10-17','end'=>'2027-10-17','logo'=>'lakshmi_puja.png','priority'=>85],
        ['name'=>'কালী পূজা','name_en'=>'Kali Puja','start'=>'2027-10-31','end'=>'2027-10-31','logo'=>'kali_puja.png','priority'=>90],
        ['name'=>'দীপাবলি','name_en'=>'Diwali','start'=>'2027-10-31','end'=>'2027-10-31','logo'=>'diwali.png','priority'=>90],
        ['name'=>'ভাই ফোঁটা','name_en'=>'Bhai Phonta','start'=>'2027-11-02','end'=>'2027-11-02','logo'=>'bhai_phonta.png','priority'=>75],
    ];

    private static function getFixedFestivals() {
        $currentYear = (int)date('Y');
        $years = [$currentYear - 1, $currentYear, $currentYear + 1, $currentYear + 2];
        $festivals = [];

        foreach ($years as $year) {
            $festivals[] = ['key'=>'new_year_'.$year,'name'=>'ইংরেজি নববর্ষ','name_en'=>'English New Year','start'=>"$year-01-01",'end'=>"$year-01-01",'logo'=>'new_year.png','priority'=>70];
            $festivals[] = ['key'=>'netaji_'.$year,'name'=>'নেতাজি জয়ন্তী','name_en'=>'Netaji Jayanti','start'=>"$year-01-23",'end'=>"$year-01-23",'logo'=>'netaji.png','priority'=>65];
            $festivals[] = ['key'=>'rep_day_'.$year,'name'=>'Republic Day','name_en'=>'Republic Day','start'=>"$year-01-26",'end'=>"$year-01-26",'logo'=>'republic_day.png','priority'=>70];
            $festivals[] = ['key'=>'pohela_falgun_'.$year,'name'=>'পহেলা ফাল্গুন','name_en'=>'Pohela Falgun','start'=>"$year-02-13",'end'=>"$year-02-13",'logo'=>'pohela_falgun.png','priority'=>80];
            $festivals[] = ['key'=>'chaitra_sankranti_'.$year,'name'=>'চৈত্র সংক্রান্তি','name_en'=>'Chaitra Sankranti','start'=>"$year-04-14",'end'=>"$year-04-14",'logo'=>'chaitra_sankranti.png','priority'=>80];
            $festivals[] = ['key'=>'pohela_boishakh_'.$year,'name'=>'পহেলা বৈশাখ','name_en'=>'Pohela Boishakh (Bengali New Year)','start'=>"$year-04-15",'end'=>"$year-04-15",'logo'=>'pohela_boishakh.png','priority'=>100];
            $festivals[] = ['key'=>'rabindra_jayanti_'.$year,'name'=>'রবীন্দ্র জয়ন্তী','name_en'=>'Rabindra Jayanti','start'=>"$year-05-09",'end'=>"$year-05-09",'logo'=>'rabindra_jayanti.png','priority'=>85];
            $festivals[] = ['key'=>'independence_'.$year,'name'=>'Independence Day','name_en'=>'Independence Day','start'=>"$year-08-15",'end'=>"$year-08-15",'logo'=>'independence_day.png','priority'=>70];
            $festivals[] = ['key'=>'christmas_'.$year,'name'=>'বড়দিন','name_en'=>'Christmas','start'=>"$year-12-25",'end'=>"$year-12-25",'logo'=>'christmas.png','priority'=>80];
            $festivals[] = ['key'=>'new_year_eve_'.$year,'name'=>'নববর্ষের পূর্বরাত্রি','name_en'=>'New Year Eve','start'=>"$year-12-31",'end'=>"$year-12-31",'logo'=>'new_year_eve.png','priority'=>70];
        }
        return $festivals;
    }

    public static function getAllFestivals() {
        $fixed = self::getFixedFestivals();
        return array_merge($fixed, self::$lunarFestivals);
    }

    public static function getMonthFestivals($year, $month) {
        $festivals = self::getAllFestivals();
        $matched = [];
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            foreach ($festivals as $f) {
                if (isset($f['start'], $f['end']) && $dateStr >= $f['start'] && $dateStr <= $f['end']) {
                    if (!isset($matched[$d])) $matched[$d] = $f;
                }
            }
        }
        return $matched;
    }

    public static function getActiveFestivals($date = null) {
        if ($date === null) $date = date('Y-m-d');
        elseif (is_numeric($date)) $date = date('Y-m-d', $date);
        elseif (is_string($date)) $date = date('Y-m-d', strtotime($date));

        $festivals = self::getAllFestivals();
        $matched = [];
        foreach ($festivals as $f) {
            if (isset($f['start'], $f['end']) && $date >= $f['start'] && $date <= $f['end']) $matched[] = $f;
        }
        usort($matched, function($a, $b) { return $b['priority'] - $a['priority']; });
        return $matched;
    }

    public static function getActiveFestival($date = null) {
        $active = self::getActiveFestivals($date);
        return !empty($active) ? $active[0] : null;
    }
}

// ==========================================
// TEST OUTPUT BLOCK (Stops White Screen)
// This will ONLY run if you open bengali_calendar.php directly.
// When included in admin_dashboard.php, it will automatically hide itself.
// ==========================================
if (!debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<div style='font-family: Arial; padding: 20px; background: #f9f9f9; border-radius: 8px;'>";

    $bnDate = BengaliCalendar::toBengaliDate();
    echo "<h2>আজকের বাংলা তারিখ:</h2>";
    echo "<p style='font-size: 20px; color: green;'>" . $bnDate['formatted_full'] . "</p>";

    $festival = BengaliFestivalCalendar::getActiveFestival();
    echo "<h2>আজকের উৎসব:</h2>";
    if ($festival) {
        echo "<p style='font-size: 18px; color: #d9534f;'>" . $festival['name'] . " (" . $festival['name_en'] . ")</p>";
    } else {
        echo "<p style='font-size: 16px; color: #777;'>আজ কোনো বিশেষ উৎসব নেই।</p>";
    }

    echo "</div>";
}

<?php

namespace App\Services\AI;

use App\Models\User;

class RoleAwareAiAssistant
{
    /** @return list<string> */
    public function capabilities(User $user): array
    {
        if ($user->hasRole('CEO', 'SUPER_ADMIN')) {
            return ['Markaz strategiyasi va KPI tahlili', 'Risklar va ustuvor vazifalar', 'Hodimlar va operatsiyalar', 'Premium va AI boshqaruvi'];
        }

        if ($user->hasRole('CENTER_ADMIN')) {
            return ['Kurs va guruh operatsiyalari', 'Davomat va hodimlar tahlili', 'Jadval va resurslar', 'Markaz KPI hisoboti'];
        }

        if ($user->hasRole('HEAD_TEACHER')) {
            return ['Dars rejasi va o‘quv mazmuni', 'Tajvid tushuntirishlari', 'Mentor vazifasini tayyorlash', 'Talaba risklarini tahlil qilish'];
        }

        if ($user->hasRole('MENTOR')) {
            return ['Talaba topshirig‘i tahlili', 'Shaxsiy feedback', 'Rivojlanish tavsiyalari', 'Keyingi mashg‘ulot rejasi'];
        }

        if ($user->hasRole('PARENT')) {
            return ['Farzand rivojlanish xulosasi', 'Davomat va dars tavsiyasi', 'Xavfsiz o‘quv odatlar', 'Mentorga savol tayyorlash'];
        }

        return ['Tajvid savollari', 'Dars materialini tushunish', 'Shaxsiy mashq rejasi', 'Xatosini tushuntirish'];
    }

    /** @return list<string> */
    public function suggestedPrompts(User $user): array
    {
        if ($user->hasRole('CEO', 'SUPER_ADMIN')) {
            return ['Markazdagi haftalik KPI ustuvorlarini tuz', 'Mavjud ma’lumotlardan operatsion xavflarni aniqlashga yordam ber'];
        }

        if ($user->hasRole('CENTER_ADMIN')) {
            return ['Bugungi guruhlar va davomat uchun rejani tuz', 'Jadval konflikti bo‘yicha yechim taklif qil'];
        }

        if ($user->hasRole('HEAD_TEACHER')) {
            return ['Tajvid darsi uchun 45 daqiqalik reja tuz', 'Bugungi mavzuga mos mashqlar tayyorla'];
        }

        if ($user->hasRole('MENTOR')) {
            return ['O‘quvchi tilovat xatosi uchun konstruktiv feedback yoz', 'Keyingi uchta mashg‘ulot uchun qisqa reja tuz'];
        }

        if ($user->hasRole('PARENT')) {
            return ['Farzandimga 20 daqiqalik Qur’on mashqini rejalashtir', 'Davomat va progressni o‘rganishga yordam ber'];
        }

        return ['Tajviddagi xatoni oddiy tilda tushuntir', 'Bugun uchun 15 daqiqalik o‘qish mashqini tuz'];
    }

    /** @return list<string> */
    public function contexts(User $user): array
    {
        if ($user->hasRole('CEO', 'SUPER_ADMIN')) {
            return ['Foydalanuvchi roli: CEO/Super Admin.', 'Markaz strategiyasi, KPI, xavf va operatsion qarorlarga yordam ber.', 'Aniqlab bo‘lmagan ma’lumotni haqiqat deb aytma.'];
        }

        if ($user->hasRole('CENTER_ADMIN')) {
            return ['Foydalanuvchi roli: markaz administratori.', 'Kurslar, guruhlar, xodimlar, davomat va markaz operatsiyalariga yordam ber.', 'Talab qilinmagan administrator harakatini bajarayotgan kabi yozma.'];
        }

        if ($user->hasRole('HEAD_TEACHER')) {
            return ['Foydalanuvchi roli: bosh ustoz.', 'Dars rejalari, o‘quv mazmuni, tajvid va o‘qituvchilar boshqaruviga yordam ber.', 'Qat’iy diniy hukm chiqarmasdan tavsiyalarni ustoz nazoratida ekanini ayt.'];
        }

        if ($user->hasRole('MENTOR')) {
            return ['Foydalanuvchi roli: mentor.', 'Talaba tilovati, topshirig‘i, feedback va individual rejalashtirishga yordam ber.', 'Tanqidni hurmatli, aniq va amaliy qil.', 'Tajweed natijasini Al-ohq real qiroat bahosi deb ko‘rsatma.'];
        }

        if ($user->hasRole('PARENT')) {
            return ['Foydalanuvchi roli: ota-ona.', 'Farzandning xavfsiz o‘qishi, davomat va progressiga oid umumiy tavsiya ber.', 'Farzand shaxsiy ma’lumotlarini boshqa tashxisga asoslanib baholama.'];
        }

        return ['Foydalanuvchi roli: o‘quvchi.', 'Tajvidni oddiy tushuntir, mashq rejasi va dars savollariga yordam ber.', 'Muqim diniy hukm savolida ustozga murojaat qilishni ayt.', 'AI tajweed natijasini qat’iy baho sifatida ko‘rsatma.'];
    }

    public function hasAccess(User $user): bool
    {
        if (! $user->hasRole('STUDENT')) {
            return $user->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'PARENT');
        }

        if ($user->ai_premium) {
            return true;
        }

        $score = (float) $user->studentProgress()->avg('average_score');

        return $score >= (float) config('ai.premium_score_threshold', 85);
    }
}

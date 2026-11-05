<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedLearningContent extends Command
{
    protected $signature = 'learning:seed {--author= : Author email}';

    protected $description = 'Add introductory teacher-guided lessons to the existing Quran and tajweed courses';

    public function handle(): int
    {
        $curriculum = [
            "Qur'on o'qish — Boshlang'ich" => [
                ['Qiroatga kirish va mashq tartibi', 'O‘quvchi dars va mustaqil mashq tartibini tushunadi.', 'Ustozning qisqa qiroatini tinglang. Mashq uchun tinch joy va muntazam vaqt tanlang.'],
                ['Arab harflarining shakllari', 'Harflarni tashqi ko‘rinishi bo‘yicha ajratish.', 'Ustoz bergan harflarni kartochkada taning, o‘xshash shakllarni juftlab farqini ayting.'],
                ['Harflarning talaffuzi', 'Harflarni ustoz namunasiga taqlid qilib aytish.', 'Har bir harfni alohida tinglab takrorlang. Ustoz tuzatgan harflarni qayta yozib oling.'],
                ['Fatha, kasra va damma', 'Qisqa unlilar bilan harf o‘qishni mashq qilish.', 'Ustoz tanlagan harflarni uch harakat bilan navbatma-navbat o‘qing.'],
                ['Harflarning qo‘shilishi', 'So‘z ichida harf shakllarini tanish.', 'Darslikdagi ikki va uch harfli birikmalarni avval ajratib, keyin qo‘shib o‘qing.'],
                ['Sukun bilan o‘qish', 'Sukunli harfni birikmada to‘g‘ri o‘qishga kirish.', 'Ustoz belgilagan sukunli birikmalarni shoshmasdan o‘qing va talaffuzni tekshirtiring.'],
                ['Tashdid bilan o‘qish', 'Tashdid belgisini tanish va ustoz bilan talaffuz qilish.', 'Darslikdagi tashdidli so‘zlarni ustoz namunasidan keyin takrorlang.'],
                ['Tanvinni tanish', 'Tanvin belgilarini farqlash.', 'Tanvin qatnashgan misollarni toping, ustoz bilan o‘qing va tovush farqini tinglang.'],
                ['Cho‘ziq unlilarga kirish', 'Qisqa va cho‘ziq o‘qish farqini eshitish.', 'Ustoz tanlagan juft misollarni tinglab, o‘qish davomiyligini unga moslab mashq qiling.'],
                ['Fotiha surasini ustoz bilan o‘qish', 'Fotiha matnini ishonchli mushafdan kuzatib o‘qish.', 'Qur’on bo‘limidan Fotiha surasini oching. Har oyatni ustozga alohida o‘qib bering.'],
                ['Ixlos, Falaq va Nos suralari', 'Qisqa suralarda harf va harakatlarni mustahkamlash.', 'Qur’on bo‘limidagi Ixlos, Falaq va Nos matnidan ustoz belgilagan oyatlarni o‘qing.'],
                ['Boshlang‘ich qiroat yakuniy mashqi', 'O‘tilgan belgilarni birgalikda qo‘llash.', 'Ustoz tanlagan surani mustaqil o‘qing, audio yozing va ustoz izohiga ko‘ra qayta mashq qiling.'],
            ],
            'Tajvid asoslari' => [
                ['Tajvidga kirish va tinglash odati', 'Tajvidni ustoz bilan o‘rganish usulini tushunish.', 'Ustoz qiroatini tinglang va e’tibor berish kerak bo‘lgan tovushlarni belgilang.'],
                ['Maxraj va harf sifatlariga kirish', 'Tovushlarning talaffuz joylarini farqlashga kirish.', 'Ustoz ko‘rsatgan harflarni alohida va so‘z tarkibida takrorlang.'],
                ['Nun sokin va tanvin mavzusi', 'Mushafdagi nun sokin va tanvin holatlarini tanish.', 'Ustoz tanlagan misollarni mushafdan toping. Har bir holatni ustoz eshituvida o‘qing.'],
                ['Mim sokin mavzusi', 'Mim sokin kelgan o‘rinlarda ustoz namunasi bilan ishlash.', 'Ustoz bergan oyatlardan mim sokinni ajrating va talaffuzingizni tekshirtiring.'],
                ['G‘unna mashqlari', 'G‘unnani eshitish va ustoz ko‘rsatmasi bilan mashq qilish.', 'Ustoz namunasini tinglab, belgilangan misollarni takrorlang; mustaqil qoida chiqarishga shoshilmang.'],
                ['Qalqala mashqlari', 'Qalqala mavzusiga oid o‘rinlarni tanish.', 'Ustoz tanlagan oyatlarda belgilangan harflarni o‘qing va qayd etilgan xatolarni takrorlang.'],
                ['Madd mavzusini mustahkamlash', 'Cho‘zish davomiyligini ustoz namunasi bilan taqqoslash.', 'Ustoz tanlagan madd misollarini tinglang, o‘qing va yonma-yon audio bilan taqqoslang.'],
                ['Vaqf, ibtido va yakuniy qiroat', 'To‘xtash va qayta boshlashni ustoz bilan mashq qilish.', 'Ustoz mushafda belgilagan to‘xtash joylari bilan qiroat qiling. Yakuniy audio bo‘yicha fikr oling.'],
            ],
        ];
        $author = $this->option('author') ? User::where('email', $this->option('author'))->firstOrFail() : null;
        $added = DB::transaction(function () use ($curriculum, $author): int {
            $added = 0;
            foreach ($curriculum as $courseName => $lessons) {
                $course = Course::where('name', $courseName)->first();
                if ($course === null) {
                    continue;
                }
                $module = CourseModule::firstOrCreate(['course_id' => $course->id, 'title' => 'Asosiy o‘quv dasturi'], ['sort_order' => 1]);
                foreach ($lessons as $position => [$title, $objective, $exercise]) {
                    $lesson = Lesson::firstOrCreate(['course_id' => $course->id, 'title' => $title], [
                        'course_module_id' => $module->id, 'sort_order' => $position + 1, 'duration_minutes' => 30,
                        'learning_objectives' => $objective,
                        'description' => "Ustoz rahbarligida bajariladigan dars rejasi.\n\nMaqsad: {$objective}\n\n1. Oldingi mashqni ustoz bilan takrorlang.\n2. {$exercise}\n3. Tushunmagan o‘rningizni so‘rang va ustoz tuzatgan misollarni qayta bajaring.\n\nDars yakuni: ustoz o‘zlashtirilgan ko‘nikma va keyingi mashqni belgilaydi.",
                        'homework' => $exercise.' Natijani keyingi darsda ustozga ko‘rsating.',
                        'status' => 'published', 'created_by' => $author?->id ?? $course->head_teacher_id,
                    ]);
                    $added += (int) $lesson->wasRecentlyCreated;
                }
            }

            return $added;
        });
        $this->info("{$added} ta yangi dars qo‘shildi. Mavjud darslar saqlandi.");

        return self::SUCCESS;
    }
}

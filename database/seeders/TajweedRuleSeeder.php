<?php

namespace Database\Seeders;

use App\Models\Quran\TajweedRule;
use Illuminate\Database\Seeder;

class TajweedRuleSeeder extends Seeder
{
    /** @var list<array{code: string, name_uz: string, description: string}> */
    private const RULES = [
        ['code' => 'izhar', 'name_uz' => 'Izhor (Эҳзо)', 'description' => 'Bo‘g‘izdan chiquvchi 6 harf (ء, هـ, ع, ح, غ, خ)dan keyin noon yoki sukunli noon kelganda noonni to‘liq, burunsiz aniq o‘qish. Misol: «من یعمل» (min ‘amila).'],
        ['code' => 'idgham', 'name_uz' => 'Idg‘om (Идғом)', 'description' => 'Noon yoki tanvindan keyin ی, ر, م, ل, و, ن harflaridan biri kelganda noonni keyingi harfga eritib o‘qish. Ghunnali (ی، ن، م، و) va ghunnasiz (ل، ر) turlari bor. Misol: «من یعمل» (may-yamal).'],
        ['code' => 'ikhfa', 'name_uz' => 'Ixfо (Ихфо)', 'description' => 'Noon yoki tanvindan keyin 15 harfdan biri kelganda noonni og‘iz va burun oralig‘ida yarim yashirib o‘qish. Ghunnah mavjud. Misol: «من بعد» (mim ba’d).'],
        ['code' => 'iqlab', 'name_uz' => 'Iqlоb (Иқлоб)', 'description' => 'Noon yoki tanvindan keyin ب kelganda noonni mim’ga aylantirib ghunnah bilan o‘qish. Misol: «من بعد» (mim ba’d), «سميعا بصيرا».'],
        ['code' => 'ghunnah', 'name_uz' => 'G‘unna (Ғунна)', 'description' => 'ن و م harflarining burundan chiqadigan nasals ovozi. Noon-mim mushaddadada 2 harakat davomida to‘liq ghunnah. Misol: «إنّ», «ثمّ».'],
        ['code' => 'qalqalah', 'name_uz' => 'Qalqala (Қалқала)', 'description' => 'Sukunli ق, ط, ب, ج, د harflarini o‘qishda ularning asl o‘rnidan sakrab chiqishi. Harakatli bo‘lsa kichik, sukunli bo‘lsa o‘rtacha, waqfda katta qalqala. Misol: «اقرأ», «يقطب».'],
        ['code' => 'madd', 'name_uz' => 'Madd (Мадд)', 'description' => 'Harfni cho‘zib o‘qish. Asosiy madd (alif, waw, ya) 2 harakat, madd muttasil va munfasil 4-5 harakat, lazim 6 harakat. Misol: «جاء», «الضآلین».'],
        ['code' => 'waqf', 'name_uz' => 'Waqf va ibtido (Вақф)', 'description' => 'To‘xtash va boshlash qoidalari. مٰ — majbur to‘xtash, قلی — to‘xtash afzal, صلی — boshlash afzal, ۞ juz belgisi. To‘xtashda oxirgi harf harakatlaridan ozod bo‘ladi.'],
        ['code' => 'makhraj', 'name_uz' => 'Maxraj (Махраж)', 'description' => 'Harfning chiqish joyi — 17 maxraj 5 asosiy mintaqadan: bo‘g‘iz (halqi), til ildizi, til o‘rtasi, til uchi, lablar. Harf o‘z maxrajidan boshqa joydan chiqsa talaffuz xato bo‘ladi.'],
        ['code' => 'sifat', 'name_uz' => 'Sifat ul-huruf (Сифот)', 'description' => 'Harflarning doimiy va vaqtinchalik sifatlari: hams (pichirlash), jahr (baland ovoz), shidda (qattiq), raxawa (yumshoq), isti’lo (baland), istifal (past), itboq, infisol.'],
    ];

    public function run(): void
    {
        foreach (self::RULES as $rule) {
            TajweedRule::updateOrCreate(['code' => $rule['code']], $rule);
        }
    }
}

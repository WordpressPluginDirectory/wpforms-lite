# Task 6 (Entries widget) — обʼєднане ревʼю PR #18327

**PR:** [#18327](https://github.com/awesomemotive/wpforms-plugin/pull/18327) · **Issue:** [#17955](https://github.com/awesomemotive/wpforms-plugin/issues/17955) · **Author:** @Zain-Balkhi
**Base:** `core/17787-dashboard/main` · **Head:** `core/17787-dashboard/17955-task-6-entries-widget`
**Diff:** 33 files, +4812 / −65
**Дата:** 2026-07-28

## Короткий висновок

Віджет зроблено добре: server-rendered swap, memoization, ре-валідація скоупу, документовані as-built відхилення, `@since {VERSION}` всюди, `npm run cs` зелений, PHP 7.2 / WP 5.5 сумісний. Стейти покриті майже всі, я перевірив їх у браузері.

Блокер один: схемна зміна таблиці Form Analytics не має власної міграції, вона їде на Dashboard-задачі і на Dashboard-прапорці. Для клієнта на новій версії індекс усе ж створиться, тому це не «індекса не буде», а проблема власності та звʼязності.

Найзмістовніше з решти: gear-меню зроблено не за каноном налаштувань фреймворку, і через це PR довелося правити спільний шаблон.

Далі за важливістю. Кожна знахідка має позначку джерела: **[A]** моє, **[B]** другого ревʼювера, **[A+B]** знайшли обидва.

---

# P0. Блокер

## 1. [A+B] Індекс у таблиці Form Analytics не має власної міграції

**Файли:** `wpforms/src/Pro/Db/Analytics/Fields.php:56`, `wpforms/src/Pro/Tasks/DashboardBackfillTask.php:279-288`, `wpforms/src/Pro/Migrations/Upgrade2_0_1.php:134`

У PR два механізми додавання `form_period`, і жоден з них не є міграцією тієї сутності, чия схема змінилася.

**1. `CREATE TABLE` (`Fields.php:56`) працює тільки для свіжих таблиць.**
`Helpers\DB::create_custom_tables():270` робить `continue` на кожній таблиці, яка вже існує, тому dbDelta на апгрейді не переганяється:

```php
foreach ( $custom_tables as $table_name => $handler_class ) {
    if ( in_array( $wpdb->prefix . $table_name, $existing_tables, true ) ) {
        continue;   // існуючі таблиці не проходять через create_table()
    }
```

`wpforms_analytics_fields` зʼявилася в 2.0.0 (реліз 2026-07-14), тому в усіх існуючих клієнтів таблиця вже є і ця гілка для них мертва.

**2. Реально індекс створює `DashboardBackfillTask::build_indexes():279-288`,** який кличеться з `Upgrade2_0_1::build_indexes_inline():134` та з async-дрейну.

Для клієнта на новій версії це **працює**: на апгрейді 2.0.0 → 2.0.1 `is_cold_start_complete()` дає false, `build_indexes_inline()` виконується, усі пʼять індексів будуються. Тобто формулювання «індекс не створиться» неточне.

**Проблема в іншому: схемна зміна Form Analytics тепер належить Dashboard-у.**

* `wpforms_analytics_fields` це таблиця Form Analytics (`Pro\Db\Analytics\Fields`, `@since 2.0.0`).
* Її індекс створює Dashboard-задача, під Dashboard-прапорцем `wpforms_dashboard_rollup_indexes_built` і Dashboard-лічильником `wpforms_dashboard_index_build_failures`.
* Сам PR у коментарі визнає, що індекс «also serves Form Analytics' own date-ranged field reads», тобто він потрібен не лише дашборду. Це робить звʼязність гіршою, не кращою.
* Два механізми розходяться в тому, що вважають правдою: `Fields::create_table()` заявляє, що індекс є частиною схеми, а на апгрейдженому сайті його наявність залежить від того, чи відпрацювала Dashboard-задача.
* Зворотний бік тієї ж монети це №3: `build_indexes()` вимагає успіху всіх пʼяти індексів, щоб виставити прапорець, тому відсутня FA-таблиця блокує **Dashboard-**прапорець.

**Що зробити:** `CREATE TABLE` залишити (для свіжих інсталяцій коректно), а створення індексу для існуючих винести з `build_indexes()` в явний крок міграції тієї версії, в якій виходить епік, не через rollup-index бухгалтерію дашборду. `Upgrade2_0_1` вже є міграцією епіка, тому мінімальна правка це окремий крок у ній, а не рядок у `build_indexes()`.

**Побічне зауваження, не про клієнтів.** Прапорець це грубий boolean без версійності, тому на сайті, де він уже стоїть, індекс не добудується. Перевірив на цьому сайті:

```sql
wpforms_versions → "2.0.1" => 1784119515        -- Base::migrate():172 скіпає Upgrade2_0_1
wpforms_dashboard_rollup_backfill_complete = 1  -- kick():73 і reschedule():96 виходять одразу
wpforms_dashboard_rollup_indexes_built = 1
-- wp_wpforms_analytics_fields: тільки PRIMARY, form_period відсутній
```

Тобто після завершення бекфілу не залишається жодного шляху, який дійшов би до `build_indexes()`. Для клієнтів це не проблема, бо вони приходять на нову версію з чистими опціями, а внутрішні сайти ми мігруємо вручну. Але якщо ми колись додамо ще один індекс у вже випущеній версії, це повториться, тому версійність прапорця варто закласти зараз.

---

# P1. Високий приоритет

## 2. [A+B] Gear-меню зроблено не за каноном налаштувань фреймворку

**Файли:** `wpforms/src/Admin/Dashboard/Widgets/Entries.php:606`, `wpforms/templates/admin/dashboard/widget-settings.php:47` і `:69`, `wpforms/assets/scss/admin/dashboard/_framework.scss:146`

У фреймворку вже є все, що потрібно для цього меню, і два еталонні віджети показують однакову форму схеми. Кожне поле має `name`, підписані поля мають `label`, а групові та списочні ще й `panel => true`:

```text
// Pro\Admin\Dashboard\Widgets\Locations::get_settings_schema():251-271
[ 'type' => 'select',    'name' => 'number_of_countries', 'label' => __( 'Number of Countries', … ) ]
[ 'type' => 'checklist', 'name' => 'excluded',            'label' => __( 'Exclude', … ), 'panel' => true ]

// Admin\Dashboard\Widgets\Payments::get_settings_schema():192-222
[ 'type' => 'checkboxes', 'name' => 'display',            'label' => __( 'Display Options', … ), 'panel' => true ]
[ 'type' => 'checklist',  'name' => 'cards',              'label' => __( 'Stat Cards', … ),      'panel' => true ]
[ 'type' => 'select',     'name' => 'number_of_payments', 'label' => __( 'Number of Payments', … ) ]
```

Entries відходить від цього в одному полі:

```text
// Admin\Dashboard\Widgets\Entries::get_settings_schema():606
[ 'type' => 'checkboxes', 'options' => [ 'graph' => … ] ]       // немає label і panel, і немає name
[ 'type' => 'select',     'name' => 'count', 'label' => … ]     // ок
[ 'type' => 'checklist',  'name' => 'forms', 'panel' => true ]  // ок, за Figma список без лейбла
```

(`name` у гілці `checkboxes` шаблон не використовує, імена інпутів беруться з `$option_key`, тому це лише консистентність з Payments, не функціональність.)

Наслідки, від найважливішого:

1. **Немає заголовка «Display Options» і сірої панелі під `Display Graph`.** В `figma_exports/Menus & Options.png` заголовок є, а чекбокс стоїть у світло-сірому rounded-контейнері (перевірив кропом, це не Figma-виділення). Фікс це буквально два ключі масиву, бо `_framework.scss:160-164` уже стилізує `&-group.wpforms-dashboard-widget-settings-panel` рівно так, як показує Figma:

   ```text
   [ 'type'  => 'checkboxes',
     'label' => __( 'Display Options', 'wpforms-lite' ),   // додати
     'panel' => true,                                       // додати
     'options' => [ 'graph' => __( 'Display Graph', … ) ],
     'value'   => [ 'graph' => $settings['graph'] ] ]
   ```

   SCSS правити не треба.

2. **Список не є скрол-контейнером.** У `<ul class="…-settings-list">` computed стилі виходять `max-height: none` / `overflow-y: visible`, тому скролиться весь поповер (`overflow-y: auto`, 539px видимих із 2955px при 96 формах). `Display Graph`, `Number of Forms` і `Save Changes` виїжджають разом зі списком. Figma показує інакше: список обмежений і обрізаний посередині елемента (`Job Application - Support Spe...`), а `Save Changes` зафіксований під ним.

**Що в цьому пункті НЕ є проблемою** (перевірив після уточнення по Figma, спершу я записав це як дефекти помилково):

* **Список форм без лейбла це правильно.** Figma показує його без підпису, на відміну від Locations з його «Exclude». Тобто `checklist` без `label` і з `panel => true` зроблено коректно.
* **Правка спільного шаблону правильна і має залишитися.** Гілка `checkboxes` **вже мала** `if ( ! empty( $field['label'] ) )` у base-гілці (`git show origin/core/17787-dashboard/main:…/widget-settings.php`). Єдина правка PR це та сама умова в гілці `checklist`, і вона потрібна: без неї базовий шаблон рендерив би порожній `<p class="…-label"></p>` і давав зайвий вертикальний відступ під списком форм. Регресії для Payments і Locations немає, бо вони свої лейбли передають.

   Уточнення, щоб не завищувати: `Save Changes` **досяжний**, я це перевірив, просто далеко. Скрол поповера до кінця його показує, це ~46 тіків колеса при 96 формах:

   ```js
   { popoverOverflowY: "auto", scrollTop: 2416, scrollHeight: 2955,
     clientHeight: 539, saveVisibleInsidePopover: true, ticksNeededApprox: 46 }
   ```

   У фреймворку немає стелі висоти, бо жодному консюмеру вона не була потрібна на 6 і 10 елементах. Entries з N = всі форми сайту це перший консюмер, якому вона потрібна, тому стеля належить у `_framework.scss` поруч з наявним `-panel` правилом, а не як per-widget оверрайд:

   ```scss
   &-list.wpforms-dashboard-widget-settings-panel {
       max-height: 220px;
       overflow-y: auto;
   }
   ```

   Плюс варто зафіксувати `.wpforms-dashboard-widget-settings-footer`, щоб кнопка завжди була видна.

Загальна рекомендація: привести схему Entries до форми Payments і Locations, після чого правки шаблону відкатати.

## 3. [A] `build_indexes()` чіпає analytics-таблицю без `tables_exist()` guard

**Файл:** `wpforms/src/Pro/Tasks/DashboardBackfillTask.php:279`

Зворотний бік звʼязності з №1: там FA-схема залежить від Dashboard-прапорця, а тут Dashboard-прапорець залежить від наявності FA-таблиці.

`RollupRepository::tables_exist():139` перевіряє лише три rollup-таблиці. `wpforms_analytics_fields` може легально відсутня, саме для цього існує `AnalyticsDB::tables_exist()` (див. `Loader.php:1173`). На такому сайті:

* `index_exists()` виконує `SHOW INDEX FROM wp_wpforms_analytics_fields` **до** `suppress_errors( true )`, тому помилка не приглушується і `print_error()` може вивести HTML у відповідь AJAX / Action Scheduler runner;
* `INDEXES_BUILT_OPTION` не виставиться, навіть якщо решта чотири індекси побудовані;
* `wpforms_log( 'Dashboard index build failed' )` спрацьовує на кожній спробі, поки лічильник не дійде до 3.

Треба обгорнути новий індекс у `AnalyticsDB::tables_exist()` і виключити його з умови built-прапорця, коли таблиці немає.

## 4. [B] Trashed або видалена форма повертається в таблицю через збережені settings

**Файли:** `wpforms/src/Admin/Dashboard/Widgets/Entries.php:665`, `:812`, `:964`

`get_resolved_settings():665` проганяє збережені ID лише через `absint()`, без перетину з published-формами. Якщо вибрана форма зникає зі superset, `resolve_table_rows():812` трактує її як «форма без entries» і zero-fill-ить, а `get_zero_filled_row():964` бере назву через `get_the_title()` напряму.

Наслідки:

* **trashed форма** далі рендериться в таблиці з живою назвою і робочими лінками на builder та entries page;
* **permanently deleted** дає рядок з порожньою назвою і лінком на `wpforms-builder&form_id=<gone>`.

`get_form_choices():640` віддає лише `publish` (дефолт `WPForms_Form_Handler::get()` це `post_status => 'publish'`, `class-form.php:238`), тому нову trashed форму вибрати не можна. Проблема саме в тому, що вибір **залишається** в user meta після зміни статусу.

**Уточнення до [B] щодо Graph AJAX:** там перевірка вже є, додаткова не потрібна. `EntriesCount::get_by_date_sql():113` → `get_allowed_forms():449` вимагає `get_post_status( $form_id ) === 'publish'` для ненульового `form_id`, тому trashed форма повертає `[]`. Скоуп теж не може «застрягнути» на такій формі: `Pro\Entries::get_active_form_id():204` вимагає `entries > 0`, а zero-filled рядок має `0`, і шаблон для нього взагалі не рендерить Graph-кнопки. Тобто це чисто проблема відображення таблиці.

**Що зробити:** перетнути resolved selection з published form IDs. `Cache::get_published_form_ids()` вже існує (`protected`), або через form handler.

## 5. [A] `top_forms( $start, $end, 0 )` робить кешований superset необмеженим

**Файл:** `wpforms/src/Pro/Admin/Dashboard/Cache.php:296`

Те саме стосується `get_by_form_sql()` на `:321` (його дефолт `limit` це 0, тобто без ліміту) і Lite-шляху, де `Lite\Reports\EntriesCount::get_by_form()` віддає всі форми.

На сайті з сотнями форм це означає: сотні рядків у transient, `_prime_post_caches()` на всі з них, і `form_id IN ( %d, %d, … )` з одним placeholder на форму одразу в двох запитах (`get_views_by_form()` і `get_interactions_by_form()`). Віджет рендерить максимум 10 рядків.

Причина зняття ліміту зрозуміла: gear-вибір має резолвитися проти superset. Але зараз superset не обмежений нічим. Пропоную стелю (умовно кілька сотень) плюс `log()` або хоча б коментар про відсічення.

## 6. [A+B] Site-wide option проти per-user meta для first-visit anchor

**Файли:** `wpforms/src/Admin/Dashboard/Widgets/Entries.php:71`, `wpforms/src/Admin/Dashboard/Page.php:530`

Код використовує `get_option( 'wpforms_dashboard_first_visit' )`. Наша ж документація каже інше:

* `task-6-entries-widget-spec.md` §2.7, доданий у цьому самому PR: *"The first-visit timestamp is **per-user meta**, written once in `Page::output()`"*;
* `widgets/widget-entries.md` §3.5: *"15 days after **the user's** first visit"*.

Docblock константи аргументує option свідомо, і це може бути правильне рішення. Але тоді §2.7 і widget md треба поправити в цьому ж PR. Зараз код і спека прямо суперечать одне одному.

Практичний наслідок [B] слушний: другий адміністратор, який зайде на дашборд пізніше, побачить promo одразу, бо годинник site-wide. Це питання до product, не до коду.

На цьому сайті опція виставилася коректно під час мого тесту: `wpforms_dashboard_first_visit = 1785243919`.

---

# P2. Середній приоритет

## 7. [B] Race condition у Graph AJAX може показати і зберегти не ту форму або дату

**Файли:** `wpforms/assets/js/admin/dashboard/modules/widget-entries.js:601` (`applyScope`), `:634` (`resetScope`), `:195` (`swapWidget`), `wpforms/src/Pro/Admin/Dashboard/Ajax.php:120`

Ні `applyScope()`, ні `resetScope()` не мають abort або revision guard і безумовно застосовують кожну відповідь. Паралельно `swapWidget()` підміняє DOM разом з актуальним діапазоном.

Сценарії:

* швидко натиснути Graph для A, потім для B: пізня відповідь A перемагає;
* змінити date range, поки Graph-запит у польоті: старі дані малюються на новому canvas;
* `resetScope` і `applyScope` можуть завершитися у зворотному порядку.

Сервер зберігає selection у межах обробки запиту (`Ajax.php:120` рахує графік, `:122` пише `save_active_form_id()`), тому порядок запису визначається порядком завершення запитів, а не порядком кліків. Через це ігнорування stale-відповіді лише на клієнті недостатньо.

**Що зробити:** тримати `jqXHR` останнього запиту і абортити попередній, або нумерувати запити і застосовувати тільки останній, включно з тим, що надсилається на сервер.

Найправдоподібніший з трьох сценаріїв це зміна діапазону під час запиту, не подвійний клік.

## 8. [A] Локед-клітинка не має dashboard-атрибуції в upgrade-лінку

**Файл:** `wpforms/templates/admin/dashboard/widgets/entries-table.php:28`

`$render_locked_cell` не віддає `data-utm-medium` і `data-utm-content`, тому:

* `getUTMContentValue()` в `education/core.js:274` падає у фолбек на `data-name`, і `utm_content` стає «Form Analytics» замість `analytics-upgrade`;
* `utm_medium` залишається базовим, тобто апгрейд з дашборда не відрізнити від Forms Overview, який ставить `forms-overview`.

Фікс цілком у dashboard-шаблоні, два атрибути. Значення для `utm_medium` варто узгодити з контент-командою, бо в чеклисті PR не відмічено «The URLs introduced in PR have been reviewed by the content team».

**Свідомо не просимо переюзати `Analytics::render_pro_badge():328`,** хоча він генерує ту саму розмітку для тих самих двох тултипів і спека §2.4 це радила. Він `private` у `src/Admin/Forms/Analytics.php`, тобто поза скоупом дашбоарда, і зміна видимості чіпала б код, що вийшов у 2.0.0. Залишаємо дублювання як борг, окремим follow-up.

## 9. [A] Два дублювання, які варто злити

* `Cache::get_views_by_form()` (`Admin/Dashboard/Cache.php:347`) повторює весь prepare/placeholder блок з `get_total_views():305`, а `Pro\Cache::get_interactions_by_form():359` повторює його третій раз. Один private helper з select-клаузою покриє всі три. Усі три в dashboard-файлах.
* `placeholderSeries()` (`widget-entries.js:392`) це `getPlaceholderData()` з `widget-payments.js:409` з тими самими трьома константами і тим самим `Math.random()`. Місце для цього спільний dashboard app. Чіпає модуль Task 7, тому кандидат на окремий follow-up, а не на цей PR.

**Знято зі списку:** дубльований список Pro-тірів (`ANALYTICS_TIERS` на `Pro\...\Entries:30` і той самий літерал inline на `Entries:439`). Знахідка валідна, `$access->get_tier()` це справді `wpforms_get_license_type()`, тому `ProAnalytics::is_allowed()` покривав би Pro-гілку. Але цей літерал уже повторюється у кодбейсі близько восьми разів, тобто це наша загальна практика, а не дефект цього PR. Не витрачаємо на це раунд ревʼю.

## 10. [A] `has_published_forms()` робить raw-запит по `$wpdb->posts` з некорректним обґрунтуванням

**Файл:** `wpforms/src/Admin/Dashboard/Widgets/Entries.php:143`

Docblock каже, що стейт резолвиться *"during service registration, before `init` runs"*. Насправді всі шляхи до `get_state()` виконуються після `init`: `Page::enqueues()` і `get_script_dependencies()` на `admin_enqueue_scripts`, `WidgetPipeline` на `wpforms_admin_page`, `render_for_range()` у `wp_ajax_*`. Тобто post type зареєстрований і handler доступний.

Це також суперечить `get_form_choices():640`, який свідомо йде через `wpforms()->obj( 'form' )` *"so access-control and multilingual filters apply"*. Два різні способи пошуку форм з протилежними аргументами в одному класі.

Пропоную `wpforms()->obj( 'form' )->get( '', [ 'fields' => 'ids', 'posts_per_page' => 1 ] )` або переюз `Cache::get_published_form_ids()`.

## 11. [A] Pro-only методи викликаються на базовому типі

**Файл:** `wpforms/src/Pro/Admin/Dashboard/Ajax.php:120`, `:122`, `:150`

`get_entries_widget()` оголошений `: ?Entries` проти Lite-бази (`Admin/Dashboard/Ajax.php`), але викликаються `build_form_graph()` і `save_active_form_id()`, які існують лише в `Pro\...\Widgets\Entries` (`:283` і `:222`). Працює через FQCN-резолюцію, я підтвердив re-scope у браузері. Але статичний аналіз цього не бачить, і будь-який фільтр, що віддасть базовий клас, дасть fatal. Треба оверрайдити резолвер у `Pro\Ajax` з Pro-типом.

## 12. [A] На Lite з вимкненим Lite Connect форми з views і без submissions не показуються

**Файл:** `wpforms/src/Lite/Admin/Dashboard/Cache.php:36`

Набір рядків приходить з батьківського `get_entries_by_form()`, а `Lite\Reports\EntriesCount::get_by_form():46` скіпає будь-яку форму з порожньою lifetime-метою `wpforms_entries_count`. Далі `zero_form_counts()` занулює те, що залишилося.

Тобто Lite-сайт, чиї форми мають views з Form Analytics але не мають submissions, побачить сірий рядок «No entries yet.» замість таблиці з Views. Це суперечить `widget-entries.md` §4.4 (*"only **Views** is populated (from Form Analytics)"*) і §5. У цьому стейті сортування рядків теж довільне, бо всі `count` нульові.

Автор **уже зробив рівно такий фолбек для LC-ON гілки**: `map_lc_forms()` резолвить назву через `get_the_title()` для LC-форм з нульовим локальним лічильником, з коментарем *"which the parent's `EntriesCount::get_by_form()` excludes"* (`Lite/Admin/Dashboard/Cache.php:144-147`). Тобто половину задачі вирішено, а LC-OFF гілка залишилася. Це неузгодженість у межах одного файлу.

**Фікс належить у `Lite\Admin\Dashboard\Cache`, не в джерелі.** `Lite/Reports/EntriesCount::get_by_form()` має інших консюмерів, зокрема щотижневий summary-емейл Lite (`Lite/Emails/Summaries.php:99`), тому прибирати там `if ( empty( $count ) ) continue;` не можна.

Застереження: Lite-білд я в браузері не перевіряв, знахідка з читання коду. Варто підтвердити на Lite перед фіксом.

## 13. [A] На Lite-шляху enrichment виконується двічі

**Файли:** `wpforms/src/Admin/Dashboard/Cache.php:222`, `wpforms/src/Lite/Admin/Dashboard/Cache.php:53`

Батьківський `get_aggregates()` тепер кличе `enrich_forms_with_analytics()`, і Lite-оверрайд кличе його ще раз на LC-змапленних рядках. Два лишні запити на кожен compute кешу, коли LC-статистика фетчиться.

---

# P3. Дрібниці та полірування

## 14. [A] Застарілий коментар у `_framework.scss`

`wpforms/assets/scss/admin/dashboard/_framework.scss:181` каже *"then let WP core paint the checked mark so it renders correctly on every WP version"*, а новий `&::before` mask існує саме тому, що глиф core невидимий на пофарбованому боксі. Треба поправити текст.

Окремо для QA: mask перефарбовує чекбокс у **кожному** gear-поповері, тому Payments і Locations потребують візуальної перевірки. Entries я перевірив, галочка малюється правильно.

## 15. [A] `get_views_by_form()` хардкодить імʼя таблиці

`wpforms/src/Admin/Dashboard/Cache.php:347` пише `$wpdb->prefix . 'wpforms_analytics_forms'`, хоча `AnalyticsDB::forms_table()` існує (`Db/Analytics/DB.php:62`). Непослідовно з Pro-сиблінгом у цьому ж PR, який використовує `AnalyticsFieldsDB::fields_table()`. (`get_total_views()` має ту саму проблему, було б добре поправити обидва.)

## 16. [A] `DateTimeImmutable` у PHPDoc без `use`

`wpforms/src/Admin/Dashboard/Widgets/Entries.php:930` посилається на `DateTimeImmutable` у `@type`, але імпорту немає, тому для тулінгу тип резолвиться як `WPForms\Admin\Dashboard\Widgets\DateTimeImmutable`. Pro-сабклас імпортує коректно.

## 17. [A] Тест тихо пропускає половину асертів

`wpforms/tests/integration/Pro/Admin/Dashboard/CacheTest.php:381`: `if ( ! AnalyticsDB::tables_exist() ) { return; }` робить тест зеленим з невиконаною змістовною частиною. Правильна форма це `markTestSkipped()`. Плюс `interactions` асертиться тільки як `0`, тому новий `get_interactions_by_form()` не має покриття на ненульовому шляху.

## 18. [A] Лейбл графіка бере Lite-домен і вже проескейплений

`wpforms/src/Admin/Dashboard/Page.php:330`: `'entries' => esc_html__( 'Entries', 'wpforms-lite' )` йде в `label:` у `widget-entries.js:340`. На Pro тайтл віджета приходить з домену `wpforms`, тому в перекладеному Pro-білді лейбл тултипа і заголовок картки можуть розійтися. А `esc_html__` пропустить HTML-ентіті в Chart.js тултип.

## 19. [A] `is_entries_widget_visible()` не може бути false

`wpforms/src/Admin/Dashboard/Page.php:444`: `Entries::get_state()` хардкодить `new WidgetState( true, … )`, тому всі три call site фактично константні, а memoized `has_published_forms()` запит виконується тільки щоб бути відкинутим. Або спростити, або додати коментар, що це forward-looking seam.

## 20. [A] Placeholder порожнього діапазону завжди на 30 днів

`wpforms/assets/js/admin/dashboard/modules/widget-entries.js:393`: `PLACEHOLDER_DAYS = 30`, тому вісь показує Jul 7 … Jul 19, поки пікер каже «Last 7 days». Дзеркалить Payments, тобто послідовно, але варто підтвердити з дизайном.

## 21. [A] Дублікати назв форм у чеклисті

Gear-чеклист рендерить однакові назви без нічого, що їх відрізняє. На тестовому сайті є дві «Simple Contact Form» (ID 33 і 527) і три «Job Application». Я сам на це впіймався під час тестування: вибрав не ту форму і спершу вирішив, що це баг у zero-fill. Можливо, додавати ID, коли назва повторюється.

## 22. [A] Три виклики `wpforms_is_admin_page( 'dashboard' )` поруч з `is_dashboard()`

`wpforms/src/Lite/Admin/Education/LiteConnect.php:52`, `:136`, `:205`. При цьому `is_dashboard():101` означає **WP**-дашборд (`index.php`). Два різних «дашборди» в одному класі читаються погано. Невеликий private helper з чіткою назвою допоміг би.

## 23. [A] Дрібні непослідовності з еталонними віджетами

* `Entries.php:640` `get_form_choices()` не має null-guard на `wpforms()->obj( 'form' )`, на відміну від `Payments::get_recent_payments()`.
* `entries-empty.php:47` міг би прогнати `$button['classes']` через `wpforms_sanitize_classes()`, як це робить `widget.php:34`.

(Відсутній `name` на `checkboxes` перенесено в №2, бо це частина того самого відхилення від канону.)

## 24. [A] CHANGELOG.md правиться в PR

Запис доданий у `wpforms/CHANGELOG.md` в unreleased-блок 2.0.1, і той самий текст є в описі PR. Варто підтвердити з лідом, як ми хочемо тут, бо CHANGELOG зазвичай зводять на релізі.

---

# Перевірено як коректне

## Що я підтвердив у браузері

Elite, `https://wpforms.test`, гілка `core/17787-dashboard/17955-task-6-entries-widget`:

* data-стейт: графік + таблиця, 6 колонок, лінки на builder / entries / analytics;
* re-scope на форму, персистентність після релоаду, reset назад на site-wide;
* зміна діапазону на такий, де в заскоупленої форми немає entries: графік коректно падає на site-wide, стан рядка чиститься;
* порожній діапазон: no-data нотис над faint placeholder-серією плюс сірий рядок «No entries yet.»;
* `Display Graph` off: графік і колонка Graph зникають разом, таблиця і gear залишаються;
* вибір форм: сортування за entries desc (5, 1, 0), zero-filled рядок для форми без entries, але з реальними Views 2 / Interactions 2 з аналітики, і без Graph-кнопок;
* carry-forward `active_form_id` через gear-save (перевірив і на graph off → on);
* `Save Changes` у gear-поповері досяжний скролом до кінця, кнопка працює (деталі в №2);
* галочка чекбокса після mask-фіксу малюється правильно;
* консоль без помилок, `wp-content/debug.log` без WPForms-нотисів;
* `npm run cs` (`lint-my-diff`) зелений: 0 PHPCS, 0 ESLint.

## Знахідки, які виявилися false positive

* **Dismissal key.** [B] правильно каже, що AI-коментар про неправильний ключ це false positive. Я перевірив незалежно: `data-section="dashboard-abandonment-promo"` (`entries-promo.php:51`) → `Education\Core::ajax_dismiss():116` пише `$dismissed[ 'edu-' . $section ]`, тобто рівно `edu-dashboard-abandonment-promo`, що й читає `get_abandonment_promo():428`.
* **Валідація form_id у Graph AJAX.** Додаткова перевірка не потрібна, деталі в №4.
* **Клас `wpforms-hidden` у Lite Connect шаблоні.** Визначений у `assets/css/admin.css`, який завантажується на всіх WPForms admin-сторінках. Працює.
* **Enqueue tooltipster.** Handle `tooltipster` і версія `4.2.6` збігаються з конвенцією кодбейсу байт у байт (`Admin/Challenge.php:196-211`), включно з `null` для style-залежностей.

## Сумісність і поділ Lite/Pro

* **PHP 7.2 / WP 5.5:** сумісно. Немає 7.4+ синтаксису (перевірив на `fn()`, `??=`, spread у літералах, typed properties, `match`, `str_contains`). `private const` це 7.1, короткий list-destructuring 7.1, `_prime_post_caches()` коректно під `function_exists()`.
* **Lite/Pro поділ:** правильний, погоджуюся з [B]. Спільний render/state layer у Lite-safe коді, entries/analytics AJAX та interaction data у Pro. Посилання на `WPForms\Lite\Integrations\LiteConnect\*` з core-класу мають прецедент (`Admin/Dashboard/Cache.php:12`) і захищені early-return на `wpforms()->is_pro()` раніше за будь-який дотик до цих класів, тому fatal на Pro неможливий.
* **Вплив на інші місця:** frontend і form builder не зачеплені, погоджуюся. Але три shared-зміни варто тримати в полі зору на QA: правки `widget-settings.php` (№2), чекбокси `_framework.scss` у **всіх** gear-поповерах (№14) і `Lite\Admin\Education\LiteConnect::allow_load()`, який тепер вантажить education-клас на новій dashboard-сторінці.
* **Міграція:** `Fields::create_table()` для нових інсталяцій, `DashboardBackfillTask` для існуючих, під тим самим імʼям індексу. Для клієнта на новій версії індекс створюється, розриву немає. Але окремої міграції під схемну зміну FA-таблиці в PR немає, деталі в №1.

## Що я не перевіряв у браузері

* **Lite-білд** (тайтл «Forms», «Entries Backed Up», відсутність колонки Graph, Lite Connect on/off, локи Interactions/Conversion). Вимагає переключення ліцензії, робив статично. №12 знайдено читанням коду, не в браузері.
* **Empty state «No Forms»**: на сайті 96 published форм, без деструктивних дій не відтворити.
* **Form Abandonment promo**: addon активний, тому `get_abandonment_promo()` коректно віддає `[]`. Сам рендер promo не бачив.
* **RTL і кросбраузерність.**
* Твердження [B] про падаючий ізольований інтеграційний тест index phase я не відтворював, але сама причина підтверджена SQL-запитом у №1.

---

# Метадані issue

| Issue | PR | Assignees | Labels | Estimate | Current Sprint |
| --- | --- | --- | --- | --- | --- |
| [#17955](https://github.com/awesomemotive/wpforms-plugin/issues/17955) | [#18327](https://github.com/awesomemotive/wpforms-plugin/pull/18327) | ✅ Assignees: Zain-Balkhi | ❌ Missing: priority:* risk:* lite | ❌ Missing API key | ❌ Missing API key |

---

# Що варто відзначити як добре зроблене

* Server-rendered swap як контракт це правильне рішення для цього віджета. `Cache::compute()`, що штампує `range_start` / `range_end`, плюс `get_range_dates()`, який окремо обробляє transient без штампів, це акуратна робота. Кейс з порожнім рядком легко пропустити, і він дав би today-only діапазон.
* `Pro\Entries::get_active_form_id()`, що ре-валідує збережений скоуп проти відображених рядків, тому в honored-скоупа завжди є видимий reset, це справді хороший інваріант. Фолбек підтвердив у браузері.
* Переюз там, де він важливий: `wpforms_panel_field_toggle_control()` для LC-тоглу, спільний партіал `admin/addons/install-link` разом з `wpforms-addon-tile__link` для promo CTA, `wpforms_dismissed` для дисмісу.
* `@since {VERSION}` коректний всюди, кожен `phpcs:ignore` має причину.
* As-built нотатки в `architecture.md`, включно з документованим відхиленням `role="img"` від `aria-hidden`-гайдлайну, це саме те, що економить наступному розробнику годину.

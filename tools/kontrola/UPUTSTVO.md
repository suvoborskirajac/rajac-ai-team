# Контролна скрипта — упутство

Скрипта само чита јавне странице сајта и пописује грешке. Ништа не мења, не брише и не шаље ван сервера.

## Постављање (cPanel → File Manager)

1. Отвори cPanel → File Manager и уђи у `public_html`.
2. Отпреми ZIP `kontrola-v1.0.zip` и **распакуј у `public_html/`**. После распакивања мора да постоји тачно овај фајл:
   `public_html/kontrola/kontrola.php`
3. Обриши ZIP из `public_html/`.

## Покретање

4. Отвори у прегледачу:
   https://piorajac.rs/kontrola/kontrola.php?token=rajac-kontrola-7q4m2x9w
5. Страница се сама освежава („Читам странице… 240 / 780“). Не затварај прозор док не пише „Контрола завршена“.
6. Отвори извештај: https://piorajac.rs/kontrola/rezultati/izvestaj.html
7. Преузми и пошаљи Claude-у:
   - https://piorajac.rs/kontrola/rezultati/kontrola.json
   - https://piorajac.rs/kontrola/rezultati/stranice.csv

## Поново или брисање

- Ново читање од почетка: https://piorajac.rs/kontrola/kontrola.php?token=rajac-kontrola-7q4m2x9w&reset=1
- Брисање резултата: https://piorajac.rs/kontrola/kontrola.php?token=rajac-kontrola-7q4m2x9w&obrisi=1

## Шта се не чита

Фолдери: ris, baza, kopindeksi, monitoring-izvestaji, inventar, kontrola, wp-content, wp-admin, wp-includes, cgi-bin, node_modules, vendor, cache, tmp, logs, backup, backups, _arhiva, _old, old, staro, _seo_backup и сваки фолдер са `.bak` у имену. Фајлови: све што није `.html` или `.htm`, и копије `.bak`, `.old`, `~`.

## Провере

1. Речи у којима се мешају ћирилична и латинична слова
2. Латиничне речи на ћириличним страницама (ван курзива, кода и линкова; дозвољени изрази су на списку у скрипти)
3. Фонтови
4. Варијанте главног менија
5. Линк ка почетној ПИО „Рајац“ у заглављу
6. target="_blank"
7. Мртви унутрашњи линкови
8. Слике са туђих адреса и слике без описа
9. Наслов, h1, viewport, canonical, meta опис

## Проба пре објаве

Испробано на пробном сајту са подметнутим грешкама (PHP 8.3): пронађена је свака подметнута грешка и ниједна лажна; обрада у серијама, поновно покретање и брисање раде.

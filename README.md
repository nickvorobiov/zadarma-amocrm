# Руководство по внедрению скрипта 1

## Назначение

Скрипт script1 предназначен для получения данных о звонках из Zadarma и передачи их в AMOcrm.

## Содержимое

script1
 |--buffer – буфер для хранения полученных, но не отправленных записей
 |--result — буфер для хранения успешно отправленных записей
 |--vendor — папка с библиотеками
 |   |--composer — менеджер пакетов
 |   |--raveren – отладочная библиотека (для работы не требуется)
 |   |--zadarma — стандартная библиотека zadarma
 |   |--autoload.php
 |
 |--composer.json — файл менеджера пакетов
 |--composer.lock — файл менеджера пакетов
 |--cookie.txt — временный файл с полученными от AMO печеньками
 |--main.php — главный исполняемый скрипт

## Инсталляция

1. Положите папку script1 в /opt/script1 на сервере
2. Откройте редактор расписания cron командой

```
crontab -e
```

3. Добавьте строку

```
* * * * * /usr/bin/php /opt/script1/main.php >> /opt/script1/logfile.txt
```

4. Сохраните файл
5. Раз в 1 минуту скрипт будет обращаться к Zadarma, получать данные о звонках, и отправлять их в AMOcrm

# Руководство по внедрению скрипта 2

## Назначение

Скрипт script2 предназначен для получения номера телефона по HTTP GET/POST и его отправки в AMOcrm

## Содержимое

script1
 |--cookie.txt — временный файл с полученными от AMO печеньками
 |--order.php — главный исполняемый скрипт
 |--index.html – тестовая страница с формой

## Инсталляция

1. Положите папку script2 в папку на вашем веб-сервере. Например, если у вас linux/apache, то это будет скорее всего /var/www/script2. Если у вас другая система, позовите вашего системного администратора, он всё сделает.
2. Предоставьте скрипту доступ к записи в папку /var/www/script2. Это необходимо для записи в файл cookie.txt:

```
chmod a+w /var/www/script2
chmod a+w /var/www/script2/cookie.txt
```

3. Откройте http://[вашСервер]/script2/index.html
4. Введите телефон в форму, нажмите кнопку. Введённый телефон будет отправлен в AMOcrm.
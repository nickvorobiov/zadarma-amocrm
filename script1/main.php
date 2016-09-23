<?php

// Переменные, которые ты можешь настроить на свой вкус

// Данные для доступа в Zadarma
$zadarmaKey = '***';
$zadarmaSecret = '***';

// Данные для доступа в AMO
// Боевые данные
$amoLogin = '***'; // Ваш логин (электронная почта)
$amoSubdomain = '***'; // http://???.amocrm.ru
$amoKey = '***'; // Хэш для доступа к API (см. в профиле пользователя)

// Папка для временного хранения вытащенных из Zadarma, но ещё не засунутых в AMO записей
$bufferFolder = __DIR__ . '/buffer';

// Папка для постоянного хранения успешно засунутых в AMO записей
$resultFolder = __DIR__ . '/result';

// Временная зона для отчёта
date_default_timezone_set('Europe/Moscow');

/////////////////////////////////////////////////////
///////// Дальше ты не хочешь ничего менять /////////
/////////////////////////////////////////////////////

require __DIR__ . '/vendor/autoload.php';

// Создаём папки для хранения записей

if (!file_exists($bufferFolder)) {
  mkdir($bufferFolder, 0777);
}
if (!file_exists($resultFolder)) {
  mkdir($resultFolder, 0777);
}

// Подключаемся к Zadarma

$zd = new \Zadarma_API\Client($zadarmaKey, $zadarmaSecret);

// Получаем статистику звонков

$out = $zd->call('/v1/statistics/', [], 'get');

// Декодируем статистику

$records = json_decode($out, true);

// Проверяем успешность получения статистики

if ($records['status'] !== 'success' || !array_key_exists('stats', $records)) {
  consoleLog($out);
  consoleLog("Не удалось получить статистику у Zadarma");
  die;
}

// Сохраняем каждую запись из Zadarma в файл в папке bufferFolder

consoleLog("Получено всего " . count($records['stats']) . " записей из Zadarma");

foreach ($records['stats'] as $item) {
  saveItem($item);
}

// Подключаемся к AMO

$out = amoApiRequest('auth.php?type=json', 'POST', ['USER_LOGIN'=>$amoLogin,'USER_HASH'=>$amoKey]);

// Декодируем JSON ответ

$res = json_decode($out, true);
$res = $res['response'];

// Флаг успешной авторизации доступен в свойстве "auth"

if (!isset($res['auth'])) {
  die("Авторизация в AMO не удалась");
}

// Выводит информацию об учётной записи в АМО для отладки
//ddd(json_decode(amoApiRequest('v2/json/accounts/current'), true));

// Проходим по очереди по всем файлам в буфере и каждый отправляем в AMO

$files = scandir($bufferFolder);
foreach ($files as $filename) {
  if (!in_array($filename, ['.', '..'])) {
    // Отправляем в АМО
    if (!sendToAmo($filename)) {
      consoleLog("Отправка записи в АМО не удалась");
      die;
    } else {
      consoleLog("Запись $filename отправлена в AMO успешно");
    }
  }
}

// Сохраняет полученную от Zadarma запись в файл в папке bufferFolder
function saveItem($item) {
  global $bufferFolder, $resultFolder;

  $id = $item['id'];

  // Если уже есть файл для такой записи, повторно не сохраняем
  if (file_exists("$bufferFolder/$id") || file_exists("$resultFolder/$id")) {
    return;
  }

  // Кодируем в JSON

  $data = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

  // Сохраняем в файл

  file_put_contents("$bufferFolder/$id", $data);

  consoleLog("Получена новая запись $id из Zadarma");
}

// Отправляем файл в АМО, при удачной отправке перемещаем в result
function sendToAmo($filename) {
  global $bufferFolder, $resultFolder;

  // Достаём запись из файла
  $item = json_decode(file_get_contents("$bufferFolder/$filename"), true);

  if (!$item) {
    consoleLog("Не получилось загрузить запись $filename из файла");
    return false;
  }

  // Формируем лид для api AMO
  $name = $item['from'] . ' -> ' . $item['to'];
  $leads['request']['leads']['add'] = [
    [
      'name' => $name
    ]
  ];
  consoleLog('Сохраняем в AMO звонок ' . $name . ' от ' . $item['callstart']);

  // Отправляем в АМО
  $out = amoApiRequest('v2/json/leads/set', 'POST', $leads);
  $res = json_decode($out, true);

  // Если удачно отправили, то перемещаем файл в папку result
  if ($res && $res['response'] && $res['response']['leads']) {
    return rename("$bufferFolder/$filename", "$resultFolder/$filename");
  } else {
    consoleLog($out);
  }

  return false;
}

// Выполняет GET-запрос к AMO
function amoApiRequest($apiMethod, $httpMethod = 'GET', $postFields = null) {
  global $amoSubdomain;

  // API endpoint
  $link = 'https://'.$amoSubdomain.'.amocrm.ru/private/api/'.$apiMethod;
  $curl = curl_init(); // Сохраняем дескриптор сеанса cURL

  // Устанавливаем необходимые опции для сеанса cURL
  curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
  curl_setopt($curl,CURLOPT_URL,$link);

  if ($httpMethod == 'POST') {
    curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
    curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($postFields));
    curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
  }

  curl_setopt($curl,CURLOPT_HEADER,false);
  curl_setopt($curl,CURLOPT_COOKIEFILE,__DIR__.'/cookie.txt');
  curl_setopt($curl,CURLOPT_COOKIEJAR,__DIR__.'/cookie.txt');
  curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
  curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
   
  $out = curl_exec($curl); // Инициируем запрос к API и сохраняем ответ в переменную

  $code = curl_getinfo($curl,CURLINFO_HTTP_CODE); // Получаем код ответа
  curl_close($curl); // Закрываем соединение
  checkCurlResponse($code); // Проверяем, что ответ — не ошибка

  return $out;
}

// Переводит код ошибки CURL на человеческий язык
function checkCurlResponse($code)
{
  $code = (int)$code;
  $errors = array(
    301 => 'Moved permanently',
    400 => 'Bad request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not found',
    500 => 'Internal server error',
    502 => 'Bad gateway',
    503 => 'Service unavailable'
  );
  try
  {
    // Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
    if( $code != 200 && $code != 204)
      throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error',$code);
  }
  catch(Exception $E)
  {
    consoleLog('Ошибка: '.$E->getMessage().PHP_EOL.'Код ошибки: '.$E->getCode());
    die;
  }
}

function consoleLog($text)
{
  echo date('[Y-m-d H:i:s] ');
  echo "$text\n";
}
